<?php
namespace App\Models;
class CourseDB extends Course 
{
    private $platformId;
    private $externalId;
    private $parsedAt;
    private $createdAt;
    private $updatedAt;

    public function __construct($data, $source = null) 
    {
        parent::__construct($data, $source);
        
        $this->platformId = $data['platform_id'] ?? null;
        $this->externalId = $data['external_id'] ?? $data['id'];
        $this->parsedAt = $data['parsed_at'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function save() 
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO courses (platform_id, external_id, title, description, rating, review_count, price, url, parsed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                rating = VALUES(rating),
                review_count = VALUES(review_count),
                price = VALUES(price),
                url = VALUES(url),
                parsed_at = VALUES(parsed_at),
                updated_at = CURRENT_TIMESTAMP
        ");

        $rating = is_numeric($this->getRating()) ? (float)$this->getRating() : null;
        $reviewCount = is_numeric($this->getReviewsCount()) ? (int)$this->getReviewsCount() : 0;
        
        return $stmt->execute([
            $this->platformId,
            $this->externalId,
            $this->getTitle(),
            $this->getDescription(),
            $rating,
            $reviewCount,
            $this->getPrice(),
            $this->getUrl(),
            $this->parsedAt
        ]);
    }

    public static function findById($id) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT c.*, p.name as platform_name
            FROM courses c
            JOIN platforms p ON c.platform_id = p.id
            WHERE c.id = ?
        ");
        
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        if ($data) {
            $courseData = $data;
            $courseData['source'] = $data['platform_name'];
            return new self($courseData, $data['platform_name']);
        }
        
        return null;
    }

    public static function search($filters = []) 
    {
        $db = Database::getInstance()->getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['platform_id'])) {
            if (is_array($filters['platform_id'])) {
                $in = implode(',', array_fill(0, count($filters['platform_id']), '?'));
                $where[] = "c.platform_id IN ($in)";
                $params = array_merge($params, $filters['platform_id']);
            } else {
                $where[] = 'c.platform_id = ?';
                $params[] = $filters['platform_id'];
            }
        }

        if (!empty($filters['search'])) {
            $where[] = '(c.title LIKE ? OR c.description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['min_rating'])) {
            $where[] = 'c.rating >= ?';
            $params[] = $filters['min_rating'];
        }

        $orderBy = 'c.created_at DESC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'rating':
                    $orderBy = 'c.rating DESC';
                    break;
                case 'reviews':
                    $orderBy = 'c.review_count DESC';
                    break;
                case 'title':
                    $orderBy = 'c.title ASC';
                    break;
            }
        }

        $limit = !empty($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = !empty($filters['page']) ? ((int)$filters['page'] - 1) * $limit : 0;

        $sql = "
            SELECT c.*, p.name as platform_name
            FROM courses c
            JOIN platforms p ON c.platform_id = p.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $courses = [];
        while ($data = $stmt->fetch()) {
            $courseData = $data;
            $courseData['source'] = $data['platform_name'];
            $courses[] = new self($courseData, $data['platform_name']);
        }
        
        return $courses;
    }

    // Дополнительные геттеры
    public function getPlatformId() { return $this->platformId; }
    public function getExternalId() { return $this->externalId; }
    public function getParsedAt() { return $this->parsedAt; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Сеттеры
    public function setPlatformId($platformId) { $this->platformId = $platformId; }
    public function setParsedAt($parsedAt) { $this->parsedAt = $parsedAt; }
}