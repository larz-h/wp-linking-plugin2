-- Manual database migration for Internal Linking Manager
-- Run this if you get database errors about missing 'post_title' column

-- Add post_title column to targets table if it doesn't exist
ALTER TABLE wp_ilm_targets
ADD COLUMN IF NOT EXISTS post_title varchar(255) DEFAULT NULL
AFTER url;

-- If you're not using 'wp_' as your WordPress table prefix,
-- replace 'wp_' with your actual prefix in the query above.
