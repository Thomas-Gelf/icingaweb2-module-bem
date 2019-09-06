-- fix inconsistency between different old versions

ALTER TABLE bem_issue
  DROP INDEX IF EXISTS idx_ci_name;

ALTER TABLE bem_issue
  ADD INDEX idx_ci_name (ci_name (128));

ALTER TABLE bem_notification_log
  DROP INDEX IF EXISTS idx_search,
  DROP INDEX IF EXISTS idx_ci_name,
  DROP INDEX IF EXISTS idx_sort,
  DROP INDEX IF EXISTS idx_sort_ts,
  DROP INDEX IF EXISTS idx_name_search;

ALTER TABLE bem_notification_log
  ADD INDEX idx_search (ci_name_checksum),
  ADD INDEX idx_name_search (ci_name(128)),
  ADD INDEX idx_sort (ts_notification);


-- just changing the commment
ALTER TABLE bem_issue
  MODIFY ci_name_checksum varbinary(20) NOT NULL COMMENT 'sha1(cell!host!object)';


