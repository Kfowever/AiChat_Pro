<?php

namespace App\Services;

use App\Core\Config;
use App\Models\Chat;
use App\Models\UploadedFile;

class FileService
{
    private UploadedFile $fileModel;

    public function __construct()
    {
        $this->fileModel = new UploadedFile();
    }

    public function upload(array $file, int $userId, ?int $chatId = null): array
    {
        $config = Config::getInstance();
        $maxSize = (int)$config->get('app.upload.max_size', 10485760);
        $allowedImageTypes = $config->get('app.upload.allowed_image_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $allowedFileTypes = $config->get('app.upload.allowed_file_types', ['pdf', 'txt', 'md', 'csv', 'json', 'doc', 'docx']);
        $allAllowed = array_merge($allowedImageTypes, $allowedFileTypes);

        $error = $this->validateUpload($file, $maxSize);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        if ($chatId !== null) {
            $chat = (new Chat())->findById($chatId);
            if (!$chat || (int)$chat['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Chat not found'];
            }
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allAllowed, true)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!$this->mimeAllowed($ext, $mime)) {
            return ['success' => false, 'message' => 'File content type does not match the extension'];
        }

        $isImage = in_array($ext, $allowedImageTypes, true);
        $subDir = $isImage ? 'files' : 'files';
        $uploadDir = dirname(__DIR__, 2) . '/uploads/' . $subDir;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to save file'];
        }
        @chmod($filepath, 0644);

        $originalName = $this->sanitizeOriginalName($file['name']);
        $publicPath = '/uploads/' . $subDir . '/' . $filename;
        $fileId = $this->fileModel->create([
            'user_id' => $userId,
            'chat_id' => $chatId,
            'filename' => $originalName,
            'filepath' => $publicPath,
            'filetype' => $ext,
            'filesize' => $file['size'],
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $fileId,
                'filename' => $originalName,
                'filepath' => $publicPath,
                'filetype' => $ext,
                'filesize' => $file['size'],
                'mime' => $mime,
                'is_image' => $isImage,
                'text_preview' => $this->textPreview($filepath, $ext),
            ]
        ];
    }

    public function uploadAvatar(array $file, int $userId): array
    {
        $maxSize = 2 * 1024 * 1024;
        $error = $this->validateUpload($file, $maxSize);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Only image files are allowed'];
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!$this->mimeAllowed($ext, $mime) || @getimagesize($file['tmp_name']) === false) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $userId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to save avatar'];
        }
        @chmod($filepath, 0644);

        $avatarPath = '/uploads/avatars/' . $filename;
        (new \App\Models\User())->updateAvatar($userId, $avatarPath);

        return [
            'success' => true,
            'data' => ['avatar' => $avatarPath]
        ];
    }

    private function validateUpload(array $file, int $maxSize): ?string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return 'Invalid upload payload';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload failed with error code: ' . $file['error'];
        }
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'Invalid uploaded file';
        }
        if ((int)$file['size'] <= 0) {
            return 'Uploaded file is empty';
        }
        if ((int)$file['size'] > $maxSize) {
            return 'File size exceeds limit';
        }
        return null;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    private function mimeAllowed(string $ext, string $mime): bool
    {
        $map = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain', 'application/octet-stream'],
            'md' => ['text/plain', 'text/markdown', 'application/octet-stream'],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream'],
            'json' => ['application/json', 'text/plain', 'application/octet-stream'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        ];

        return isset($map[$ext]) && in_array($mime, $map[$ext], true);
    }

    private function sanitizeOriginalName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $name);
        return mb_substr($name ?: 'file', 0, 180);
    }

    private function textPreview(string $path, string $ext): ?string
    {
        if (!in_array($ext, ['txt', 'md', 'csv', 'json'], true)) {
            return null;
        }

        $limit = (int) Config::getInstance()->get('app.chat.attachment_preview_chars', 12000);
        $content = @file_get_contents($path, false, null, 0, max(1, $limit * 4));
        if ($content === false) {
            return null;
        }

        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $content = @mb_convert_encoding($content, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5,ISO-8859-1');
        }

        return mb_substr($content, 0, $limit);
    }
}
