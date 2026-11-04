-- ==============================================================================
-- UniServe Database Normalization & Mobile API Token Schema
-- ==============================================================================

-- 1. Create API Tokens Table for Mobile App Authentication
CREATE TABLE IF NOT EXISTS api_tokens (
    token_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    token VARCHAR(128) NOT NULL UNIQUE,
    device_info VARCHAR(255) DEFAULT 'Android Device',
    expires_at TIMESTAMPTZ NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL '30 days'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_api_tokens_token ON api_tokens(token);
CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens(user_id);

-- 2. Add extra profile fields to users if not present
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='phone_number') THEN
        ALTER TABLE users ADD COLUMN phone_number VARCHAR(30);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='avatar_url') THEN
        ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255);
    END IF;
END $$;

-- 3. Add extra fields to categories, businesses, and services if not present
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='categories' AND column_name='icon_name') THEN
        ALTER TABLE categories ADD COLUMN icon_name VARCHAR(50) DEFAULT 'category';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='businesses' AND column_name='logo_url') THEN
        ALTER TABLE businesses ADD COLUMN logo_url VARCHAR(255);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='services' AND column_name='image_url') THEN
        ALTER TABLE services ADD COLUMN image_url VARCHAR(255);
    END IF;
END $$;
