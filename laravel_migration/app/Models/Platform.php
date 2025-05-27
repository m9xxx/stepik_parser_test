<?php
namespace App\Models;
class Platform 
{
    private $id;
    private $name;
    private $url;
    private $createdAt;

    public function __construct($data = []) 
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function save() 
    {
        $db = Database::getInstance()->getConnection();
        
        if ($this->id) {
            $stmt = $db->prepare("UPDATE platforms SET name = ?, url = ? WHERE id = ?");
            return $stmt->execute([$this->name, $this->url, $this->id]);
        } else {
            $stmt = $db->prepare("INSERT INTO platforms (name, url) VALUES (?, ?)");
            $stmt->execute([$this->name, $this->url]);
            $this->id = $db->lastInsertId();
            return true;
        }
    }

    public static function findById($id) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM platforms WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public static function findByName($name) 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM platforms WHERE name = ?");
        $stmt->execute([$name]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }

    public static function getAll() 
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM platforms ORDER BY name");
        $platforms = [];
        
        while ($data = $stmt->fetch()) {
            $platforms[] = new self($data);
        }
        
        return $platforms;
    }

    public function toArray() 
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'created_at' => $this->createdAt
        ];
    }

    // Геттеры
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getUrl() { return $this->url; }
    public function getCreatedAt() { return $this->createdAt; }

    // Сеттеры
    public function setName($name) { $this->name = $name; }
    public function setUrl($url) { $this->url = $url; }
}