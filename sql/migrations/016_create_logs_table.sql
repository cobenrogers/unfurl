-- Migration: Update logs table for enhanced logging requirements
-- Purpose: Add log_type, ip_address, and user_agent columns
-- Date: 2026-02-07

-- Step 1: Rename category to log_type and update ENUM values
ALTER TABLE logs
    CHANGE COLUMN `category` `log_type` ENUM('processing', 'user', 'feed', 'api', 'system') NOT NULL DEFAULT 'system';

-- Step 2: Rename level to log_level (keep same ENUM values)
ALTER TABLE logs
    CHANGE COLUMN `level` `log_level` ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL;

-- Step 3: Add new columns for IP and user agent
ALTER TABLE logs
    ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `context`,
    ADD COLUMN `user_agent` VARCHAR(255) NULL AFTER `ip_address`;

-- Step 4: Modify message to VARCHAR(500) for consistency
ALTER TABLE logs
    MODIFY COLUMN `message` VARCHAR(500) NOT NULL;

-- Step 5: Update indexes
ALTER TABLE logs
    DROP INDEX `idx_level`,
    DROP INDEX `idx_category`,
    DROP INDEX `idx_level_category`,
    ADD INDEX `idx_log_type` (`log_type`),
    ADD INDEX `idx_log_level` (`log_level`);
