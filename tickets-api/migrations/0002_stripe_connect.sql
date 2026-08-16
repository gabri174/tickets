-- Stripe Connect: one connected account per organizer.
-- Safe to run once on the D1 database.
ALTER TABLE admins ADD COLUMN stripe_account_id TEXT;
ALTER TABLE admins ADD COLUMN stripe_onboarding_status TEXT NOT NULL DEFAULT 'not_started';
ALTER TABLE admins ADD COLUMN stripe_charges_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE admins ADD COLUMN stripe_payouts_enabled INTEGER NOT NULL DEFAULT 0;

CREATE UNIQUE INDEX IF NOT EXISTS idx_admins_stripe_account_id
ON admins(stripe_account_id);
