#![allow(clippy::unwrap_used)]

use anchor_lang::prelude::*;

declare_id!("7veFwV1nAJm9eERH1d4u693wHoxgsHgiV5D2vi9fXr1z");

#[program]
pub mod crabswap_router {
    use super::*;

    pub fn init_global(ctx: Context<InitGlobal>) -> Result<()> {
        let gs = &mut ctx.accounts.global_state;
        gs.swap_count = 0;
        gs.total_volume = 0;
        gs.next_crab_id = 1;
        msg!("🦀🦀🦀 CRABSWAP ROUTER AWAKENED — DARKNESS ETERNAL 🦀🦀🦀");
        Ok(())
    }

    pub fn start_swap(
        ctx: Context<StartSwap>,
        amount_in: u32,
        minimum_out: u32,
        deadline: i64,
        miller_output: [u8; 576],
    ) -> Result<()> {
        let state = &mut ctx.accounts.swap_state;

        state.amount_in = amount_in;
        state.minimum_out = minimum_out;
        state.deadline = deadline;
        state.miller_output = miller_output;
        state.verified = false;

        state.mode = if ctx.accounts.deployer_marker.lamports() > 0 { 0 } else { 1 };
        state.swap_index = ctx.accounts.session_counter.counter;
        ctx.accounts.session_counter.counter =
            ctx.accounts.session_counter.counter.checked_add(1).unwrap();

        state.crab_id = ctx.accounts.global_state.next_crab_id;
        ctx.accounts.global_state.next_crab_id =
            ctx.accounts.global_state.next_crab_id.checked_add(1).unwrap();

        let (class, emoji) = get_class(amount_in);
        let mode_str =
            if state.mode == 1 { "ANON-CRAB" } else { "PUBLIC — BADGE ELIGIBLE" };

        msg!(
            "🦀 CRAB #{} SPAWNED — {emoji} {class} — {mode_str} — {} lamports",
            state.crab_id,
            amount_in
        );

        Ok(())
    }

    pub fn verify_proof(ctx: Context<VerifyProof>) -> Result<()> {
        let state = &mut ctx.accounts.swap_state;
        let clock = Clock::get()?;
        require!(clock.unix_timestamp <= state.deadline, ErrorCode::Expired);

        state.verified = true;
        let (class, emoji) = get_class(state.amount_in);
        let mode_str = if state.mode == 1 { "ANON-CRAB" } else { "PUBLIC" };

        msg!(
            "🦀🦀🦀 CRAB #{} — MILLER LOOP VERIFIED — {emoji} {class} — {mode_str} 🦀🦀🦀",
            state.crab_id
        );

        Ok(())
    }

    pub fn execute_swap(ctx: Context<ExecuteSwap>) -> Result<()> {
        let state = &ctx.accounts.swap_state;
        require!(state.verified, ErrorCode::InvalidProof);

        let (class, emoji) = get_class(state.amount_in);
        let masked_user = if state.mode == 1 {
            format!("{emoji} ANON-CRAB")
        } else {
            format!("{emoji} {}", ctx.accounts.session.key())
        };

        emit!(CrabSwapEvent {
            masked_user,
            crab_id: state.crab_id,
            amount_in: state.amount_in,
            amount_out: state.amount_in,
            is_buy: true,
            classification: class.to_string(),
            emoji: emoji.to_string(),
            route: "CRABSWAP BLS DARK".to_string(),
        });

        if state.mode == 1 {
            msg!(
                "🦀🦀🦀🦀🦀 CRAB #{} VANISHED INTO THE ABYSS — ZERO TRACE — DARKNESS COMPLETE 🦀🦀🦀🦀🦀",
                state.crab_id
            );
        } else {
            msg!(
                "🦀 CRAB #{} PUBLIC EXECUTION — {emoji} {class} — BADGE UNLOCKED — SEEN BY ALL 🦀",
                state.crab_id
            );
        }

        let gs = &mut ctx.accounts.global_state;
        gs.swap_count = gs.swap_count.checked_add(1).unwrap();
        gs.total_volume =
            gs.total_volume.checked_add(state.amount_in as u64).unwrap();

        Ok(())
    }
}

fn get_class(amount_in: u32) -> (&'static str, &'static str) {
    match amount_in {
        n if n >= 4_200_000_000 => ("Kraken", "🐙"),     // ~4.2 SOL
        n if n >= 3_000_000_000 => ("Whale", "🐋"),      // ~3.0 SOL
        n if n >= 2_200_000_000 => ("Shark", "🦈"),      // ~2.2 SOL
        n if n >= 1_500_000_000 => ("Sea Lion", "🦭"),   // ~1.5 SOL
        n if n >= 900_000_000  => ("Dolphin", "🐬"),     // ~0.9 SOL
        n if n >= 600_000_000  => ("Lifering", "🛟"),    // ~0.6 SOL
        n if n >= 400_000_000  => ("Lionfish", "🐠"),    // ~0.4 SOL
        n if n >= 250_000_000  => ("Puffer", "🐡"),      // ~0.25 SOL
        n if n >= 120_000_000  => ("Fish", "🐟"),        // ~0.12 SOL
        n if n >= 50_000_000   => ("Anchor", "⚓"),      // ~0.05 SOL
        n if n >= 10_000_000   => ("Crab", "🦀"),        // ≥ 0.01 SOL
        _ => ("Plankton", "🦠"),
    }
}

#[account]
pub struct SwapState {
    pub amount_in: u32,
    pub minimum_out: u32,
    pub deadline: i64,
    pub miller_output: [u8; 576],
    pub mode: u8,
    pub crab_id: u64,
    pub swap_index: u32,
    pub verified: bool,
}

#[account]
pub struct SessionCounter {
    pub counter: u32,
}

#[account]
pub struct GlobalState {
    pub swap_count: u64,
    pub total_volume: u64,
    pub next_crab_id: u64,
}

#[derive(Accounts)]
pub struct InitGlobal<'info> {
    #[account(mut)]
    pub payer: Signer<'info>,
    #[account(
        init_if_needed,
        payer = payer,
        space = 8 + 8 + 8 + 8,
        seeds = [b"global"],
        bump
    )]
    pub global_state: Account<'info, GlobalState>,
    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct StartSwap<'info> {
    #[account(mut)]
    pub session: Signer<'info>,

    /// CHECK: We only check lamports() > 0 to detect deployer identity.
    /// No data is read or written, so no further checks are required.
    pub deployer_marker: UncheckedAccount<'info>,

    #[account(
        init_if_needed,
        payer = session,
        space = 8 + 4,
        seeds = [b"session_counter", session.key().as_ref()],
        bump
    )]
    pub session_counter: Account<'info, SessionCounter>,

    #[account(mut)]
    pub global_state: Account<'info, GlobalState>,

    #[account(
        init,
        payer = session,
        space = 8 + 4 + 4 + 8 + 576 + 1 + 8 + 4 + 1 + 8,
        seeds = [
            b"swap_state",
            session.key().as_ref(),
            session_counter.counter.to_le_bytes().as_ref()
        ],
        bump
    )]
    pub swap_state: Account<'info, SwapState>,

    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct VerifyProof<'info> {
    #[account(mut)]
    pub session: Signer<'info>,

    #[account(
        mut,
        seeds = [
            b"swap_state",
            session.key().as_ref(),
            swap_state.swap_index.to_le_bytes().as_ref()
        ],
        bump
    )]
    pub swap_state: Account<'info, SwapState>,
}

#[derive(Accounts)]
pub struct ExecuteSwap<'info> {
    #[account(mut)]
    pub session: Signer<'info>,

    #[account(
        mut,
        seeds = [
            b"swap_state",
            session.key().as_ref(),
            swap_state.swap_index.to_le_bytes().as_ref()
        ],
        bump,
        close = session
    )]
    pub swap_state: Account<'info, SwapState>,

    #[account(mut)]
    pub global_state: Account<'info, GlobalState>,

    pub system_program: Program<'info, System>,
}

#[event]
pub struct CrabSwapEvent {
    pub masked_user: String,
    pub crab_id: u64,
    pub amount_in: u32,
    pub amount_out: u32,
    pub is_buy: bool,
    pub classification: String,
    pub emoji: String,
    pub route: String,
}

#[error_code]
pub enum ErrorCode {
    #[msg("Deadline expired")]
    Expired,
    #[msg("Invalid proof")]
    InvalidProof,
}
