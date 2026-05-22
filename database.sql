-- This SQL file defines the database schema for a system that manages multiple identities (members) within a system, along with fronting sessions and user management.

-- Each system can have multiple members, and each member can have various attributes such as pronouns, avatar, color, description, and role. Systems can be public or private.
CREATE TABLE systems (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_public TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Each member represents a distinct identity within a system, with optional pronouns, avatar, color, description, and role.
CREATE TABLE members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  pronouns VARCHAR(50),
  avatar_url VARCHAR(255),
  color VARCHAR(7),         -- hex color, e.g. #a3c4f3
  description TEXT,
  role VARCHAR(100),        -- host, protector, etc. (free text is fine)
  visibility ENUM('public','friends','private') DEFAULT 'private',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
);

-- Fronting sessions represent periods of time where one or more members are active. They can have an optional note and track when they started and ended.
CREATE TABLE fronting_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_id INT NOT NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  note TEXT,
  FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
);

-- This table links members to fronting sessions, allowing for multiple members to be active in a single session. The combination of session_id and member_id is unique to prevent duplicate entries.
CREATE TABLE fronting_session_members (
  session_id INT NOT NULL,
  member_id INT NOT NULL,
  PRIMARY KEY (session_id, member_id),
  FOREIGN KEY (session_id) REFERENCES fronting_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- The users table stores information about users who can create systems and be friends with other users. Each user has a unique username and email, along with a hashed password for authentication.
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  pending_email VARCHAR(150) DEFAULT NULL,
  pending_email_token_hash VARCHAR(64) DEFAULT NULL,
  pending_email_expires_at TIMESTAMP NULL DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- The friends table manages friendships between users, allowing for different access levels (full or limited) to the friend's systems. Each friendship is unique based on the combination of system_id and friend_user_id.
CREATE TABLE friends (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_id INT NOT NULL,
  friend_user_id INT NOT NULL,
  access_level ENUM('full','limited') DEFAULT 'limited',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (system_id, friend_user_id),
  FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
  FOREIGN KEY (friend_user_id) REFERENCES users(id) ON DELETE CASCADE
);



ALTER TABLE users ADD COLUMN discord_id VARCHAR(20) DEFAULT NULL UNIQUE;
ALTER TABLE members ADD COLUMN proxy_prefix VARCHAR(10) DEFAULT NULL;