-- AiChat Pro Database Schema
-- MySQL 5.7+
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table: plans
-- ----------------------------
CREATE TABLE IF NOT EXISTS `plans` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL COMMENT '套餐标识名',
    `display_name` VARCHAR(50) NOT NULL COMMENT '套餐显示名',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '月价格(美元)',
    `quota_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '每次发放额度(美元)',
    `quota_period` ENUM('monthly','weekly') DEFAULT 'monthly' COMMENT '额度发放周期',
    `features` JSON DEFAULT NULL COMMENT '套餐特性列表',
    `sort_order` TINYINT UNSIGNED DEFAULT 0 COMMENT '排序',
    `status` ENUM('active','inactive') DEFAULT 'active' COMMENT '状态',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐表';

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    `email` VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `avatar` VARCHAR(500) DEFAULT NULL COMMENT '头像路径',
    `plan_id` TINYINT UNSIGNED DEFAULT 1 COMMENT '当前套餐ID',
    `quota_balance` DECIMAL(14,6) DEFAULT 0.000000 COMMENT '剩余额度(美元)',
    `total_used` DECIMAL(14,6) DEFAULT 0.000000 COMMENT '累计使用额度(美元)',
    `status` ENUM('active','banned') DEFAULT 'active' COMMENT '账户状态',
    `auto_renew` TINYINT(1) DEFAULT 0 COMMENT '自动续费',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_plan` (`plan_id`),
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- Table: subscriptions
-- ----------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `plan_id` TINYINT UNSIGNED NOT NULL COMMENT '套餐ID',
    `status` ENUM('active','cancelled','expired') DEFAULT 'active' COMMENT '订阅状态',
    `auto_renew` TINYINT(1) DEFAULT 0 COMMENT '自动续费',
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '开始时间',
    `expires_at` DATETIME DEFAULT NULL COMMENT '到期时间',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订阅表';

-- ----------------------------
-- Table: transactions
-- ----------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` ENUM('purchase','usage','refund','quota_grant') NOT NULL COMMENT '交易类型',
    `amount` DECIMAL(14,6) NOT NULL COMMENT '金额(美元)',
    `description` VARCHAR(500) DEFAULT NULL COMMENT '描述',
    `payment_method` ENUM('alipay','wechat','manual','system') DEFAULT NULL COMMENT '支付方式',
    `payment_status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending' COMMENT '支付状态',
    `model_id` VARCHAR(100) DEFAULT NULL COMMENT '消费模型',
    `input_tokens` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '输入token数',
    `output_tokens` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '输出token数',
    `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总token数',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_model` (`model_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='交易记录表';

-- ----------------------------
-- Table: model_configs
-- ----------------------------
CREATE TABLE IF NOT EXISTS `model_configs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '模型标识',
    `display_name` VARCHAR(100) NOT NULL COMMENT '模型显示名',
    `provider` VARCHAR(50) NOT NULL COMMENT '提供商(openai/anthropic/deepseek等)',
    `model_id` VARCHAR(100) NOT NULL COMMENT 'API调用用的模型ID',
    `api_key` VARCHAR(500) DEFAULT NULL COMMENT 'API密钥',
    `api_base_url` VARCHAR(500) DEFAULT NULL COMMENT 'API基础URL(为空则用默认)',
    `pricing_input` DECIMAL(10,6) DEFAULT 0.000000 COMMENT '输入价格(美元/1K tokens)',
    `pricing_output` DECIMAL(10,6) DEFAULT 0.000000 COMMENT '输出价格(美元/1K tokens)',
    `max_context_tokens` INT UNSIGNED DEFAULT 4096 COMMENT '最大上下文token数',
    `max_output_tokens` INT UNSIGNED DEFAULT 2048 COMMENT '最大输出token数',
    `default_temperature` DECIMAL(3,2) DEFAULT 0.70 COMMENT '默认温度',
    `system_prompt` TEXT DEFAULT NULL COMMENT '系统提示词',
    `description` TEXT DEFAULT NULL COMMENT '模型描述',
    `capabilities` JSON DEFAULT NULL COMMENT '能力标签(vision,function_calling,streaming等)',
    `daily_limit` INT UNSIGNED DEFAULT 0 COMMENT '每日调用限制(0=不限)',
    `status` ENUM('active','inactive') DEFAULT 'active' COMMENT '状态',
    `sort_order` INT UNSIGNED DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模型配置表';

-- ----------------------------
-- Table: plan_models (套餐-模型关联表)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `plan_models` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id` TINYINT UNSIGNED NOT NULL COMMENT '套餐ID',
    `model_id` INT UNSIGNED NOT NULL COMMENT '模型配置ID',
    UNIQUE KEY `uk_plan_model` (`plan_id`, `model_id`),
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`model_id`) REFERENCES `model_configs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐-模型关联表';

-- ----------------------------
-- Table: chats
-- ----------------------------
CREATE TABLE IF NOT EXISTS `chats` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `title` VARCHAR(200) DEFAULT '新对话' COMMENT '聊天标题',
    `model` VARCHAR(100) DEFAULT 'gpt-3.5-turbo' COMMENT '使用的模型',
    `last_message` TEXT DEFAULT NULL COMMENT '最后一条消息预览',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_user` (`user_id`),
    INDEX `idx_updated` (`updated_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天会话表';

-- ----------------------------
-- Table: messages
-- ----------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `chat_id` BIGINT UNSIGNED NOT NULL COMMENT '聊天会话ID',
    `role` ENUM('user','assistant','system') NOT NULL COMMENT '角色',
    `content` LONGTEXT NOT NULL COMMENT '消息内容',
    `tokens` INT UNSIGNED DEFAULT 0 COMMENT '消耗token数',
    `model` VARCHAR(100) DEFAULT NULL COMMENT '使用的模型',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_chat` (`chat_id`),
    FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息表';

-- ----------------------------
-- Table: uploaded_files
-- ----------------------------
CREATE TABLE IF NOT EXISTS `uploaded_files` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `chat_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联聊天ID',
    `filename` VARCHAR(255) NOT NULL COMMENT '原始文件名',
    `filepath` VARCHAR(500) NOT NULL COMMENT '存储路径',
    `filetype` VARCHAR(50) NOT NULL COMMENT '文件类型',
    `filesize` INT UNSIGNED NOT NULL COMMENT '文件大小(字节)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='上传文件表';

-- ----------------------------
-- Table: admin_users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '管理员用户名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- ----------------------------
-- Table: site_settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE COMMENT '设置键',
    `setting_value` TEXT DEFAULT NULL COMMENT '设置值',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点设置表';

-- ----------------------------
-- Table: install_lock
-- ----------------------------
CREATE TABLE IF NOT EXISTS `install_lock` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `installed` TINYINT(1) DEFAULT 1 COMMENT '是否已安装',
    `installed_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '安装时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安装锁表';

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- Initial Data: Plans
-- ----------------------------
INSERT INTO `plans` (`id`, `name`, `display_name`, `price`, `quota_amount`, `quota_period`, `features`, `sort_order`, `status`) VALUES
(1, 'Free', '免费版', 0.00, 1.00, 'monthly', '["每月 $1.00 额度","基础模型访问","标准响应速度"]', 1, 'active'),
(2, 'Lite', 'Lite 版', 5.00, 8.00, 'monthly', '["每月 $8.00 额度","全部模型访问","优先响应速度","文件上传支持"]', 2, 'active'),
(3, 'Pro', 'Pro 版', 20.00, 40.00, 'monthly', '["每月 $40.00 额度","全部模型访问","最高优先响应","文件上传支持","深度思考模式","联网搜索"]', 3, 'active');

-- ----------------------------
-- Initial Data: Model Configs
-- ----------------------------
INSERT INTO `model_configs` (`name`, `display_name`, `provider`, `model_id`, `pricing_input`, `pricing_output`, `max_context_tokens`, `max_output_tokens`, `default_temperature`, `system_prompt`, `description`, `capabilities`, `status`, `sort_order`) VALUES
('gpt-3.5-turbo', 'GPT-3.5 Turbo', 'openai', 'gpt-3.5-turbo', 0.000500, 0.001500, 16384, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'OpenAI GPT-3.5 Turbo 快速响应模型', '["streaming"]', 'active', 1),
('gpt-4', 'GPT-4', 'openai', 'gpt-4', 0.030000, 0.060000, 8192, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'OpenAI GPT-4 高质量推理模型', '["streaming","function_calling"]', 'active', 2),
('gpt-4o', 'GPT-4o', 'openai', 'gpt-4o', 0.005000, 0.015000, 128000, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'OpenAI GPT-4o 多模态旗舰模型', '["streaming","function_calling","vision"]', 'active', 3),
('gpt-4o-mini', 'GPT-4o Mini', 'openai', 'gpt-4o-mini', 0.000150, 0.000600, 128000, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'OpenAI GPT-4o Mini 高性价比模型', '["streaming","function_calling","vision"]', 'active', 4),
('claude-3-haiku', 'Claude 3 Haiku', 'anthropic', 'claude-3-haiku-20240307', 0.000250, 0.001250, 200000, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'Anthropic Claude 3 Haiku 快速模型', '["streaming","vision"]', 'active', 5),
('claude-3-sonnet', 'Claude 3 Sonnet', 'anthropic', 'claude-3-sonnet-20240229', 0.003000, 0.015000, 200000, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'Anthropic Claude 3 Sonnet 均衡模型', '["streaming","function_calling","vision"]', 'active', 6),
('claude-3-opus', 'Claude 3 Opus', 'anthropic', 'claude-3-opus-20240229', 0.015000, 0.075000, 200000, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'Anthropic Claude 3 Opus 旗舰模型', '["streaming","function_calling","vision"]', 'active', 7),
('deepseek-chat', 'DeepSeek Chat', 'deepseek', 'deepseek-chat', 0.000140, 0.000280, 32768, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'DeepSeek Chat 通用对话模型', '["streaming"]', 'active', 8),
('deepseek-reasoner', 'DeepSeek Reasoner', 'deepseek', 'deepseek-reasoner', 0.000550, 0.002190, 65536, 4096, 0.70, 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.', 'DeepSeek Reasoner 深度推理模型', '["streaming","deep_thinking"]', 'active', 9);

-- ----------------------------
-- Initial Data: Plan-Model Associations
-- Free: gpt-3.5-turbo, gpt-4o-mini, deepseek-chat
-- Lite: all models
-- Pro: all models
-- ----------------------------
INSERT INTO `plan_models` (`plan_id`, `model_id`) VALUES
(1, 1), (1, 4), (1, 8),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9),
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 7), (3, 8), (3, 9);

-- ----------------------------
-- Initial Data: Site Settings
-- ----------------------------
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'AiChat Pro'),
('site_description', '现代化 AI 聊天平台'),
('site_logo', ''),
('site_url', 'http://localhost'),
('site_announcement', ''),
('allow_registration', '1'),
('maintenance_mode', '0'),
('default_model', 'gpt-3.5-turbo'),
('max_chat_history', '100'),
('alipay_enabled', '0'),
('wechat_enabled', '0'),
('contact_email', ''),
('icp_number', '');
