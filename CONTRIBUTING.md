# Contributing to AiChat Pro

Thanks for your interest in improving AiChat Pro.

## Ground Rules

- Keep pull requests focused and small when possible
- Do not commit secrets, credentials, or production data
- Follow existing code style and directory conventions
- Write clear commit messages

## Development Setup

1. Fork and clone the repository.
2. Copy `.env.example` to `.env`.
3. Prepare MySQL and update DB settings in `.env`.
4. Run the app locally.

```bash
php -S 127.0.0.1:8080
```

5. Complete installer at `http://127.0.0.1:8080/install`.

## Pull Request Checklist

- I have tested the changed behavior locally
- I did not include `.env` or sensitive config values
- I updated docs when behavior or setup changed
- I kept unrelated refactors out of this PR

## Branch Naming

Recommended pattern:
- `feature/<short-description>`
- `fix/<short-description>`
- `chore/<short-description>`

## Reporting Issues

- Use GitHub Issues for bugs and feature requests
- For security vulnerabilities, follow `SECURITY.md` and avoid public disclosure first
