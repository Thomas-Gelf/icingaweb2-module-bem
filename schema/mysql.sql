CREATE TABLE bem_issue (
  ci_name_checksum VARBINARY(20) NOT NULL COMMENT 'sha1(host), sha1(host!service)',
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
  ts_first_notification BIGINT(20) NOT NULL,
  ts_last_notification BIGINT(20) NOT NULL,
  ts_next_notification BIGINT(20) NOT NULL,
  cnt_notifications BIGINT(20) NOT NULL,
  PRIMARY KEY (ci_name_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE bem_notification_log (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  bem_event_id BIGINT(20) UNSIGNED DEFAULT NULL, -- last_bem_event_id ?
  ci_name_checksum VARBINARY(20) NOT NULL, -- sha1("${host_name}!${object_name}")
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
  slot_values TEXT NOT NULL,
  ts_notification BIGINT(20) NOT NULL, -- unix timestamp with ms
  duration_ms INT(10) UNSIGNED NOT NULL,
  pid INT(10) UNSIGNED DEFAULT NULL,
  system_user VARCHAR(64) NOT NULL,
  system_host_name VARCHAR(255) NOT NULL,
  exit_code TINYINT UNSIGNED NOT NULL,
  command_line TEXT NOT NULL,
  output TEXT NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_search (ci_name_checksum),
  INDEX idx_name_search (host_name(64), object_name(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;
