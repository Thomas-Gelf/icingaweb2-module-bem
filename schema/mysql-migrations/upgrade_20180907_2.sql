ALTER TABLE bem_cell_stats
  ADD COLUMN is_master ENUM('y', 'n') DEFAULT 'y' NOT NULL AFTER cell_name;
