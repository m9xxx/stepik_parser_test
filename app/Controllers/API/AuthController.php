<?php

namespace App\Controllers\API;

use App\Models\User;
use App\Database\Database;

class AuthController
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function register($data)
    {
        error_log("Register method called with data: " . print_r($data, true));

        // Валидация данных
        if (!isset($data['email']) || !isset($data['password'])) {
            return [
                'success' => false,
                'message' => 'Email and password are required'
            ];
        }

        // Проверяем, существует ли пользователь
        $existingUser = $this->db->query(
            "SELECT * FROM users WHERE email = ?",
            [$data['email']]
        )->fetch();

        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'User with this email already exists'
            ];
        }

        // Хешируем пароль
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Создаем пользователя
        $userId = $this->db->query(
            "INSERT INTO users (email, password) VALUES (?, ?)",
            [$data['email'], $hashedPassword]
        );

        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Failed to create user'
            ];
        }

        return [
            'success' => true,
            'message' => 'User registered successfully',
            'user' => [
                'id' => $userId,
                'email' => $data['email']
            ]
        ];
    }

    public function login($data)
    {
        error_log("Login method called with data: " . print_r($data, true));

        // Валидация данных
        if (!isset($data['email']) || !isset($data['password'])) {
            return [
                'success' => false,
                'message' => 'Email and password are required'
            ];
        }

        // Получаем пользователя
        $user = $this->db->query(
            "SELECT * FROM users WHERE email = ?",
            [$data['email']]
        )->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }

        // Генерируем токен
        $token = bin2hex(random_bytes(32));

        // Сохраняем токен в базе
        $this->db->query(
            "UPDATE users SET token = ? WHERE id = ?",
            [$token, $user['id']]
        );

        return [
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ];
    }
} 