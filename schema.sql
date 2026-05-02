-- Shelah Database Schema
-- PostgreSQL (Neon)

-- Users
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Friendships (bidirectional)
CREATE TABLE IF NOT EXISTS friendships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    friend_id UUID REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, friend_id)
);

-- Location Types (seed data)
CREATE TABLE IF NOT EXISTS location_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50)
);

-- Places
CREATE TABLE IF NOT EXISTS places (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    location_type_id UUID REFERENCES location_types(id),
    address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    rating DECIMAL(2,1),
    popularity VARCHAR(20),
    price_per_person_egp INTEGER,
    thumbnail_url TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Outings
CREATE TABLE IF NOT EXISTS outings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    creator_id UUID REFERENCES users(id),
    outing_type VARCHAR(20) NOT NULL,
    scheduled_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Outing Members
CREATE TABLE IF NOT EXISTS outing_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    invite_status VARCHAR(20) DEFAULT 'pending',
    requirements_submitted BOOLEAN DEFAULT FALSE,
    invited_by UUID REFERENCES users(id),
    invite_approved BOOLEAN DEFAULT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, user_id)
);

-- Invite Approvals for restricted outings
CREATE TABLE IF NOT EXISTS invite_approvals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    candidate_user_id UUID REFERENCES users(id),
    voter_user_id UUID REFERENCES users(id),
    approved BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, candidate_user_id, voter_user_id)
);

-- User Requirements per Outing
CREATE TABLE IF NOT EXISTS user_requirements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    home_latitude DECIMAL(10, 8),
    home_longitude DECIMAL(11, 8),
    max_distance_km INTEGER,
    popularity_preference VARCHAR(20),
    min_rating DECIMAL(2,1),
    max_price_egp INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, user_id)
);

-- Location Type Preferences per Requirement
CREATE TABLE IF NOT EXISTS requirement_location_types (
    requirement_id UUID REFERENCES user_requirements(id) ON DELETE CASCADE,
    location_type_id UUID REFERENCES location_types(id),
    PRIMARY KEY (requirement_id, location_type_id)
);

-- Votes on Places per Outing
CREATE TABLE IF NOT EXISTS place_votes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    place_id UUID REFERENCES places(id),
    user_id UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, place_id, user_id)
);
