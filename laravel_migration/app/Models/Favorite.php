<?php
namespace App\Models;
class Favorite 
{
    private $id;
    private $userId;
    private $courseId;
    private $createdAt;

    public function __construct($data = []) 
    {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->courseId = $data['course_id'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function save() 
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT IGNORE INTO user_favorites (user_id, course_id) 
            VALUES (?, ?)
        ");
        
        $result = $stmt->execute([$this->userId, $this->courseId]);
        if (!$this->id && $result) {
            $this->id = $db->lastInsertId();
        }
        
        return $result;
    }

    public function delete() 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND course_id = ?");
        return $stmt->execute([$this->userId, $this->courseId]);
    }

    public static function findByUserAndCourse($userId, $courseId) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_favorites WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public static function getUserFavorites($userId) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT c.*, p.name as platform_name, f.created_at as added_to_favorites
            FROM user_favorites f
            JOIN courses c ON f.course_id = c.id
            JOIN platforms p ON c.platform_id = p.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function isFavorite($userId, $courseId) 
    {
        return self::findByUserAndCourse($userId, $courseId) !== null;
    }

    public function toArray() 
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'course_id' => $this->courseId,
            'created_at' => $this->createdAt
        ];
    }

    // Геттеры
    public function getId() { return $this->id; }
    public function getUserId() { return $this->userId; }
    public function getCourseId() { return $this->courseId; }
    public function getCreatedAt() { return $this->createdAt; }

    // Сеттеры
    public function setUserId($userId) { $this->userId = $userId; }
    public function setCourseId($courseId) { $this->courseId = $courseId; }
}