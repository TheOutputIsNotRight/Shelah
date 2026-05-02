-- Shelah v2 Migration
-- Run this manually against your Neon database

ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_code VARCHAR(20) UNIQUE;
ALTER TABLE outings ADD COLUMN IF NOT EXISTS description TEXT;

-- Generate invite codes for existing users that don't have one
UPDATE users SET invite_code = LOWER(SUBSTRING(display_name FROM 1 FOR 4) || '-' || SUBSTRING(gen_random_uuid()::text FROM 1 FOR 5))
WHERE invite_code IS NULL;
