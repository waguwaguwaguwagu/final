CREATE DATABASE IF NOT EXISTS school_checkin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_checkin;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
  department VARCHAR(80) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  kind ENUM('classroom','lab','office','facility','passage') NOT NULL DEFAULT 'classroom',
  x FLOAT NOT NULL,
  y FLOAT NOT NULL,
  w FLOAT NOT NULL,
  h FLOAT NOT NULL,
  checkable TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checkins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  checked_in_at DATETIME NOT NULL,
  checked_out_at DATETIME NULL,
  auto_closed TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_user_open (user_id, checked_out_at),
  INDEX idx_room_open (room_id, checked_out_at),
  INDEX idx_in (checked_in_at),
  CONSTRAINT fk_ci_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ci_room FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  att_date DATE NOT NULL,
  time_in DATETIME NOT NULL,
  time_out DATETIME NULL,
  status ENUM('present','late') NOT NULL DEFAULT 'present',
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_user_date (user_id, att_date),
  INDEX idx_date (att_date),
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_changes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  from_room_id INT UNSIGNED NULL,
  to_room_id INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  admin_note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  subject_id INT UNSIGNED NULL,
  session_id INT UNSIGNED NULL,
  change_date DATE NULL,
  INDEX idx_status (status),
  INDEX idx_rc_session_date (session_id, change_date),
  CONSTRAINT fk_rc_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_rc_from FOREIGN KEY (from_room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_rc_to FOREIGN KEY (to_room_id) REFERENCES rooms(id),
  CONSTRAINT fk_rc_admin FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(120) NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  section VARCHAR(40) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sub_teacher (teacher_id),
  CONSTRAINT fk_sub_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subject_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  part TINYINT UNSIGNED NOT NULL DEFAULT 1,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room_id INT UNSIGNED NULL,
  INDEX idx_ss_day (day_of_week),
  INDEX idx_ss_room_day (room_id, day_of_week),
  CONSTRAINT fk_ss_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedule_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  old_day TINYINT UNSIGNED NOT NULL,
  old_start TIME NOT NULL,
  old_end TIME NOT NULL,
  old_room_id INT UNSIGNED NULL,
  new_day TINYINT UNSIGNED NOT NULL,
  new_start TIME NOT NULL,
  new_end TIME NOT NULL,
  new_room_id INT UNSIGNED NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  admin_note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_sr_status (status),
  CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_sr_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_session FOREIGN KEY (session_id) REFERENCES subject_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_admin FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS defect_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  category ENUM('facility','equipment') NOT NULL DEFAULT 'equipment',
  item VARCHAR(120) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  severity ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','dismissed','cancelled') NOT NULL DEFAULT 'open',
  reported_at DATETIME NOT NULL,
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  admin_note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_def_status (status),
  INDEX idx_def_room (room_id, status),
  INDEX idx_def_user (user_id),
  CONSTRAINT fk_def_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_def_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_def_admin FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rooms (name, kind, x, y, w, h, checkable) VALUES
('Library','facility',45,162,120,325,1),
('Room 110','classroom',40,517,50,65,1),
('Kitchen','facility',90,567,140,73,1),
('Chemistry','lab',235,562,135,75,1),
('Comlab','lab',372,557,133,70,1),
('Research','lab',508,557,34,67,1),
('Anatomy Room','lab',545,550,70,74,1),
('Room 102','classroom',255,192,55,100,1),
('Room 101','classroom',255,292,55,90,1),
('Room 103','classroom',255,382,55,100,1),
('Moot Court','facility',315,157,45,35,1),
('Registrar','office',360,157,55,30,1),
('Admission','office',415,157,60,30,1),
('Hallway','passage',450,187,70,20,0),
('Long Room','facility',390,202,55,250,1),
('Room 104','classroom',475,207,50,135,1),
('Room 105','classroom',475,342,50,100,1),
('Room 106','classroom',570,212,40,120,1),
('Room 107','classroom',570,332,40,145,1),
('Stairs A','passage',200,487,55,28,0),
('Stairs B','passage',500,157,75,27,0),
('Stairs C','passage',830,477,55,28,0),
('Guard House','facility',440,18,75,34,1),
('Court','facility',548,57,107,50,1),
('CR','facility',565,37,65,20,1),
('Clinic','facility',690,167,95,57,1),
('Annex','facility',697,250,31,45,1),
('PE Room','facility',753,247,122,48,1),
('Main Hall','facility',760,317,100,125,1),
('Room 109','classroom',860,547,50,83,1),
('Question Dissemination','office',850,632,80,75,1),
('Forensic Ballistic','lab',870,717,65,80,1);
