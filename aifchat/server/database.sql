-- ============================================================
-- AIF Chat 数据库初始化脚本
-- 使用: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS aifchat DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aifchat;

-- ====================== 用户表 ======================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  nickname VARCHAR(100) DEFAULT '',
  email VARCHAR(255) DEFAULT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'bcrypt 哈希',
  role VARCHAR(50) DEFAULT 'user' COMMENT 'user / admin / vip',
  status VARCHAR(50) DEFAULT 'active' COMMENT 'active / disabled',
  avatar_url VARCHAR(500) DEFAULT NULL,
  points INT DEFAULT 0 COMMENT '站内积分',
  tokens_used BIGINT UNSIGNED DEFAULT 0,
  tokens_limit BIGINT UNSIGNED DEFAULT 1000000 COMMENT 'Token 总额度',
  tokens_remaining BIGINT UNSIGNED DEFAULT 1000000,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ====================== Refresh Token 表 ======================
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ====================== 邮箱验证码表 ======================
CREATE TABLE IF NOT EXISTS email_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  code VARCHAR(6) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email, code),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ====================== 验证码表（图形验证码） ======================
CREATE TABLE IF NOT EXISTS captcha (
  id VARCHAR(36) PRIMARY KEY,
  answer VARCHAR(10) NOT NULL COMMENT '验证码答案',
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ====================== 插入默认管理员 ======================
-- 密码: admin123 (请立即修改)
-- INSERT INTO users (username, nickname, email, password, role, tokens_limit, tokens_remaining)
-- VALUES ('admin', '管理员', 'admin@example.com', '$2y$10$...', 'admin', 999999999, 999999999);

-- ====================== API 调用日志表 ======================
CREATE TABLE IF NOT EXISTS api_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT 0,
  action VARCHAR(50) NOT NULL,
  method VARCHAR(10) NOT NULL DEFAULT 'POST',
  ip VARCHAR(45) DEFAULT '',
  status_code SMALLINT DEFAULT 200,
  response_time_ms INT DEFAULT 0 COMMENT '响应耗时（毫秒）',
  tokens_used INT DEFAULT 0 COMMENT '该请求消耗的 Token',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at),
  INDEX idx_ip (ip)
) ENGINE=InnoDB;

-- ====================== 用户反馈表 ======================
CREATE TABLE IF NOT EXISTS feedbacks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  content TEXT NOT NULL COMMENT '反馈内容',
  contact VARCHAR(255) DEFAULT NULL COMMENT '联系方式',
  category VARCHAR(50) DEFAULT 'other' COMMENT 'bug / feature / other',
  status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending / read / replied',
  reply TEXT DEFAULT NULL COMMENT '管理员回复',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ====================== Token 充值记录表 ======================
CREATE TABLE IF NOT EXISTS token_purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount BIGINT UNSIGNED NOT NULL COMMENT '充值的 Token 数量',
  cost_points INT DEFAULT 0 COMMENT '消耗的积分',
  method VARCHAR(50) DEFAULT 'points' COMMENT 'points / admin / payment',
  admin_id INT UNSIGNED DEFAULT NULL COMMENT '操作管理员（管理员充值）',
  note VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;
