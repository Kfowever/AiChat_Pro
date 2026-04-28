# AiChat Pro

[English documentation: click here](README.md)

AiChat Pro 是一个基于 PHP 和 MySQL 的自托管多模型 AI 聊天平台。

> 重要提醒
>
> 支付系统目前尚未完全建设完成，请将其视为开发中的功能。
>
> 本项目的部分代码由 AI 生成或辅助生成。若用于生产环境，请重点复查安全、支付与业务逻辑。

项目当前包含：
- 用户注册、登录与资料管理
- 多轮聊天、流式回复支持
- 模型与提供商管理（OpenAI / Anthropic / DeepSeek）
- 文件上传支持
- 套餐、额度、订阅与交易记录管理
- 管理后台
- 首次部署安装向导

## 技术栈

- 后端：PHP 7.4+（轻量 MVC 风格）
- 数据库：MySQL 5.7+
- 前端：原生 HTML/CSS/JS + Tailwind CDN
- 部署：Nginx + PHP-FPM（支持 Docker）

## 目录结构

```text
.
+-- app/              # 控制器、服务、模型与核心类
+-- routes/           # API 路由
+-- config/           # 应用配置（数据库运行时配置会在安装后生成）
+-- sql/              # 数据库结构与初始化数据
+-- admin/            # 后台页面
+-- install/          # 安装页面与安装接口
+-- assets/           # 静态资源
+-- docker/           # Docker 与 Nginx 配置
+-- uploads/          # 运行期上传目录
`-- storage/          # 运行期缓存与限流存储
```

## 快速开始

1. 克隆仓库。
2. 复制环境变量模板。
3. 创建 MySQL 数据库。
4. 启动应用。
5. 打开安装页面并完成初始化。

```bash
cp .env.example .env
php -S 127.0.0.1:8080
```

Windows PowerShell：

```powershell
Copy-Item .env.example .env
php -S 127.0.0.1:8080
```

Docker（可选）：

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

安装入口：
- `http://127.0.0.1:8080/install`

## 环境变量

请在 `.env` 中配置以下内容：

- `JWT_SECRET`
- `DB_HOST`、`DB_PORT`、`DB_NAME`、`DB_USER`、`DB_PASS`
- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`
- `DEEPSEEK_API_KEY`
- `APP_URL`

参考文件：
- `.env.example`

## 安全基线

- 不要提交真实密钥（`.env`、API Key、数据库密码、支付私钥）
- 生产环境保持 `APP_DEBUG=false`
- 使用足够随机且强度高的 `JWT_SECRET`
- 一旦密钥暴露，立即轮换
- 公开部署前复查 AI 生成代码

## API 概览

主要路由定义在 `routes/api.php`：

- `/api/auth/*` 认证接口
- `/api/chats/*` 聊天与会话接口
- `/api/user/*` 用户资料、用量与交易接口
- `/api/admin/*` 管理后台接口
- `/api/install/*` 安装流程接口

## 开源信息

- 开源协议：MIT（[LICENSE](LICENSE)）
- 贡献指南：[CONTRIBUTING.md](CONTRIBUTING.md)
- 安全策略：[SECURITY.md](SECURITY.md)
