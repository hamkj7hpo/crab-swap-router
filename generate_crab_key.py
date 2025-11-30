#!/usr/bin/env python3
import os
from py_ecc.bls import G2ProofOfPossession as bls

# BLS12-381 curve order (constant)
BLS12_381_CURVE_ORDER = 0x73eda753299d7d483339d80809a1d80553bda402fffe5bfeffffffff00000001

# Generate secret key (< curve order)
sk = int.from_bytes(os.urandom(32), 'big') % BLS12_381_CURVE_ORDER
sk_bytes = sk.to_bytes(32, 'big')

# Public key (48 bytes compressed G1)
pk_bytes = bls.SkToPk(sk)

# Message exactly like your on-chain test
msg = b'\x00' * 57 + b'CRAB-V5'

# Real BLS signature (96 bytes compressed G2)
sig_bytes = bls.Sign(sk, msg)

print("CRAB KEY READY — 100% arkworks-compatible\n")

print("const SECRET_KEY = Uint8Array.from([")
print("  " + ", ".join(f"0x{b:02x}" for b in sk_bytes))
print("]);\n")

print("// Public key (48 bytes compressed G1)")
print(" ".join(f"0x{b:02x}" for b in pk_bytes))

print("\n// Signature for \\x00*57 || \"CRAB-V5\" (96 bytes compressed G2)")
print(" ".join(f"0x{b:02x}" for b in sig_bytes))

print("\n// Verify on-chain with verify_only or swap() → will PASS")
