CREATE TABLE bmc_issue (
  checksum VARBINARY(20) NOT NULL COMMENT 'sha1(host), sha1(host!service)',
  bmc_event_id BIGINT(20) UNSIGNED DEFAULT NULL,
  host VARCHAR(255) NOT NULL,
  service VARCHAR(255) DEFAULT NULL,
  last_priority ENUM(
    'PRIORITY_1', -- highest priority
    'PRIORITY_2',
    'PRIORITY_3',
    'PRIORITY_4',
    'PRIORITY_5', -- lowest priority
  ) NOT NULL,
  last_severity ENUM(
        -- also seen:
        'OK',
        'INFO',
        'WARNING',

        -- as of documentation:
        'MINOR',
        'MAJOR',
        'CRITICAL',
  ) NOT NULL,
  first_notification INT UNSIGNED NOT NULL,
  last_notification INT UNSIGNED NOT NULL,
  next_notification INT UNSIGNED NOT NULL,
  cnt_notifications INT UNSIGNED NOT NULL,
  last_exit_code TINYINT UNSIGNED NOT NULL,
  last_cmd_line TEXT NOT NULL,
  last_output TEXT NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
