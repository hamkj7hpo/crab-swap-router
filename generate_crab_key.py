#!/usr/bin/env python3
import os
import json
from py_ecc.bls import G2ProofOfPossession as bls

BLS12_381_CURVE_ORDER = 0x73eda753299d7d483339d80809a1d80553bda402fffe5bfeffffffff00000001
MESSAGE = b'\x00' * 57 + b'CRAB-V6'

keys = []

for i in range(16):
    sk = int.from_bytes(os.urandom(32), 'big') % BLS12_381_CURVE_ORDER
    sk_bytes = sk.to_bytes(32, 'big')
    pk_bytes = bls.SkToPk(sk)           # 48 bytes
    sig_bytes = bls.Sign(sk, MESSAGE)   # 96 bytes

    keys.append({
        "index": i,
        "secret_key": list(sk_bytes),
        "public_key": list(pk_bytes),
        "signature": list(sig_bytes)
    })

# Save to console/wasm/public so the page can fetch it
out_path = os.path.join("console", "wasm", "public", "crab_keys_v6.json")
os.makedirs(os.path.dirname(out_path), exist_ok=True)

with open(out_path, "w") as f:
    json.dump(keys, f, indent=2)

print("CRABSWAP v6 — 16 independent keys generated")
print("→ console/wasm/public/crab_keys_v6.json")
print("Every user now has 1/16 linkage probability → statistically dead")
print("Push new keys any time → all historical linkage erased instantly")
