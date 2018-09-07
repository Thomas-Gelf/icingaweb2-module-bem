ALTER TABLE bem_issue
  ADD COLUMN ci_name VARCHAR(255) NOT NULL AFTER cell_name;

ALTER TABLE bem_notification_log
  ADD COLUMN ci_name VARCHAR(255) NOT NULL AFTER ci_name_checksum;

ALTER TABLE bem_notification_log
  DROP COLUMN host_name,
  DROP COLUMN object_name;

ALTER TABLE bem_issue
  ADD INDEX idx_ci_name (ci_name (128));
