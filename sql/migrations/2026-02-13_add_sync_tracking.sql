-- Migration: Add sync tracking columns
-- Date: 2026-02-13
-- Purpose: Track which articles need to be synced to production

-- Add sync tracking columns to articles table
ALTER TABLE articles
ADD COLUMN sync_pending TINYINT(1) DEFAULT 1 COMMENT 'Whether article needs to be synced to production',
ADD COLUMN synced_at TIMESTAMP NULL COMMENT 'When article was last synced to production',
ADD INDEX idx_sync_pending (sync_pending);

-- Update existing articles to not require sync (they're already in production)
-- Only new articles processed locally will have sync_pending = 1
UPDATE articles SET sync_pending = 0, synced_at = NOW() WHERE sync_pending IS NULL;

-- Note: This migration should be run on LOCAL database only
-- Production database does NOT need these columns
