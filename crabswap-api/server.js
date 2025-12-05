// server.js — CRABSWAP v7 FINAL — SELF-FUNDING + 16-KEY STEALTH
const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const {
  Connection,
  Keypair,
  Transaction,
  ComputeBudgetProgram,
  TransactionInstruction,
  PublicKey,
  sendAndConfirmTransaction,
  SystemProgram
} = require('@solana/web3.js');

const app = express();
app.use(express.json({ limit: '20mb' }));
app.use(cors());

/*
 * === NEW: Serve static client assets from ./public
 * Put browser-side helper files (generateRequest.js, any client helpers, wasm loader shim, etc.)
 * into crabswap-api/public so we avoid serving Node-only generated files to browsers.
 */
const PUBLIC_DIR = path.join(__dirname, 'public');
if (!fs.existsSync(PUBLIC_DIR)) {
  fs.mkdirSync(PUBLIC_DIR, { recursive: true });
}
app.use(express.static(PUBLIC_DIR, { extensions: ['js', 'mjs'] }));

console.log("\nCRABSWAP v7 VAULT BOOTING — STEALTH + SELF-FUNDING MODE");

// ======================== MAGNET BOARD ========================
const MAGNET_FILE = path.join(__dirname, 'magnet_board.json');
const MAGNET_TTL = 600_000; // 10 minutes

let magnets = [];

// Load magnets on startup
function loadMagnets() {
  if (fs.existsSync(MAGNET_FILE)) {
    try {
      const raw = JSON.parse(fs.readFileSync(MAGNET_FILE, 'utf-8'));
      const now = Date.now();
      magnets = raw.filter(m => m.ts > now - MAGNET_TTL);
      console.log(`Loaded ${magnets.length} active magnets`);
    } catch (e) {
      console.log("No valid magnet_board.json — starting fresh");
      magnets = [];
    }
  }
}

function saveMagnets() {
  const now = Date.now();
  const active = magnets.filter(m => m.ts > now - MAGNET_TTL);
  fs.writeFileSync(MAGNET_FILE, JSON.stringify(active, null, 2));
}

loadMagnets();
setInterval(saveMagnets, 20_000);

// Public magnet board
app.get('/magnets', (req, res) => {
  const now = Date.now();
  const active = magnets.filter(m => m.ts > now - MAGNET_TTL);
  res.json(active.map(m => ({ hash: m.hash, amount: m.amount })));
});

// ======================== VAULT SETUP ========================
const keypairPath = path.join(process.env.HOME, ".config", "solana", "new_wallet.json");
let vaultKeypair;

try {
  const secret = JSON.parse(fs.readFileSync(keypairPath, "utf-8"));
  vaultKeypair = Keypair.fromSecretKey(Uint8Array.from(secret));
  console.log("VAULT ONLINE:", vaultKeypair.publicKey.toBase58());
} catch (err) {
  console.error("Vault key missing:", err.message);
  process.exit(1);
}

const PROGRAM_ID = new PublicKey("7veFwV1nAJm9eERH1d4u693wHoxgsHgiV5D2vi9fXr1z");
const connection = new Connection("https://api.devnet.solana.com", "confirmed");

const [globalPda] = PublicKey.findProgramAddressSync([Buffer.from("global")], PROGRAM_ID);

// ======================== MAIN /swap ========================
app.post('/swap', async (req, res) => {
  console.log("\nSWAP REQUEST RECEIVED");

  const debug = req.query.debug === '1' || req.headers['x-debug'] === '1';
  if (debug) return res.json({ ok: true, message: "debug mode" });

  const {
    amount_in,
    minimum_out = 0,
    deadline,
    miller_output,
    destination,
    magnet_hash,
    magnet_amount
  } = req.body;

  const proof = Array.isArray(miller_output) ? miller_output : null;

  if (!amount_in || !deadline || !proof || proof.length !== 576 || !destination) {
    return res.status(400).json({ error: "invalid payload" });
  }

  try {
    const proofBuffer = Uint8Array.from(proof);
    const sessionPubkey = vaultKeypair.publicKey;

    const [sessionCounterPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("session_counter"), sessionPubkey.toBuffer()],
      PROGRAM_ID
    );

    const counterInfo = await connection.getAccountInfo(sessionCounterPda);
    let counter = 0;
    if (counterInfo?.data?.length >= 12) {
      counter = counterInfo.data.readUInt32LE(8);
    }

    const counterBuf = Buffer.alloc(4);
    counterBuf.writeUInt32LE(counter, 0);

    const [swapStatePda] = PublicKey.findProgramAddressSync(
      [Buffer.from("swap_state"), sessionPubkey.toBuffer(), counterBuf],
      PROGRAM_ID
    );

    const [deployerMarkerPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("deployer"), sessionPubkey.toBuffer()],
      PROGRAM_ID
    );

    const tx = new Transaction();

    const startSwapIx = new TransactionInstruction({
      programId: PROGRAM_ID,
      keys: [
        { pubkey: sessionPubkey, isSigner: true, isWritable: true },
        { pubkey: deployerMarkerPda, isSigner: false, isWritable: false },
        { pubkey: sessionCounterPda, isSigner: false, isWritable: true },
        { pubkey: globalPda, isSigner: false, isWritable: true },
        { pubkey: swapStatePda, isSigner: false, isWritable: true },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false }
      ],
      data: Buffer.concat([
        Buffer.from([118,137,51,60,156,242,171,155]),
        Buffer.from(new Uint32Array([amount_in, minimum_out]).buffer),
        Buffer.from(new BigInt64Array([BigInt(deadline)]).buffer),
        proofBuffer
      ])
    });

    const verifyIx = new TransactionInstruction({
      programId: PROGRAM_ID,
      keys: [
        { pubkey: sessionPubkey, isSigner: true, isWritable: false },
        { pubkey: swapStatePda, isSigner: false, isWritable: true }
      ],
      data: Buffer.from([217,211,191,110,144,13,186,98])
    });

    const execIx = new TransactionInstruction({
      programId: PROGRAM_ID,
      keys: [
        { pubkey: sessionPubkey, isSigner: true, isWritable: true },
        { pubkey: swapStatePda, isSigner: false, isWritable: true },
        { pubkey: globalPda, isSigner: false, isWritable: true },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false }
      ],
      data: Buffer.from([56,182,124,215,155,140,157,102])
    });

    tx.add(
      ComputeBudgetProgram.setComputeUnitLimit({ units: 1_400_000 }),
      startSwapIx,
      verifyIx,
      execIx
    );

    const { blockhash } = await connection.getLatestBlockhash();
    tx.recentBlockhash = blockhash;
    tx.feePayer = sessionPubkey;

    const sig = await sendAndConfirmTransaction(connection, tx, [vaultKeypair], { commitment: "confirmed" });

    console.log(`SUCCESS — Crab #${counter + 1} spawned | TX: ${sig}`);

    // Save magnet if provided
    if (magnet_hash && magnet_amount && Array.isArray(magnet_hash) && magnet_hash.length === 32) {
      magnets.push({ hash: magnet_hash, amount: magnet_amount, ts: Date.now() });
      saveMagnets();
      console.log(`Magnet posted — ${magnet_amount / 1e9} SOL pending`);
    }

    return res.json({
      success: true,
      tx: sig,
      crab_id: counter + 1
    });

  } catch (err) {
    console.error("VAULT ERROR:", err);
    return res.status(500).json({
      error: "swap failed",
      details: err.message
    });
  }
});

// Health
app.get('/', (req, res) => res.send("CRABSWAP v7 — LIVE"));
app.get('/magnets', (req, res) => {
  const now = Date.now();
  res.json(magnets.filter(m => m.ts > now - MAGNET_TTL).map(m => ({ hash: m.hash, amount: m.amount })));
});

app.listen(3000, '127.0.0.1', () => {
  console.log("CRABSWAP v7 VAULT RUNNING — 127.0.0.1:3000");
});
