// programs/crabswap_router/build.rs
use std::env;
use std::fs;
use std::path::PathBuf;

fn main() {
    // YOUR NEW ETERNAL PROGRAM ID — CHANGE ONLY HERE
    let program_id = "7veFwV1nAJm9eERH1d4u693wHoxgsHgiV5D2vi9fXr1z";

    let out_dir = PathBuf::from(env::var("OUT_DIR").unwrap());
    let dest_path = out_dir.join("program_id.rs");

    let content = format!(
        r#"
pub const PROGRAM_ID: &str = "{}";
::anchor_lang::declare_id!("{}");
"#,
        program_id, program_id
    );

    fs::write(dest_path, content).expect("Failed to write program_id.rs");

    println!("cargo:rerun-if-changed=build.rs");
}
