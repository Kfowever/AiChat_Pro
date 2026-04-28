<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChatService;
use App\Services\FileService;
use App\Middleware\AuthMiddleware;

class ChatController
{
    private ChatService $chatService;
    private FileService $fileService;

    public function __construct()
    {
        $this->chatService = new ChatService();
        $this->fileService = new FileService();
    }

    public function list(Request $request): void
    {
        $user = AuthMiddleware::user();
        $result = $this->chatService->listChats($user['id']);
        Response::json($result);
    }

    public function create(Request $request): void
    {
        $user = AuthMiddleware::user();
        $defaultModel = (new \App\Models\SiteSetting())->get('default_model', 'gpt-3.5-turbo');
        $model = $request->input('model', $defaultModel ?: 'gpt-3.5-turbo');
        $result = $this->chatService->createChat($user['id'], $model);
        Response::json($result, $result['success'] ? 201 : 400);
    }

    public function get(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $result = $this->chatService->getChat($chatId, $user['id']);
        Response::json($result, $result['success'] ? 200 : 404);
    }

    public function delete(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $result = $this->chatService->deleteChat($chatId, $user['id']);
        Response::json($result);
    }

    public function update(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $result = $this->chatService->updateChat($chatId, $user['id'], $request->body());
        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function export(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $format = strtolower((string) $request->query('format', 'markdown'));
        $result = $this->chatService->exportChat($chatId, $user['id'], $format);
        Response::json($result, $result['success'] ? 200 : 404);
    }

    public function sendMessage(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $content = $request->input('content', '');
        $webSearch = (bool) $request->input('web_search', false);
        $deepThink = (bool) $request->input('deep_think', false);
        $maxChars = (int) Config::getInstance()->get('app.chat.max_message_chars', 20000);

        if (empty(trim($content))) {
            Response::error('Message content is required');
            return;
        }

        if (mb_strlen($content) > $maxChars) {
            Response::error('Message is too long. Please shorten it and try again.', 413);
            return;
        }

        $this->chatService->sendMessage($chatId, $user['id'], $content, $webSearch, $deepThink);
    }

    public function regenerate(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $webSearch = (bool) $request->input('web_search', false);
        $deepThink = (bool) $request->input('deep_think', false);

        $this->chatService->regenerateLastResponse($chatId, $user['id'], $webSearch, $deepThink);
    }

    public function regenerateFromMessage(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $messageId = (int) $request->param('messageId');
        $webSearch = (bool) $request->input('web_search', false);
        $deepThink = (bool) $request->input('deep_think', false);

        $this->chatService->regenerateFromUserMessage($chatId, $user['id'], $messageId, $webSearch, $deepThink);
    }

    public function rollback(Request $request): void
    {
        $user = AuthMiddleware::user();
        $chatId = (int) $request->param('id');
        $messageId = (int) $request->param('messageId');

        $result = $this->chatService->rollbackToMessage($chatId, $user['id'], $messageId);
        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function upload(Request $request): void
    {
        $user = AuthMiddleware::user();
        $file = $request->file('file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('No file uploaded');
            return;
        }

        $chatId = $request->input('chat_id') ? (int) $request->input('chat_id') : null;
        $result = $this->fileService->upload($file, $user['id'], $chatId);
        Response::json($result, $result['success'] ? 201 : 400);
    }

    public function getModels(Request $request): void
    {
        $models = (new \App\Models\ModelConfig())->findAll();
        Response::success($models);
    }
}
