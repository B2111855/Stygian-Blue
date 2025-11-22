-- Migration: add additional indexes to nhat_ky_he_thong to optimize common filters
-- Run this after deploying code changes. Idempotent: uses IF NOT EXISTS (MySQL 8+ required)

ALTER TABLE `nhat_ky_he_thong`
  ADD INDEX `idx_nkht_role` (`VAI_TRO`),
  ADD INDEX `idx_nkht_subject` (`DOI_TUONG`),
  ADD INDEX `idx_nkht_ip` (`IP`),
  ADD INDEX `idx_nkht_created_at` (`CREATED_AT`);

-- Optional wide covering index (commented out by default). Enable only if query profile justifies.
-- CREATE INDEX `idx_nkht_search_combo` ON `nhat_ky_he_thong` (`ACTOR_ID`, `HANH_DONG`, `DOI_TUONG`, `IP`, `CREATED_AT`);

-- Verification examples:
-- EXPLAIN SELECT COUNT(*) FROM nhat_ky_he_thong WHERE CREATED_AT >= '2025-11-20 00:00:00';
-- EXPLAIN SELECT * FROM nhat_ky_he_thong WHERE VAI_TRO = 'admin' ORDER BY CREATED_AT DESC LIMIT 25;
-- EXPLAIN SELECT * FROM nhat_ky_he_thong WHERE DOI_TUONG = 'auth' AND HANH_DONG = 'LOGIN_SUCCESS' ORDER BY CREATED_AT DESC LIMIT 25;
