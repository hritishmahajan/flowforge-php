-- FlowForge MySQL schema. Activate the MySQL backend with:
--   export FLOWFORGE_DSN="mysql:host=127.0.0.1;dbname=flowforge;charset=utf8mb4"
--   export FLOWFORGE_DB_USER=root FLOWFORGE_DB_PASS=secret
-- Then run this file:  mysql -u root -p flowforge < schema.sql

CREATE DATABASE IF NOT EXISTS flowforge CHARACTER SET utf8mb4;
USE flowforge;

CREATE TABLE IF NOT EXISTS workflows (
  id         CHAR(12)    NOT NULL PRIMARY KEY,
  payload    JSON        NOT NULL,           -- full workflow definition
  created_at DATETIME    NOT NULL,
  INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS runs (
  id         CHAR(12)    NOT NULL PRIMARY KEY,
  payload    JSON        NOT NULL,           -- input + action log + status
  created_at DATETIME    NOT NULL,
  INDEX idx_created (created_at)
);
