CREATE TABLE bem_issue (
  ci_name_checksum VARBINARY(20) NOT NULL COMMENT 'sha1(cell!ci_name)',
  cell_name VARCHAR(255) NOT NULL,
  ci_name VARCHAR(255) NOT NULL,
  host_name VARCHAR(255) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  severity ENUM(
    'UNKNOWN',
    'OK',
    'INFO',
    'WARNING',
    'MINOR',
    'MAJOR',
    'CRITICAL',
    'DOWN'
  ) NOT NULL,
  worst_severity ENUM(
    'UNKNOWN',
    'OK',
    'INFO',
    'WARNING',
    'MINOR',
    'MAJOR',
    'CRITICAL',
    'DOWN'
  ) NOT NULL,
  is_relevant ENUM('y', 'n') NOT NULL,
  slot_set_values TEXT NOT NULL,
  ts_first_notification BIGINT(20) DEFAULT NULL,
  ts_last_notification BIGINT(20) DEFAULT NULL,
  ts_next_notification BIGINT(20) NOT NULL,
  cnt_notifications BIGINT(20) NOT NULL,
  PRIMARY KEY (ci_name_checksum),
  INDEX idx_ci_name (ci_name (128))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE bem_notification_log (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  bem_event_id BIGINT(20) UNSIGNED DEFAULT NULL, -- last_bem_event_id ?
  ci_name_checksum VARBINARY(20) NOT NULL,
  ci_name VARCHAR(255) NOT NULL,
  severity ENUM(
    'UNKNOWN',
    'OK',
    'INFO',
    'WARNING',
    'MINOR',
    'MAJOR',
    'CRITICAL',
    'DOWN'
  ) NOT NULL,
  slot_set_values TEXT NOT NULL,
  ts_notification BIGINT(20) NOT NULL, -- unix timestamp with ms
  duration_ms INT(10) NOT NULL,
  pid INT(10) UNSIGNED DEFAULT NULL,
  system_user VARCHAR(64) NOT NULL,
  system_host_name VARCHAR(255) NOT NULL,
  exit_code TINYINT UNSIGNED NOT NULL,
  command_line TEXT NOT NULL,
  output TEXT NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_search (ci_name_checksum),
  INDEX idx_name_search (ci_name(128)),
  INDEX idx_sort (ts_notification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE bem_cell_stats (
  cell_name VARCHAR(64) NOT NULL,
  event_counter BIGINT(20) UNSIGNED NOT NULL,
  max_parallel_processes INT(10) UNSIGNED NOT NULL,
  running_processes INT(10) UNSIGNED NOT NULL,
  queue_size INT(10) UNSIGNED NOT NULL,
  ts_last_modification BIGINT(20) NOT NULL,
  ts_last_update BIGINT(20) NOT NULL,
  PRIMARY KEY (cell_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;
