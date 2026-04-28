<?php

namespace App\Core;

class Request
{
    private $params;
    private $body = null;
    private $query = null;
    private $files = [];

    public function __construct(array $params = [])
    {
        $this->params = $params;
        $this->files = $_FILES;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function input(string $key, $default = null)
    {
        $body = $this->body();
        return $body[$key] ?? $default;
    }

    public function body(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $this->body = json_decode($raw, true) ?? [];
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            $this->body = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            $this->body = json_decode($raw, true) ?? $_POST;
        }

        return $this->body;
    }

    public function query(string $key, $default = null)
    {
        if ($this->query === null) {
            $this->query = $_GET;
        }
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
