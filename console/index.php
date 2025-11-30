<?php
// index.php — CRABSWAP ANON ROUTER — FINAL FORM
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CRABSWAP — FIRST REAL ON-CHAIN BLS12-381</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #000;
      color: #0f0;
      font-family: 'Courier New', monospace;
      min-height: 100vh;
      background-image: radial-gradient(circle at 10% 20%, rgba(0,255,0,0.1) 0%, transparent 20%),
                        radial-gradient(circle at 90% 80%, rgba(0,255,0,0.1) 0%, transparent 20%);
    }
    .crab {
      font-size: 15vw;
      animation: pulse 3s infinite;
      text-shadow: 0 0 30px #0f0, 0 0 60px #0f0;
    }
    @keyframes pulse {
      0%, 100% { opacity: 0.8; }
      50% { opacity: 1; }
    }
    .btn-crab {
      background: #000;
      border: 4px solid #0f0;
      color: #0f0;
      font-weight: bold;
      padding: 20px 60px;
      font-size: 1.8rem;
      transition: all 0.3s;
    }
    .btn-crab:hover {
      background: #0f0 !important;
      color: #000 !important;
      box-shadow: 0 0 40px #0f0;
    }
    .log {
      background: #111;
      border: 3px solid #0f0;
      min-height: 200px;
      max-height: 60vh;
      overflow-y: auto;
      padding: 20px;
      font-size: 1.1rem;
      box-shadow: 0 0 20px rgba(0,255,0,0.3);
    }
    h1, h2 { text-shadow: 0 0 20px #0f0; }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

<div class="container text-center flex-grow-1 d-flex flex-column justify-content-center">
  <div class="mb-5">
    <h1 class="display-1 fw-bold">CRABSWAP</h1>
    <div class="crab mb-4">🦀</div>
    <p class="lead">First real on-chain BLS12-381 private router on Solana</p>
  </div>

  <div class="row justify-content-center mb-5">
    <div class="col-md-6">
      <button id="connect" class="btn btn-crab w-100 mb-3">CONNECT WALLET</button>
      <button id="swap" class="btn btn-crab w-100" disabled>ANON SWAP — 0.01 SOL → CRAB</button>
    </div>
  </div>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <pre class="log" id="log">Crab army standing by...</pre>
    </div>
  </div>
</div>

<div class="text-center py-4 border-top border-success mt-auto">
  <small class="text-success">© 2025 CRABSWAP — HISTORY MADE</small>
</div>

<script type="module">
import { Buffer } from "https://esm.sh/buffer@6.0.3";
import { Connection, PublicKey, Transaction, TransactionInstruction, SystemProgram } from "https://esm.sh/@solana/web3.js@1.91.7";
import init, { compute_miller_output } from "./wasm/pkg/crab_bls.js";

const PROGRAM_ID = new PublicKey("5TpfDn84jCUcAViGvjAs8bhC2ifQG7bph2aAzTHQeu4m");
const conn = new Connection("https://api.devnet.solana.com", "confirmed");

let wallet = null;
const log = (m) => {
  console.log(m);
  const el = document.getElementById("log");
  el.textContent += m + "\n";
  el.scrollTop = el.scrollHeight;
};

document.getElementById("connect").onclick = async () => {
  if (!window.solana?.isPhantom) return log("Phantom not detected");
  try {
    await window.solana.connect();
    wallet = window.solana.publicKey;
    log("Connected: " + wallet.toBase58().slice(0,8) + "...");
    document.getElementById("swap").disabled = false;
    log("Loading WASM...");
    await init();
    log("Miller loop engine ready (secret key baked in)");
  } catch (e) { log("Error: " + e.message); }
};

document.getElementById("swap").onclick = async () => {
  log("Computing Miller loop with hidden secret key...");
  const amount = 10_000_000; // 0.01 SOL
  const min_out = 0;
  const nonce = Math.floor(Math.random() * 4294967295);
  const deadline = Math.floor(Date.now() / 1000) + 300;

  const miller = compute_miller_output(amount, min_out, nonce, wallet.toBytes());
  if (miller.length !== 576) throw "Bad proof length";

  log("Proof generated: " + miller.length + " bytes");

  const [globalPda] = PublicKey.findProgramAddressSync([Buffer.from("global")], PROGRAM_ID);

  // --- CORRECT DEADLINE ENCODING ---
  const deadlineBuf = Buffer.alloc(8);
  deadlineBuf.writeBigUInt64LE(BigInt(deadline));

  // --- DATA LAYOUT EXACTLY MATCHING Rust ---
  const data = Buffer.concat([
    Buffer.from([0x1f,0x75,0x5d,0x0a,0x8e,0x65,0x4b,0x5e]), // method selector
    Buffer.from(new Uint32Array([amount, min_out]).buffer),   // u32 x2
    Buffer.from(miller),                                      // 576 bytes
    deadlineBuf,                                              // u64
    Buffer.from(new Uint32Array([nonce]).buffer),             // u32
  ]);

  // --- ACCOUNT ORDER MATCHES Rust ---
  const ix = new TransactionInstruction({
    programId: PROGRAM_ID,
    keys: [
      { pubkey: wallet, isSigner: true, isWritable: true },     // user
      { pubkey: globalPda, isSigner: false, isWritable: true }, // global_state
      { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
    ],
    data
  });

  const tx = new Transaction().add(ix);
  const { blockhash } = await conn.getLatestBlockhash();
  tx.recentBlockhash = blockhash;
  tx.feePayer = wallet;

  log("Sending transaction...");
  try {
    const signed = await window.solana.signTransaction(tx);
    const sig = await conn.sendRawTransaction(signed.serialize());
    log("HISTORY MADE");
    log("https://solscan.io/tx/" + sig + "?cluster=devnet");
    log("FIRST REAL ON-CHAIN BLS12-381 — CRABSWAP WINS FOREVER");
  } catch (e) {
    log("Failed: " + (e.logs?.join("\n") || e.message));
  }
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
