<?php
namespace App\Models;
class Playlist 
{
    private $id;
    private $userId;
    private $name;
    private $description;
    private $isPublic;
    private $createdAt;
    private $updatedAt;

    public function __construct($data = []) 
    {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->isPublic = $data['is_public'] ?? true;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function save() 
    {
        $db = Database::getInstance()->getConnection();
        
        if ($this->id) {
            $stmt = $db->prepare("
                UPDATE playlists 
                SET name = ?, description = ?, is_public = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$this->name, $this->description, $this->isPublic, $this->id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO playlists (user_id, name, description, is_public) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$this->userId, $this->name, $this->description, $this->isPublic]);
            $this->id = $db->lastInsertId();
            return true;
        }
    }

    public function delete() 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM playlists WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    public static function findById($id) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM playlists WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public static function getUserPlaylists($userId) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT p.*, COUNT(pc.course_id) as course_count
            FROM playlists p
            LEFT JOIN playlist_courses pc ON p.id = pc.playlist_id
            WHERE p.user_id = ?
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getPublicPlaylists($limit = 20, $offset = 0) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT p.*, u.username, COUNT(pc.course_id) as course_count
            FROM playlists p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN playlist_courses pc ON p.id = pc.playlist_id
            WHERE p.is_public = 1
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function searchPlaylists($query, $isPublic = true, $userId = null, $limit = 20, $offset = 0) 
    {
        $db = Database::getInstance()->getConnection();
        $params = [];
        $where = [];

        // Добавляем условие поиска по названию и описанию
        if (!empty($query)) {
            $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $query . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Добавляем условие для публичных/приватных подборок
        if ($isPublic) {
            $where[] = "p.is_public = 1";
        }

        // Если указан userId, ищем подборки конкретного пользователя
        if ($userId !== null) {
            $where[] = "p.user_id = ?";
            $params[] = $userId;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $db->prepare("
            SELECT p.*, u.username, COUNT(pc.course_id) as course_count
            FROM playlists p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN playlist_courses pc ON p.id = pc.playlist_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function addCourse($courseId, $position = 0) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO playlist_courses (playlist_id, course_id, position) 
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$this->id, $courseId, $position]);
    }

    public function removeCourse($courseId) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            DELETE FROM playlist_courses 
            WHERE playlist_id = ? AND course_id = ?
        ");
        
        return $stmt->execute([$this->id, $courseId]);
    }

    public function getCourses() 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT c.*, p.name as platform_name, pc.position, pc.added_at
            FROM playlist_courses pc
            JOIN courses c ON pc.course_id = c.id
            JOIN platforms p ON c.platform_id = p.id
            WHERE pc.playlist_id = ?
            ORDER BY pc.position ASC, pc.added_at ASC
        ");
        
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    public function toArray() 
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => $this->isPublic,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    // Геттеры
    public function getId() { return $this->id; }
    public function getUserId() { return $this->userId; }
    public function getName() { return $this->name; }
    public function getDescription() { return $this->description; }
    public function getIsPublic() { return $this->isPublic; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Сеттеры
    public function setName($name) { $this->name = $name; }
    public function setDescription($description) { $this->description = $description; }
    public function setIsPublic($isPublic) { $this->isPublic = $isPublic; }
}