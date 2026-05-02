-- Migration V3: Replace budget_tier with numerical max_price_egp

-- 1. Update user_requirements table
ALTER TABLE user_requirements
ADD COLUMN max_price_egp INTEGER;

-- Optionally migrate data if possible, but since it's hard to map tier to int precisely without context, 
-- we can just leave it null (which means 'any budget'). If we wanted to:
 UPDATE user_requirements SET max_price_egp = 500 WHERE budget_tier = 'budget';
 UPDATE user_requirements SET max_price_egp = 1500 WHERE budget_tier = 'moderate';
 UPDATE user_requirements SET max_price_egp = 3000 WHERE budget_tier = 'upscale';
 UPDATE user_requirements SET max_price_egp = 10000 WHERE budget_tier = 'luxury';

ALTER TABLE user_requirements
DROP COLUMN budget_tier;

-- 2. Update places table (remove budget_tier as price_per_person_egp already exists)
ALTER TABLE places
DROP COLUMN budget_tier;
