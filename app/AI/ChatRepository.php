<?php

namespace App\AI;

use mysqli;
use RuntimeException;
use Throwable;

class ChatRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array{session_id:int, client_token:string}
     */
    public function createSession(?string $userId, string $channel = 'web'): array
    {
        $token = $this->generateClientToken();
        $stmt = $this->connection->prepare(
            'INSERT INTO ai_chat_session (ID_TK, KENH, CLIENT_TOKEN) VALUES (?, ?, ?)' 
        );
        if ($stmt === false) {
            throw new RuntimeException('Không thể tạo phiên AI chat: ' . $this->connection->error);
        }

        $stmt->bind_param('sss', $userId, $channel, $token);
        if ($stmt->execute() === false) {
            $stmt->close();
            throw new RuntimeException('Thất bại khi lưu phiên AI chat: ' . $stmt->error);
        }

        $sessionId = (int) $stmt->insert_id;
        $stmt->close();

        return [
            'session_id' => $sessionId,
            'client_token' => $token,
        ];
    }

    public function getSession(int $sessionId, ?string $clientToken = null): ?array
    {
        $sql = 'SELECT ID_SESSION, ID_TK, KENH, BAT_DAU, KET_THUC, TRANG_THAI, CLIENT_TOKEN
                 FROM ai_chat_session
                 WHERE ID_SESSION = ?';
        if ($clientToken !== null) {
            $sql .= ' AND CLIENT_TOKEN = ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể đọc phiên AI chat: ' . $this->connection->error);
        }

        if ($clientToken !== null) {
            $stmt->bind_param('is', $sessionId, $clientToken);
        } else {
            $stmt->bind_param('i', $sessionId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $session = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $session ?: null;
    }

    public function saveMessage(int $sessionId, string $role, string $content, ?array $meta = null): int
    {
        $json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $this->connection->prepare(
            'INSERT INTO ai_chat_message (ID_SESSION, ROLE, NOI_DUNG, META_JSON) VALUES (?, ?, ?, ?)' 
        );
        if ($stmt === false) {
            throw new RuntimeException('Không thể lưu tin nhắn AI chat: ' . $this->connection->error);
        }

        $stmt->bind_param('isss', $sessionId, $role, $content, $json);
        if ($stmt->execute() === false) {
            $stmt->close();
            throw new RuntimeException('Thất bại khi lưu tin nhắn AI chat: ' . $stmt->error);
        }

        $messageId = (int) $stmt->insert_id;
        $stmt->close();

        return $messageId;
    }

    /**
     * @return array<int, array{ID_MSG:int, ROLE:string, NOI_DUNG:string, CREATED_AT:string}>
     */
    public function fetchMessages(int $sessionId, int $limit = 20): array
    {
        $stmt = $this->connection->prepare(
            'SELECT ID_MSG, ROLE, NOI_DUNG, CREATED_AT
             FROM ai_chat_message
             WHERE ID_SESSION = ?
             ORDER BY CREATED_AT DESC, ID_MSG DESC
             LIMIT ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('Không thể tải lịch sử AI chat: ' . $this->connection->error);
        }

        $stmt->bind_param('ii', $sessionId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        // Đảo ngược để trả về theo thứ tự thời gian tăng dần (cũ -> mới)
        return array_reverse($messages ?? []);
    }

    public function closeSession(int $sessionId): void
    {
        $stmt = $this->connection->prepare(
            "UPDATE ai_chat_session SET TRANG_THAI = 'closed', KET_THUC = NOW() WHERE ID_SESSION = ?"
        );
        if ($stmt === false) {
            throw new RuntimeException('Không thể đóng phiên AI chat: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $sessionId);
        $stmt->execute();
        $stmt->close();
    }

    public function appendSessionOwner(int $sessionId, ?string $userId): void
    {
        if ($userId === null) {
            return;
        }

        $stmt = $this->connection->prepare(
            'UPDATE ai_chat_session SET ID_TK = COALESCE(ID_TK, ?) WHERE ID_SESSION = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('Không thể cập nhật phiên AI chat: ' . $this->connection->error);
        }

        $stmt->bind_param('si', $userId, $sessionId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @return array{ID_SESSION:int, ID_TK:?string, KENH:string, BAT_DAU:string, KET_THUC:?string, TRANG_THAI:string, CLIENT_TOKEN:string}
     */
    public function assertSessionAccessible(int $sessionId, ?string $clientToken, ?string $userId): array
    {
        $session = $this->getSession($sessionId, $clientToken);
        if ($session === null) {
            throw new RuntimeException('Phiên chat không tồn tại, đã xoá hoặc thông tin không hợp lệ.');
        }

        if ($session['TRANG_THAI'] === 'closed') {
            throw new RuntimeException('Phiên chat đã kết thúc. Hãy bắt đầu phiên mới.');
        }

        $owner = $session['ID_TK'] ?? null;
        if ($owner !== null && $userId !== null && $owner !== $userId) {
            throw new RuntimeException('Bạn không có quyền truy cập phiên chat này.');
        }

        return $session;
    }

    private function generateClientToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $throwable) {
            throw new RuntimeException('Không thể khởi tạo token phiên chat.', 0, $throwable);
        }
    }
}
