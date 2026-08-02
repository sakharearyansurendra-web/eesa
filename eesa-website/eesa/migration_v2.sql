-- migration_v2.sql
-- Run this against the existing Aiven database via mysqlsh --file=...
-- Adds: multi-stage join verification, profile fields, team_members ->
-- user account linking, and the team-only communication channel.

ALTER TABLE users MODIFY status ENUM('pending','verifier1_approved','verifier2_approved','approved','rejected','suspended') NOT NULL DEFAULT 'pending';

ALTER TABLE users
  ADD COLUMN phone VARCHAR(20) DEFAULT NULL,
  ADD COLUMN address VARCHAR(255) DEFAULT NULL,
  ADD COLUMN bio VARCHAR(500) DEFAULT NULL,
  ADD COLUMN verifier1_by INT DEFAULT NULL,
  ADD COLUMN verifier1_at DATETIME DEFAULT NULL,
  ADD COLUMN verifier2_by INT DEFAULT NULL,
  ADD COLUMN verifier2_at DATETIME DEFAULT NULL,
  ADD FOREIGN KEY (verifier1_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD FOREIGN KEY (verifier2_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE team_members
  ADD COLUMN user_id INT DEFAULT NULL,
  ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE team_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
