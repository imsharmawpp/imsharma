-- ==========================================================
-- Migration V4: Interactive plan markers
-- Adds a `markers` column to reports to store the user-dropped
-- element markers (entrance, kitchen, owner cabin, etc.) as JSON.
-- Run this after migration_v3.sql
-- ==========================================================

-- markers stores a JSON array: [{ "type": "entrance", "label": "Main Entrance", "nx": 0.5, "ny": 0.1 }, ...]
ALTER TABLE reports ADD COLUMN IF NOT EXISTS markers TEXT DEFAULT NULL AFTER other_problem_text;

-- For MySQL versions that don't support IF NOT EXISTS on ALTER TABLE,
-- run this instead (ignore the "duplicate column" error if it already exists):
-- ALTER TABLE reports ADD COLUMN markers TEXT DEFAULT NULL;
