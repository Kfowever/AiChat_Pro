<?php

return [
    'name' => getenv('APP_NAME') ?: 'AiChat Pro',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'env' => getenv('APP_ENV') ?: 'production',
    'base_path' => '',
    'jwt_secret' => getenv('JWT_SECRET') ?: '',
    'jwt_expire' => 86400 * 7,
    'version' => '1.0.0',
    'cors' => [
        'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('APP_ALLOWED_ORIGINS') ?: 'http://localhost,http://127.0.0.1,http://localhost:8080'))),
    ],
    'upload' => [
        'max_size' => (int)(getenv('UPLOAD_MAX_SIZE') ?: 10485760),
        'allowed_image_types' => explode(',', getenv('UPLOAD_ALLOWED_IMAGE_TYPES') ?: 'jpg,jpeg,png,gif,webp'),
        'allowed_file_types' => explode(',', getenv('UPLOAD_ALLOWED_FILE_TYPES') ?: 'pdf,txt,md,csv,json,doc,docx'),
    ],
    'chat' => [
        'max_message_chars' => (int)(getenv('CHAT_MAX_MESSAGE_CHARS') ?: 20000),
        'attachment_preview_chars' => (int)(getenv('CHAT_ATTACHMENT_PREVIEW_CHARS') ?: 12000),
    ],
    'rate_limit' => [
        'api' => ['max' => (int)(getenv('RATE_LIMIT_API_MAX') ?: 120), 'window' => 60],
        'auth' => ['max' => (int)(getenv('RATE_LIMIT_AUTH_MAX') ?: 10), 'window' => 600],
        'chat' => ['max' => (int)(getenv('RATE_LIMIT_CHAT_MAX') ?: 30), 'window' => 60],
        'upload' => ['max' => (int)(getenv('RATE_LIMIT_UPLOAD_MAX') ?: 10), 'window' => 60],
        'admin' => ['max' => (int)(getenv('RATE_LIMIT_ADMIN_MAX') ?: 8), 'window' => 600],
    ],
];
