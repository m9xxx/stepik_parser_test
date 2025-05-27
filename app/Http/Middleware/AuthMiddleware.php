<?php
namespace App\Middleware;

use App\Models\User;

class AuthMiddleware {
    public function handle() {
        // Получаем заголовок Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        // Проверяем формат Bearer token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->unauthorized('Токен не предоставлен');
        }

        $token = $matches[1];
        
        try {
            // Разбираем JWT токен
            $tokenParts = explode('.', $token);
            if (count($tokenParts) != 3) {
                $this->unauthorized('Неверный формат токена');
            }

            $payload = json_decode(base64_decode($tokenParts[1]), true);
            
            // Проверяем срок действия токена
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                $this->unauthorized('Токен истек');
            }

            // Проверяем подпись
            $secret = 'your-256-bit-secret'; // Должен совпадать с секретом в AuthController
            $signature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $secret, true);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            if ($base64UrlSignature !== $tokenParts[2]) {
                $this->unauthorized('Неверная подпись токена');
            }

            // Получаем пользователя
            $user = User::findById($payload['user_id']);
            if (!$user) {
                $this->unauthorized('Пользователь не найден');
            }

            // Добавляем пользователя в глобальную переменную для доступа в контроллерах
            global $currentUser;
            $currentUser = $user;

            return true;

        } catch (\Exception $e) {
            $this->unauthorized('Ошибка проверки токена: ' . $e->getMessage());
        }
    }

    private function unauthorized($message) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
} 