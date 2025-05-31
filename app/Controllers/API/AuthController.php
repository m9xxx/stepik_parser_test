<?php

namespace App\Controllers\API;

use App\Models\User;

class AuthController {
    public function register() {
        try {
            // Получаем данные из POST запроса
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Проверяем наличие необходимых полей
            if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
                throw new \Exception('Не все поля заполнены');
            }
            
            // Проверяем формат email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Неверный формат email');
            }
            
            // Проверяем длину пароля
            if (strlen($data['password']) < 6) {
                throw new \Exception('Пароль должен быть не менее 6 символов');
            }
            
            // Проверяем, не существует ли уже пользователь
            $existingUser = User::findByUsername($data['username']);
            if ($existingUser) {
                throw new \Exception('Пользователь с таким именем или email уже существует');
            }
            
            // Создаем нового пользователя
            $user = new User();
            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            $user->setPassword($data['password']);
            
            // Сохраняем пользователя
            $user->save();
            
            // Возвращаем успешный ответ
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Регистрация успешна',
                'data' => $user->toArray()
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    public function login() {
        try {
            // Получаем данные из POST запроса
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Проверяем наличие необходимых полей
            if (!isset($data['email']) || !isset($data['password'])) {
                throw new \Exception('Не все поля заполнены');
            }
            
            // Ищем пользователя
            $user = User::findByUsername($data['email']);
            if (!$user) {
                throw new \Exception('Пользователь не найден');
            }
            
            // Проверяем пароль
            if (!$user->authenticate($data['password'])) {
                throw new \Exception('Неверный пароль');
            }
            
            // Возвращаем успешный ответ
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Вход выполнен успешно',
                'data' => $user->toArray()
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}