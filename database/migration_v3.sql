-- ==========================================================
-- Migration V3: Add questionnaire fields + overlay support
-- Run this after migration_v2.sql
-- ==========================================================

-- Add new columns to reports table for the revamped questionnaire
ALTER TABLE reports ADD COLUMN IF NOT EXISTS property_category VARCHAR(20) DEFAULT NULL AFTER city;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS property_subtype VARCHAR(40) DEFAULT NULL AFTER property_category;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS problem_areas VARCHAR(200) DEFAULT NULL AFTER property_subtype;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS other_problem_text VARCHAR(50) DEFAULT NULL AFTER problem_areas;

-- Add overlay image columns
ALTER TABLE reports ADD COLUMN IF NOT EXISTS overlay_path VARCHAR(500) DEFAULT NULL AFTER pdf_url;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS overlay_url VARCHAR(500) DEFAULT NULL AFTER overlay_path;

-- For MySQL versions that don't support IF NOT EXISTS on ALTER TABLE:
-- Run these instead if the above fail:
-- ALTER TABLE reports ADD COLUMN property_category VARCHAR(20) DEFAULT NULL;
-- ALTER TABLE reports ADD COLUMN property_subtype VARCHAR(40) DEFAULT NULL;
-- ALTER TABLE reports ADD COLUMN problem_areas VARCHAR(200) DEFAULT NULL;
-- ALTER TABLE reports ADD COLUMN other_problem_text VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE reports ADD COLUMN overlay_path VARCHAR(500) DEFAULT NULL;
-- ALTER TABLE reports ADD COLUMN overlay_url VARCHAR(500) DEFAULT NULL;
