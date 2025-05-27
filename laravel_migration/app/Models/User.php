<?php
namespace App\Models;
class User {
    private $id;
    private $username;
    private $email;
    private $passwordHash;
    private $createdAt;
    private $updatedAt;

    public function __construct($data = []) 
    {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->passwordHash = $data['password_hash'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function save() 
    {
        $db = Database::getInstance()->getConnection();
        
        if ($this->id) {
            // Обновление существующего пользователя
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$this->username, $this->email, $this->passwordHash, $this->id]);
        } else {
            // Создание нового пользователя
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash) 
                VALUES (?, ?, ?)
            ");
            
            try {
                $stmt->execute([$this->username, $this->email, $this->passwordHash]);
                $this->id = $db->lastInsertId();
                return true;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) {
                    throw new \Exception('Пользователь с таким именем или email уже существует');
                }
                throw new \Exception('Ошибка создания пользователя: ' . $e->getMessage());
            }
        }
    }

    public static function findById($id) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public static function findByUsername($username) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public function authenticate($password) 
    {
        return password_verify($password, $this->passwordHash);
    }

    public function setPassword($password) 
    {
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    public function toArray() 
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    // Геттеры
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Сеттеры
    public function setUsername($username) { $this->username = $username; }
    public function setEmail($email) { $this->email = $email; }
}