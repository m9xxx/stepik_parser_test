<?php
namespace App\Controllers\API;

use App\Models\User;

class UserController {
    public function updateProfile() {
        try {
            // Получаем данные из тела запроса
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || !isset($data['email'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Не указаны обязательные поля'
                ]);
                return;
            }

            // Проверяем валидность email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Некорректный формат email'
                ]);
                return;
            }

            // Получаем текущего пользователя
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Пользователь не авторизован'
                ]);
                return;
            }

            $user = User::findById($userId);
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ]);
                return;
            }

            // Проверяем, не занят ли email другим пользователем
            $existingUser = User::findByUsername($data['email']);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Этот email уже используется'
                ]);
                return;
            }

            // Обновляем данные пользователя
            $user->setUsername($data['name']);
            $user->setEmail($data['email']);

            // Сохраняем изменения
            if ($user->save()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Профиль успешно обновлен',
                    'user' => $user->toArray()
                ]);
            } else {
                throw new \Exception('Ошибка при сохранении данных');
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка при обновлении профиля: ' . $e->getMessage()
            ]);
        }
    }

    public function updatePassword() {
        try {
            // Получаем данные из тела запроса
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Не указаны обязательные поля'
                ]);
                return;
            }

            // Получаем текущего пользователя
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Пользователь не авторизован'
                ]);
                return;
            }

            $user = User::findById($userId);
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ]);
                return;
            }

            // Проверяем текущий пароль
            if (!$user->authenticate($data['current_password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Неверный текущий пароль'
                ]);
                return;
            }

            // Проверяем новый пароль на сложность
            if (strlen($data['new_password']) < 8) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Новый пароль должен содержать минимум 8 символов'
                ]);
                return;
            }

            // Обновляем пароль
            $user->setPassword($data['new_password']);

            // Сохраняем изменения
            if ($user->save()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Пароль успешно обновлен'
                ]);
            } else {
                throw new \Exception('Ошибка при сохранении пароля');
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка при обновлении пароля: ' . $e->getMessage()
            ]);
        }
    }
}