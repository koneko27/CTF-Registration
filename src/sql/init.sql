CREATE TABLE IF NOT EXISTS users (
	id SERIAL PRIMARY KEY,
	full_name VARCHAR(100) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	username VARCHAR(50) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(20) NOT NULL DEFAULT 'user',
	avatar_data BYTEA DEFAULT NULL,
	avatar_mime VARCHAR(100) DEFAULT NULL,
	avatar_updated_at TIMESTAMP DEFAULT NULL,
	bio TEXT DEFAULT NULL CHECK (bio IS NULL OR LENGTH(bio) <= 1000),
	location VARCHAR(100) DEFAULT NULL,
	email_verified BOOLEAN DEFAULT FALSE,
	locked_until TIMESTAMP DEFAULT NULL,
	token_version INTEGER NOT NULL DEFAULT 1,
	updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
	created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS competitions (
	id SERIAL PRIMARY KEY,
	name VARCHAR(255) NOT NULL,
	description TEXT CHECK (description IS NULL OR LENGTH(description) <= 50000),
	start_date TIMESTAMP NOT NULL,
	end_date TIMESTAMP NOT NULL,
	registration_deadline TIMESTAMP NOT NULL,
	max_participants INTEGER DEFAULT NULL CHECK (max_participants IS NULL OR (max_participants > 0 AND max_participants <= 100000)),
	difficulty_level VARCHAR(50) DEFAULT 'beginner',
	prize_pool VARCHAR(255) DEFAULT NULL,
	category VARCHAR(100) NOT NULL,
	rules TEXT CHECK (rules IS NULL OR LENGTH(rules) <= 50000),
	contact_person VARCHAR(255),
	banner_data BYTEA DEFAULT NULL,
	banner_mime VARCHAR(100) DEFAULT NULL,
	banner_updated_at TIMESTAMP DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT NOW(),
	CHECK (difficulty_level IN ('beginner', 'intermediate', 'advanced', 'expert')),
	CHECK (end_date > start_date),
	CHECK (registration_deadline <= start_date)
);

CREATE TABLE IF NOT EXISTS competition_registrations (
	id SERIAL PRIMARY KEY,
	user_id INTEGER NOT NULL,
	competition_id INTEGER NOT NULL,
	team_name VARCHAR(255) DEFAULT NULL,
	registration_status VARCHAR(20) NOT NULL DEFAULT 'pending',
	payment_status VARCHAR(20) DEFAULT 'unpaid',
	registration_notes TEXT DEFAULT NULL CHECK (registration_notes IS NULL OR LENGTH(registration_notes) <= 5000),
	score INTEGER DEFAULT 0 CHECK (score >= 0 AND score <= 1000000),
	rank INTEGER DEFAULT NULL CHECK (rank IS NULL OR (rank > 0 AND rank <= 100000)),
	registered_at TIMESTAMP NOT NULL DEFAULT NOW(),
	updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
	UNIQUE(user_id, competition_id),
	CHECK (registration_status IN ('pending', 'approved', 'rejected', 'cancelled', 'waitlisted')),
	CHECK (payment_status IN ('unpaid', 'pending', 'paid', 'refunded'))
);

CREATE TABLE IF NOT EXISTS user_activity (
	id BIGSERIAL PRIMARY KEY,
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	activity_type VARCHAR(100) NOT NULL,
	description TEXT NOT NULL,
	metadata JSONB DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_activity_user_created ON user_activity (user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS failed_login_attempts (
	id BIGSERIAL PRIMARY KEY,
	identifier VARCHAR(255) NOT NULL,
	attempt_at TIMESTAMP NOT NULL DEFAULT NOW(),
	ip_address VARCHAR(45),
	user_agent TEXT,
	created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_failed_login_identifier ON failed_login_attempts (identifier, attempt_at DESC);

CREATE TABLE IF NOT EXISTS user_sessions (
	id BIGSERIAL PRIMARY KEY,
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	selector VARCHAR(255) NOT NULL,
	hashed_validator VARCHAR(255) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT NOW(),
	UNIQUE(selector)
);

CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_user_sessions_expires ON user_sessions (expires_at);

CREATE TABLE IF NOT EXISTS password_resets (
	id BIGSERIAL PRIMARY KEY,
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	token_hash VARCHAR(255) NOT NULL,
	expires_at TIMESTAMP NOT NULL,
	used BOOLEAN NOT NULL DEFAULT FALSE,
	created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets (token_hash);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id);

CREATE TABLE IF NOT EXISTS rate_limits (
	id BIGSERIAL PRIMARY KEY,
	rate_key VARCHAR(255) NOT NULL,
	attempt_at INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_key_time ON rate_limits (rate_key, attempt_at);