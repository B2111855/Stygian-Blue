<?php

declare(strict_types=1);

use App\AI\ChatRepository;
use App\AI\GeminiClient;
use Dotenv\Dotenv;

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->safeLoad();

require __DIR__ . '/../../../database/config.php';

$rawBody = file_get_contents('php://input');
$rawBody = $rawBody === false ? '' : trim($rawBody);
$input = $rawBody !== '' ? json_decode($rawBody, true) : null;

if (!is_array($input)) {
    http_response_code(400);
    $errorMessage = $rawBody === ''
        ? 'Không nhận được dữ liệu. Vui lòng thử lại.'
        : 'JSON không hợp lệ: ' . json_last_error_msg();
    echo json_encode(['ok' => false, 'error' => $errorMessage], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$action = is_string($input['action'] ?? null) ? strtolower($input['action']) : 'send';
$message = isset($input['message']) ? trim((string) $input['message']) : '';

$sessionId = null;
if (array_key_exists('session_id', $input)) {
    $candidate = $input['session_id'];
    if (is_numeric($candidate) && (int) $candidate > 0) {
        $sessionId = (int) $candidate;
    }
}
$userId = $_SESSION['ID_TK'] ?? ($_SESSION['user']['ID_TK'] ?? null);

$repo = new ChatRepository($conn);

try {
    $conn->set_charset('utf8mb4');
    $gemini = new GeminiClient();
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
    $conn->close();
    exit;
}

$systemPrompt = <<<PROMPT
Bạn là trợ lý AI của studio chụp ảnh Stygian Blue. Hãy tư vấn thân thiện, ngắn gọn, ưu tiên tiếng Việt, và đưa ra hướng dẫn rõ ràng về dịch vụ chụp ảnh, lịch hẹn, báo giá, thanh toán, cũng như các câu hỏi thường gặp. Nếu câu hỏi vượt ngoài phạm vi, hãy lịch sự từ chối hoặc đề xuất hướng xử lý khác.
PROMPT;

$maxHistory = 20;

try {
    switch ($action) {
        case 'start':
            $sessionId = $repo->createSession($userId, 'web');
            $repo->appendSessionOwner($sessionId, $userId);
            $messages = $repo->fetchMessages($sessionId, $maxHistory);
            echo json_encode([
                'ok' => true,
                'session_id' => $sessionId,
                'messages' => $messages,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'history':
            if (!$sessionId) {
                throw new RuntimeException('Thiếu mã phiên chat.');
            }
            $repo->assertSessionAccessible($sessionId, $userId);
            $messages = $repo->fetchMessages($sessionId, $maxHistory);
            echo json_encode([
                'ok' => true,
                'session_id' => $sessionId,
                'messages' => $messages,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'send':
        default:
            if ($message === '') {
                throw new RuntimeException('Nội dung tin nhắn không được để trống.');
            }

            if (!$sessionId) {
                $sessionId = $repo->createSession($userId, 'web');
                $repo->appendSessionOwner($sessionId, $userId);
            } else {
                $repo->assertSessionAccessible($sessionId, $userId);
            }

            $repo->saveMessage($sessionId, 'user', $message);
            $history = $repo->fetchMessages($sessionId, $maxHistory);
            $transformed = [];
            foreach ($history as $item) {
                $transformed[] = [
                    'role' => $item['ROLE'],
                    'content' => $item['NOI_DUNG'],
                ];
            }

            $geminiResponse = $gemini->generate($transformed, $systemPrompt);
            $repo->saveMessage($sessionId, 'assistant', $geminiResponse['text'], $geminiResponse['raw']);

            $messages = $repo->fetchMessages($sessionId, $maxHistory);

            echo json_encode([
                'ok' => true,
                'session_id' => $sessionId,
                'reply' => $geminiResponse['text'],
                'messages' => $messages,
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (RuntimeException $exception) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} finally {
    $conn->close();
}
