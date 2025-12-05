import {
  Connection,
  Keypair,
  PublicKey,
  Transaction,
  TransactionInstruction,
  SystemProgram,
} from "@solana/web3.js";
import fs from "fs";

// === CONFIG ===
const PROGRAM_ID = new PublicKey("7veFwV1nAJm9eERH1d4u693wHoxgsHgiV5D2vi9fXr1z");

// Load wallet (change path if needed)
const secretKey = Uint8Array.from(
  JSON.parse(fs.readFileSync(`${process.env.HOME}/.config/solana/new_wallet.json`, "utf8"))
);
const payer = Keypair.fromSecretKey(secretKey);

// FIXED URL ← this was your only real bug
const RPC_URL = "https://api.devnet.solana.com";
const connection = new Connection(RPC_URL, "confirmed");

console.log("Connecting to:", RPC_URL);
console.log("Payer:", payer.publicKey.toBase58());

// PDA for global state
const [globalPda] = PublicKey.findProgramAddressSync(
  [Buffer.from("global")],
  PROGRAM_ID
);

console.log("Global PDA:", globalPda.toBase58());

// init_global instruction (your discriminator is correct for Anchor init_global)
const ix = new TransactionInstruction({
  programId: PROGRAM_ID,
  keys: [
    { pubkey: payer.publicKey, isSigner: true,  isWritable: true },
    { pubkey: globalPda,        isSigner: false, isWritable: true },
    { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
  ],
  data: Buffer.from([44, 238, 77, 253, 76, 182, 192, 162]), // init_global discriminator
});

const main = async () => {
  try {
    // Get fresh blockhash (required for legacy Transaction)
    const { blockhash, lastValidBlockHeight } = await connection.getLatestBlockhash("confirmed");

    const tx = new Transaction({
      recentBlockhash: blockhash,
      feePayer: payer.publicKey,
      lastValidBlockHeight,
    }).add(ix);

    // Send & sign in one call
    const signature = await connection.sendTransaction(tx, [payer], {
      skipPreflight: false,
      preflightCommitment: "confirmed",
    });

    console.log("\n✅ Initialization successful!");
    console.log("Signature:", `https://solana.fm/tx/${signature}?cluster=devnet-solana`);
    console.log("Explorer:", `https://explorer.solana.com/tx/${signature}?cluster=devnet`);

    // Wait for confirmation
    await connection.confirmTransaction(
      { signature, blockhash, lastValidBlockHeight },
      "confirmed"
    );
    console.log("Transaction confirmed");
  } catch (err) {
    console.error("\n❌ Transaction failed:", err);

    if (err.logs) console.error("Program logs:", err.logs);
    if (err.message?.includes("0x1")) console.error("Error 0x1 = Insufficient funds for fee + rent");
    if (err.message?.includes("custom program error: 0x0")) console.error("Already initialized or wrong discriminator");
  }
};

main();
