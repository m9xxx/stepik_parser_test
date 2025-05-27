<?php
namespace App\Models;

class Course {
    private $id;
    private $title;
    private $description;
    private $rating;
    private $url;
    private $source;
    private $additionalData;

    public function __construct($data, $source) {
        $this->id = $data['id'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->rating = $data['rating'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->source = $source;
        $this->additionalData = $data;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'rating' => $this->rating,
            'url' => $this->url,
            'source' => $this->source,
            'additional_data' => $this->additionalData
        ];
    }

    // Базовые геттеры
    public function getId() { return $this->id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getRating() { return $this->rating; }
    public function getUrl() { return $this->url; }
    public function getSource() { return $this->source; }
    
    // Дополнительные геттеры для доступа к специфичным полям из additionalData
    public function getPrice() { 
        return $this->additionalData['price'] ?? 'N/A'; 
    }
    
    public function getCurrency() { 
        return $this->additionalData['currency'] ?? 'RUB'; 
    }
    
    public function getDuration() { 
        return $this->additionalData['duration'] ?? 'N/A'; 
    }
    
    public function getStartDate() { 
        return $this->additionalData['start_date'] ?? null; 
    }
    
    public function getEndDate() { 
        return $this->additionalData['end_date'] ?? null; 
    }
    
    public function getReviewsCount() {
        return $this->additionalData['reviews_count'] ?? 0;
    }
    
    public function getFullDescription() {
        return $this->additionalData['full_description'] ?? $this->description;
    }
}