use wasm_bindgen::prelude::*;
use ark_bls12_381::{Bls12_381, Fr, G1Affine, G2Projective};
use ark_ec::{pairing::Pairing, AffineRepr, Group};
use ark_ff::PrimeField;
use ark_serialize::CanonicalSerialize;
use sha2::{Digest, Sha256};
use crab_secret::CRAB_SECRET_KEY;

/// Hash amount_in, min_out, nonce, wallet -> G2 point
fn hash_to_g2(msg: &[u8]) -> G2Projective {
    let mut hasher = Sha256::new();
    hasher.update(msg);
    hasher.update(b"CRABSWAP_BLS_V1");
    let digest = hasher.finalize();
    let scalar = Fr::from_le_bytes_mod_order(digest.as_slice());
    G2Projective::generator() * scalar
}

/// Computes the Miller loop output for the front-end transaction
#[wasm_bindgen]
pub fn compute_miller_output(
    amount_in: u32,
    min_out: u32,
    nonce: u32,
    wallet: &[u8],
) -> Vec<u8> {
    let sk = Fr::from_le_bytes_mod_order(&CRAB_SECRET_KEY);

    // Construct message exactly as front-end expects
    let mut msg = Vec::with_capacity(4*3 + wallet.len());
    msg.extend_from_slice(&amount_in.to_le_bytes());
    msg.extend_from_slice(&min_out.to_le_bytes());
    msg.extend_from_slice(&nonce.to_le_bytes());
    msg.extend_from_slice(wallet);

    let h_g2 = hash_to_g2(&msg);
    let pk_g1 = G1Affine::generator() * sk;

    // Compute Miller loop
    let miller = Bls12_381::multi_miller_loop(
        [pk_g1].as_ref(),
        [h_g2].as_ref(),
    );

    // Serialize compressed — 576 bytes
    let mut out = vec![0u8; 576];
    miller.0.serialize_compressed(&mut out[..]).unwrap();
    out
}
