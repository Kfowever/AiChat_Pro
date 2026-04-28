# AiChat Pro

[Chinese documentation: click here](README.zh-CN.md)

AiChat Pro is a self-hosted multi-model AI chat platform built with PHP and MySQL.

> Important
>
> The payment system is not fully implemented yet and should be treated as work in progress.
>
> Parts of this project were generated or assisted by AI. Please review security, payment, and business logic carefully before production use.

It includes:
- User auth and profile management
- Multi-turn chat sessions with streaming response support
- Model/provider management (OpenAI / Anthropic / DeepSeek)
- File upload support
- Plan, quota, subscription, and transaction management
- Admin dashboard
- Web installer for first-time setup

## Tech Stack

- Backend: PHP 7.4+ (lightweight MVC-style structure)
- Database: MySQL 5.7+
- Frontend: Vanilla HTML/CSS/JS + Tailwind CDN
- Deployment: Nginx + PHP-FPM (Docker supported)

## Project Structure

```text
.
+-- app/              # Controllers, services, models, and core classes
+-- routes/           # API routes
+-- config/           # App config (runtime DB config generated after install)
+-- sql/              # Database schema and seed data
+-- admin/            # Admin page
+-- install/          # Installer page and API
+-- assets/           # Static files
+-- docker/           # Docker and Nginx configs
+-- uploads/          # Runtime upload directory
`-- storage/          # Runtime cache and rate-limit storage
```

## Quick Start

1. Clone this repository.
2. Copy the environment template.
3. Create a MySQL database.
4. Start the app.
5. Open the installer and complete setup.

```bash
cp .env.example .env
php -S 127.0.0.1:8080
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
php -S 127.0.0.1:8080
```

Docker (optional):

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

Installer URL:
- `http://127.0.0.1:8080/install`

## Environment Variables

Set the following in `.env`:

- `JWT_SECRET`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`
- `DEEPSEEK_API_KEY`
- `APP_URL`

Reference file:
- `.env.example`

## Security Baseline

- Never commit real secrets (`.env`, API keys, DB passwords, payment private keys)
- Keep `APP_DEBUG=false` in production
- Use a strong random `JWT_SECRET`
- Rotate API keys immediately if exposed
- Review AI-generated code before public deployment

## API Overview

Core routes are defined in `routes/api.php`:

- `/api/auth/*` authentication
- `/api/chats/*` chat/session endpoints
- `/api/user/*` profile, usage, transactions
- `/api/admin/*` admin endpoints
- `/api/install/*` install endpoints

## Open Source

- License: MIT ([LICENSE](LICENSE))
- Contributing guide: [CONTRIBUTING.md](CONTRIBUTING.md)
- Security policy: [SECURITY.md](SECURITY.md)
