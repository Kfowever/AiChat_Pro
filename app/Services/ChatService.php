<?php

namespace App\Services;

use App\Core\Response;
use App\Models\Chat;
use App\Models\Message;
use App\Models\ModelConfig;
use App\Models\PlanModel;
use App\Models\SiteSetting;
use App\Models\User;

class ChatService
{
    private $chatModel;
    private $messageModel;
    private $modelModel;
    private $quotaService;

    public function __construct()
    {
        $this->chatModel = new Chat();
        $this->messageModel = new Message();
        $this->modelModel = new ModelConfig();
        $this->quotaService = new QuotaService();
    }

    public function listChats(int $userId): array
    {
        $chats = $this->chatModel->findByUser($userId);
        return ['success' => true, 'data' => $chats];
    }

    public function createChat(int $userId, string $model = 'gpt-3.5-turbo'): array
    {
        $modelConfig = $this->modelModel->findByModelId($model);
        if (!$modelConfig || $modelConfig['status'] !== 'active') {
            return ['success' => false, 'message' => 'Invalid model'];
        }

        $user = (new User())->findById($userId);
        if (!$user || !(new PlanModel())->canUseModel((int)$user['plan_id'], (int)$modelConfig['id'])) {
            return ['success' => false, 'message' => 'Your plan does not have access to this model.'];
        }

        $chatId = $this->chatModel->create($userId, $model);
        return ['success' => true, 'data' => $this->chatModel->findById($chatId)];
    }

    public function getChat(int $chatId, int $userId): array
    {
        $chat = $this->chatModel->getWithMessages($chatId, $userId);
        if (!$chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }
        return ['success' => true, 'data' => $chat];
    }

    public function updateChat(int $chatId, int $userId, array $data): array
    {
        $chat = $this->chatModel->findById($chatId);
        if (!$chat || (int)$chat['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        $update = [];
        if (isset($data['title'])) {
            $title = trim((string)$data['title']);
            if ($title === '' || mb_strlen($title) > 100) {
                return ['success' => false, 'message' => 'Title must be between 1 and 100 characters'];
            }
            $update['title'] = $title;
        }

        if (isset($data['model']) && $data['model'] !== $chat['model']) {
            $modelConfig = $this->modelModel->findByModelId((string)$data['model']);
            $user = (new User())->findById($userId);
            if (!$modelConfig || $modelConfig['status'] !== 'active') {
                return ['success' => false, 'message' => 'Invalid model'];
            }
            if (!$user || !(new PlanModel())->canUseModel((int)$user['plan_id'], (int)$modelConfig['id'])) {
                return ['success' => false, 'message' => 'Your plan does not have access to this model.'];
            }
            $update['model'] = $modelConfig['model_id'];
        }

        if (empty($update)) {
            return ['success' => true, 'data' => $chat];
        }

        $this->chatModel->update($chatId, $update);
        return ['success' => true, 'data' => $this->chatModel->findById($chatId)];
    }

    public function deleteChat(int $chatId, int $userId): array
    {
        $chat = $this->chatModel->findById($chatId);
        if (!$chat || (int)$chat['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Chat not found'];
        }
        $this->chatModel->delete($chatId, $userId);
        return ['success' => true, 'message' => 'Chat deleted'];
    }

    public function exportChat(int $chatId, int $userId, string $format = 'markdown'): array
    {
        $chat = $this->chatModel->getWithMessages($chatId, $userId);
        if (!$chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        $format = $format === 'json' ? 'json' : 'markdown';
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]+/u', '_', $chat['title'] ?: 'chat');
        $filename = $safeTitle . '_' . date('Ymd_His') . '.' . ($format === 'json' ? 'json' : 'md');

        if ($format === 'json') {
            $content = json_encode($chat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $content = "# " . ($chat['title'] ?: 'Untitled chat') . "\n\n";
            $content .= "- Model: " . ($chat['model'] ?: '-') . "\n";
            $content .= "- Exported at: " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($chat['messages'] as $message) {
                $role = ucfirst((string)$message['role']);
                $content .= "## {$role}\n\n" . rtrim((string)$message['content']) . "\n\n";
            }
        }

        return [
            'success' => true,
            'data' => [
                'format' => $format,
                'filename' => $filename,
                'content' => $content,
            ],
        ];
    }

    public function sendMessage(int $chatId, int $userId, string $content, bool $webSearch = false, bool $deepThink = false): void
    {
        $context = $this->loadChatContext($chatId, $userId);
        if (!$context) {
            return;
        }

        $userMessageId = $this->messageModel->create(
            $chatId,
            'user',
            $content,
            $this->estimateTokens($content),
            $context['chat']['model']
        );
        $this->chatModel->update($chatId, [
            'last_message' => mb_substr($content, 0, 100),
        ]);

        if ($this->isDefaultChatTitle((string)($context['chat']['title'] ?? ''))) {
            $title = mb_substr($content, 0, 30) . (mb_strlen($content) > 30 ? '...' : '');
            $this->chatModel->update($chatId, ['title' => $title]);
        }

        $context['chat'] = $this->chatModel->findById($chatId);
        $this->streamAssistantResponse(
            $chatId,
            $userId,
            $context['chat'],
            $context['model'],
            $webSearch,
            $deepThink,
            $userMessageId,
            null
        );
    }

    public function regenerateLastResponse(int $chatId, int $userId, bool $webSearch = false, bool $deepThink = false): void
    {
        $lastUserMessage = $this->messageModel->findLastUserMessage($chatId);
        if (!$lastUserMessage) {
            Response::error('No user message to regenerate from', 400);
            return;
        }

        $this->regenerateFromUserMessage($chatId, $userId, (int)$lastUserMessage['id'], $webSearch, $deepThink);
    }

    public function regenerateFromUserMessage(int $chatId, int $userId, int $userMessageId, bool $webSearch = false, bool $deepThink = false): void
    {
        $context = $this->loadChatContext($chatId, $userId);
        if (!$context) {
            return;
        }

        $targetUserMessage = $this->messageModel->findInChat($chatId, $userMessageId);
        if (!$targetUserMessage || ($targetUserMessage['role'] ?? '') !== 'user') {
            Response::error('Target user message not found', 404);
            return;
        }

        $context['chat'] = $this->chatModel->findById($chatId);
        $this->streamAssistantResponse(
            $chatId,
            $userId,
            $context['chat'],
            $context['model'],
            $webSearch,
            $deepThink,
            $userMessageId,
            $userMessageId
        );
    }

    public function rollbackToMessage(int $chatId, int $userId, int $userMessageId): array
    {
        $chat = $this->chatModel->findById($chatId);
        if (!$chat || (int)$chat['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        $target = $this->messageModel->findInChat($chatId, $userMessageId);
        if (!$target || ($target['role'] ?? '') !== 'user') {
            return ['success' => false, 'message' => 'Target message not found'];
        }

        $this->messageModel->deleteFromId($chatId, $userMessageId);

        $latest = $this->messageModel->findLastUserMessage($chatId);
        $this->chatModel->update($chatId, [
            'last_message' => $latest ? mb_substr((string)$latest['content'], 0, 100) : null,
        ]);

        return [
            'success' => true,
            'message' => 'Conversation rolled back',
            'data' => [
                'draft' => (string)$target['content'],
                'rolled_back_to_message_id' => $userMessageId,
            ],
        ];
    }

    private function loadChatContext(int $chatId, int $userId): ?array
    {
        $chat = $this->chatModel->findById($chatId);
        if (!$chat || (int)$chat['user_id'] !== $userId) {
            Response::error('Chat not found', 404);
            return null;
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            Response::error('User not found', 404);
            return null;
        }

        if ((float)$user['quota_balance'] <= 0) {
            Response::error('Insufficient quota, please upgrade your plan', 402);
            return null;
        }

        $modelConfig = $this->modelModel->findByModelId($chat['model']);
        if (!$modelConfig || $modelConfig['status'] !== 'active') {
            Response::error('Model is not available', 400);
            return null;
        }

        if (!(new PlanModel())->canUseModel((int)$user['plan_id'], (int)$modelConfig['id'])) {
            Response::error('Your plan does not have access to this model. Please upgrade.', 403);
            return null;
        }

        $dailyLimit = (int)($modelConfig['daily_limit'] ?? 0);
        if ($dailyLimit > 0 && $this->messageModel->countModelUsageToday($userId, $chat['model']) >= $dailyLimit) {
            Response::error('Daily model usage limit reached. Please try again tomorrow.', 429);
            return null;
        }

        return ['chat' => $chat, 'user' => $user, 'model' => $modelConfig];
    }

    private function streamAssistantResponse(
        int $chatId,
        int $userId,
        array $chat,
        array $modelConfig,
        bool $webSearch,
        bool $deepThink,
        int $targetUserMessageId,
        ?int $historyUntilUserMessageId
    ): void {
        $messages = $this->buildMessages($chatId, $modelConfig, $webSearch, $deepThink, $historyUntilUserMessageId);
        $temperature = $deepThink ? 0.3 : (float)($modelConfig['default_temperature'] ?? 0.7);
        $estimatedMaxCost = $this->quotaService->calculateCost(
            $chat['model'],
            $this->estimateTokens(implode(' ', array_column($messages, 'content'))),
            (int)($modelConfig['max_output_tokens'] ?? 2048)
        );
        $user = (new User())->findById($userId);
        if ($user && $estimatedMaxCost > 0 && (float)$user['quota_balance'] < $estimatedMaxCost) {
            Response::error('Insufficient quota for this model and message length. Please shorten the message or upgrade your plan.', 402);
            return;
        }

        Response::sse(function ($send) use ($chatId, $userId, $chat, $modelConfig, $messages, $temperature, $deepThink, $targetUserMessageId) {
            $fullResponse = '';
            $reasoningResponse = '';

            try {
                $apiUrl = $this->getApiUrl($modelConfig);
                $apiKey = $this->getApiKey($modelConfig);
                $requestModel = $this->resolveRequestModelId($chat['model'], $modelConfig);

                if (empty($apiKey)) {
                    $send('error', 'API key not configured for this model');
                    return;
                }

                if (($modelConfig['provider'] ?? '') === 'anthropic') {
                    $this->callAnthropicApi($apiUrl, $apiKey, $requestModel, $messages, $temperature, $modelConfig, $send, $fullResponse);
                } else {
                    $this->callOpenAiCompatibleApi($apiUrl, $apiKey, $requestModel, $messages, $temperature, $modelConfig, $deepThink, $send, $fullResponse, $reasoningResponse);
                }
            } catch (\Exception $e) {
                if (empty($fullResponse)) {
                    $send('error', $this->formatStreamErrorMessage($e));
                }
            }

            if (!empty($fullResponse)) {
                $inputTokens = $this->estimateTokens(implode(' ', array_column($messages, 'content')));
                $outputTokens = $this->estimateTokens(trim($fullResponse . ' ' . $reasoningResponse));
                $variantNo = $this->messageModel->getNextAssistantVariantNo($chatId, $targetUserMessageId);

                $this->messageModel->create(
                    $chatId,
                    'assistant',
                    $fullResponse,
                    $outputTokens,
                    $chat['model'],
                    [
                        'reasoning_content' => $reasoningResponse,
                        'parent_user_message_id' => $targetUserMessageId,
                        'variant_no' => $variantNo,
                    ]
                );

                $this->chatModel->update($chatId, [
                    'last_message' => mb_substr($fullResponse, 0, 100),
                ]);

                $cost = $this->quotaService->calculateCost($chat['model'], $inputTokens, $outputTokens);
                $this->quotaService->deductQuota($userId, $cost, "Chat #{$chatId} - {$chat['model']}", [
                    'model_id' => $chat['model'],
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                ]);

                $send('meta', json_encode([
                    'parent_user_message_id' => $targetUserMessageId,
                    'variant_no' => $variantNo,
                ], JSON_UNESCAPED_UNICODE));
            }
        });
    }

    private function buildMessages(int $chatId, array $modelConfig, bool $webSearch, bool $deepThink, ?int $historyUntilUserMessageId = null): array
    {
        $systemPrompt = trim((string)($modelConfig['system_prompt'] ?? ''));
        if ($systemPrompt === '') {
            $systemPrompt = 'You are a helpful AI assistant. Answer clearly, honestly, and in the user language when practical.';
        }
        if ($webSearch) {
            $systemPrompt .= ' The user enabled web-search mode, but this installation does not provide a search connector. If current facts matter, explain that external verification may be needed.';
        }
        if ($deepThink) {
            $systemPrompt .= ' The user enabled deep thinking mode. When the model supports it, separate reasoning from final answer clearly.';
        }

        $historyLimit = (int)(new SiteSetting())->get('max_chat_history', '30');
        $historyLimit = max(1, min(200, $historyLimit));
        $history = $this->messageModel->getPromptMessages($chatId, $historyLimit, $historyUntilUserMessageId);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            if (($msg['role'] ?? '') !== 'system') {
                $messages[] = [
                    'role' => (string)$msg['role'],
                    'content' => (string)$msg['content'],
                ];
            }
        }

        $maxContext = (int)($modelConfig['max_context_tokens'] ?? 4096);
        while (count($messages) > 2 && $this->estimateTokens(implode(' ', array_column($messages, 'content'))) > $maxContext) {
            array_splice($messages, 1, 1);
        }

        return $messages;
    }

    private function callOpenAiCompatibleApi(string $apiUrl, string $apiKey, string $model, array $messages, float $temperature, array $modelConfig, bool $deepThink, callable $send, string &$fullResponse, string &$reasoningResponse): void
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'temperature' => $temperature,
            'max_tokens' => (int)($modelConfig['max_output_tokens'] ?? 2048),
        ];

        if (($modelConfig['provider'] ?? '') === 'deepseek') {
            $payload['thinking'] = ['type' => $deepThink ? 'enabled' : 'disabled'];
            if ($deepThink) {
                $payload['reasoning_effort'] = 'high';
            }
        }

        $postData = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $this->runCurlStream($apiUrl, $postData, $headers, function ($line) use ($send, &$fullResponse, &$reasoningResponse) {
            if (!str_starts_with($line, 'data: ')) {
                return;
            }
            $json = substr($line, 6);
            if ($json === '[DONE]') {
                return;
            }
            $parsed = json_decode($json, true);
            if (!$parsed || !isset($parsed['choices'][0]['delta']) || !is_array($parsed['choices'][0]['delta'])) {
                return;
            }

            $delta = $parsed['choices'][0]['delta'];
            if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                $chunk = $delta['reasoning_content'];
                $reasoningResponse .= $chunk;
                $send('reasoning', $chunk);
            }

            if (isset($delta['content']) && $delta['content'] !== '') {
                $chunk = $delta['content'];
                $fullResponse .= $chunk;
                $send('message', $chunk);
            }
        });
    }

    private function callAnthropicApi(string $apiUrl, string $apiKey, string $model, array $messages, float $temperature, array $modelConfig, callable $send, string &$fullResponse): void
    {
        $systemContent = '';
        $apiMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemContent = $msg['content'];
            } else {
                $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $postData = json_encode([
            'model' => $model,
            'max_tokens' => (int)($modelConfig['max_output_tokens'] ?? 2048),
            'temperature' => $temperature,
            'system' => $systemContent,
            'messages' => $apiMessages,
            'stream' => true,
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $currentEvent = '';
        $this->runCurlStream($apiUrl, $postData, $headers, function ($line) use ($send, &$fullResponse, &$currentEvent) {
            if (str_starts_with($line, 'event: ')) {
                $currentEvent = substr($line, 7);
                return;
            }
            if (!str_starts_with($line, 'data: ')) {
                return;
            }

            $parsed = json_decode(substr($line, 6), true);
            if (!$parsed) {
                return;
            }

            if ($currentEvent === 'content_block_delta' && isset($parsed['delta']['text'])) {
                $chunk = $parsed['delta']['text'];
                $fullResponse .= $chunk;
                $send('message', $chunk);
            }
        });
    }

    private function runCurlStream(string $apiUrl, string $postData, array $headers, callable $onLine): void
    {
        $attempt = $this->executeCurlStreamOnce($apiUrl, $postData, $headers, $onLine, true);

        if ($attempt['ok'] === false && $this->shouldRetryWithInsecureTls($apiUrl, $attempt['error'], $attempt['status'])) {
            $attempt = $this->executeCurlStreamOnce($apiUrl, $postData, $headers, $onLine, false);
        }

        if ($attempt['ok'] === false) {
            throw new \RuntimeException($attempt['error'] ?: 'AI provider request failed');
        }
        if ($attempt['status'] >= 400) {
            throw new \RuntimeException($this->extractProviderErrorMessage($attempt['status'], $attempt['rawResponse']));
        }
    }

    private function getApiUrl(array $modelConfig): string
    {
        if (!empty($modelConfig['api_base_url'])) {
            $base = rtrim($modelConfig['api_base_url'], '/');
            if (!$this->isValidApiBaseUrl($base)) {
                throw new \RuntimeException('Invalid API base URL');
            }
            if (($modelConfig['provider'] ?? '') === 'anthropic') {
                return $this->appendApiPath($base, '/v1/messages');
            }
            return $this->appendApiPath($base, '/v1/chat/completions');
        }

        switch ($modelConfig['provider']) {
            case 'anthropic': return 'https://api.anthropic.com/v1/messages';
            case 'deepseek': return 'https://api.deepseek.com/v1/chat/completions';
            default: return 'https://api.openai.com/v1/chat/completions';
        }
    }

    private function isValidApiBaseUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }

        return !preg_match('/(^localhost$|(^|\.)local$|^127\.|^10\.|^192\.168\.|^172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $parts['host']);
    }

    private function getApiKey(?array $modelConfig): string
    {
        if ($modelConfig && !empty($modelConfig['api_key'])) {
            return (string)$modelConfig['api_key'];
        }

        $provider = $modelConfig ? (string)$modelConfig['provider'] : 'openai';
        switch ($provider) {
            case 'anthropic': return getenv('ANTHROPIC_API_KEY') ?: '';
            case 'deepseek': return getenv('DEEPSEEK_API_KEY') ?: '';
            default: return getenv('OPENAI_API_KEY') ?: '';
        }
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    private function resolveRequestModelId(string $chatModel, array $modelConfig): string
    {
        $configured = trim((string)($modelConfig['model_id'] ?? ''));
        $resolved = $configured !== '' ? $configured : trim($chatModel);
        $provider = strtolower((string)($modelConfig['provider'] ?? ''));

        if ($provider !== 'deepseek') {
            return $resolved;
        }

        $normalized = strtolower(str_replace('_', '-', $resolved));
        if (str_starts_with($normalized, 'deepseek-')) {
            return $normalized;
        }
        if ($normalized === 'deepseekv4pro') {
            return 'deepseek-v4-pro';
        }
        if ($normalized === 'deepseekv4flash') {
            return 'deepseek-v4-flash';
        }

        return $resolved;
    }

    private function appendApiPath(string $baseUrl, string $defaultPath): string
    {
        $base = rtrim($baseUrl, '/');
        $lowerBase = strtolower($base);
        $lowerPath = strtolower($defaultPath);
        $pathWithoutV1 = strtolower(preg_replace('#^/v1#i', '', $defaultPath) ?: $defaultPath);

        if (str_ends_with($lowerBase, $lowerPath) || str_ends_with($lowerBase, $pathWithoutV1)) {
            return $base;
        }
        if (str_ends_with($lowerBase, '/v1')) {
            return $base . preg_replace('#^/v1#i', '', $defaultPath);
        }
        return $base . $defaultPath;
    }

    private function extractProviderErrorMessage(int $status, string $rawResponse): string
    {
        $raw = trim($rawResponse);
        $prefix = 'AI provider returned HTTP ' . $status;
        if ($raw === '') {
            return $prefix;
        }

        $payload = json_decode($raw, true);
        $message = $this->extractErrorMessageFromPayload($payload);
        if ($message !== '') {
            return $message;
        }

        $lines = preg_split('/\r?\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }
            $json = substr($line, 6);
            if ($json === '' || $json === '[DONE]') {
                continue;
            }
            $parsed = json_decode($json, true);
            $message = $this->extractErrorMessageFromPayload($parsed);
            if ($message !== '') {
                return $message;
            }
        }

        return $prefix . ': ' . mb_substr($raw, 0, 240);
    }

    private function extractErrorMessageFromPayload($payload): string
    {
        if (!is_array($payload)) {
            return '';
        }

        if (isset($payload['error'])) {
            if (is_string($payload['error']) && trim($payload['error']) !== '') {
                return trim($payload['error']);
            }
            if (is_array($payload['error'])) {
                foreach (['message', 'detail', 'type', 'code'] as $key) {
                    if (isset($payload['error'][$key]) && is_string($payload['error'][$key]) && trim($payload['error'][$key]) !== '') {
                        return trim($payload['error'][$key]);
                    }
                }
            }
        }

        foreach (['message', 'detail', 'error_message'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        return '';
    }

    private function executeCurlStreamOnce(
        string $apiUrl,
        string $postData,
        array $headers,
        callable $onLine,
        bool $verifyTls
    ): array {
        $buffer = '';
        $rawResponse = '';
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer, &$rawResponse, $onLine) {
                $rawResponse .= $data;
                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);
                foreach ($lines as $line) {
                    $onLine(trim($line));
                }
                return strlen($data);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($buffer !== '') {
            $onLine(trim($buffer));
        }

        return [
            'ok' => $ok !== false,
            'status' => $status,
            'error' => $error,
            'rawResponse' => $rawResponse,
        ];
    }

    private function shouldRetryWithInsecureTls(string $apiUrl, string $curlError, int $status): bool
    {
        $switch = strtolower(trim((string) getenv('TLS_INSECURE_FALLBACK')));
        $enabled = $switch === '' || in_array($switch, ['1', 'true', 'yes', 'on'], true);
        if (!$enabled) {
            return false;
        }

        $error = strtolower(trim($curlError));
        if ($status !== 0 || $error === '') {
            return false;
        }

        $isCertificateError =
            str_contains($error, 'ssl certificate problem') ||
            str_contains($error, 'self signed certificate') ||
            str_contains($error, 'unable to get local issuer certificate');
        if (!$isCertificateError) {
            return false;
        }

        $host = strtolower((string) parse_url($apiUrl, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $trustedHosts = ['api.deepseek.com', 'api.openai.com', 'api.anthropic.com'];
        return in_array($host, $trustedHosts, true);
    }

    private function formatStreamErrorMessage(\Exception $e): string
    {
        $message = trim((string)$e->getMessage());
        if ($message === '') {
            return 'Sorry, an error occurred while processing your request. Please try again.';
        }

        $lower = strtolower($message);
        if (str_contains($lower, 'ssl certificate problem') || (str_contains($lower, 'certificate') && str_contains($lower, 'self signed'))) {
            return 'TLS 证书校验失败：当前 API 地址返回了不受信任的证书（self-signed certificate）。请在模型配置中检查 API 基础地址是否为官方可信地址，或修复代理/网关证书链。';
        }

        return $message;
    }

    private function isDefaultChatTitle(string $title): bool
    {
        $normalized = trim($title);
        if ($normalized === '') {
            return true;
        }
        if (str_contains($normalized, '鏂板')) {
            return true;
        }
        $defaults = ['新对话', 'new chat', 'untitled chat', 'untitled'];
        return in_array(mb_strtolower($normalized), $defaults, true);
    }
}
