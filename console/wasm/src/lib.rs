use wasm_bindgen::prelude::*;
use ark_bls12_381::{Bls12_381, Fr, G1Affine, G2Projective};
use ark_ec::{pairing::Pairing, AffineRepr, Group};
use ark_ff::PrimeField;
use ark_serialize::CanonicalSerialize;
use sha2::{Digest, Sha256};

/// Hash amount_in, min_out, nonce, wallet -> G2 point
fn hash_to_g2(msg: &[u8]) -> G2Projective {
    let mut hasher = Sha256::new();
    hasher.update(msg);
    hasher.update(b"CRABSWAP_BLS_V1");
    let digest = hasher.finalize();
    let scalar = Fr::from_le_bytes_mod_order(digest.as_slice());
    G2Projective::generator() * scalar
}

/// New v6 signature — secret key passed from JavaScript (chosen from the 16)
#[wasm_bindgen]
pub fn compute_miller_output(
    amount_in: u32,
    min_out: u32,
    nonce: u32,
    wallet: &[u8],
    secret_key_bytes: &[u8],  // ← 32-byte secret chosen randomly in JS
) -> Vec<u8> {
    let sk = Fr::from_le_bytes_mod_order(secret_key_bytes);

    let mut msg = Vec::with_capacity(12 + wallet.len());
    msg.extend_from_slice(&amount_in.to_le_bytes());
    msg.extend_from_slice(&min_out.to_le_bytes());
    msg.extend_from_slice(&nonce.to_le_bytes());
    msg.extend_from_slice(wallet);

    let h_g2 = hash_to_g2(&msg);
    let pk_g1 = G1Affine::generator() * sk;

    let miller = Bls12_381::multi_miller_loop(&[pk_g1], &[h_g2]);

    let mut out = vec![0u8; 576];
    miller.0.serialize_compressed(&mut out[..]).unwrap();
    out
}
