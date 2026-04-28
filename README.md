# AiChat Pro

一个基于 PHP 的多模型 AI 聊天系统，包含用户端聊天、套餐与额度、支付回调、后台管理、安装向导与基础安全策略。

## 功能概览

- 用户注册、登录、JWT 鉴权、个人资料管理
- 多轮聊天、流式回复、重试与回滚、历史导出
- 模型管理（OpenAI / Anthropic / DeepSeek）
- 文件上传与基础类型/大小校验
- 套餐、订阅、额度消耗与交易记录
- 管理后台（用户、模型、套餐、系统设置）
- 一键安装向导（环境检测、数据库初始化、管理员创建）

## 技术栈

- 后端：PHP 7.4+（无重框架，轻量 MVC 结构）
- 数据库：MySQL 5.7+
- 前端：原生 HTML/CSS/JS（含 Tailwind CDN）
- 部署：Nginx + PHP-FPM（支持 Docker）

## 目录结构

```text
.
├─ app/              # 核心业务代码（Controller/Service/Model/Core）
├─ routes/           # API 路由
├─ config/           # 配置文件（数据库配置会在安装后生成）
├─ sql/              # 数据库结构与初始化数据
├─ admin/            # 后台页面
├─ install/          # 安装向导
├─ assets/           # 静态资源
├─ docker/           # Docker 与 Nginx 配置
├─ uploads/          # 上传目录（运行期）
└─ storage/          # 运行期缓存/限流数据
```

## 本地快速开始

1. 克隆项目并进入目录
2. 复制环境变量模板
3. 创建数据库并确保账号可访问
4. 启动服务
5. 打开安装页面完成初始化

```bash
cp .env.example .env
```

```powershell
Copy-Item .env.example .env
```

```bash
# 方式一：PHP 内置服务
php -S 127.0.0.1:8080
```

```bash
# 方式二：Docker
docker compose -f docker/docker-compose.yml up -d --build
```

安装入口：

- `http://127.0.0.1:8080/install`

## 关键环境变量

请在 `.env` 中配置以下内容：

- `JWT_SECRET`
- `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS`
- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`
- `DEEPSEEK_API_KEY`
- `APP_URL`

## 安全说明

- `.env`、运行期上传目录、缓存目录已加入 `.gitignore`
- 不要提交真实 API Key、数据库密码、支付私钥
- 生产环境务必关闭调试：`APP_DEBUG=false`
- 建议将 `JWT_SECRET` 设为足够随机的长字符串

## API 概览

主要路由位于 `routes/api.php`，包括：

- `/api/auth/*` 用户认证
- `/api/chats/*` 聊天会话与消息
- `/api/user/*` 用户资料与账单信息
- `/api/admin/*` 管理后台接口
- `/api/install/*` 安装流程接口

## License

如需开源发布，建议补充 `LICENSE` 文件（例如 MIT）。
