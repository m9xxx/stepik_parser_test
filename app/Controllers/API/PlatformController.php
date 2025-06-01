<?php
namespace App\Controllers\API;

use App\Models\Database;

class PlatformController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index() {
        try {
            $stmt = $this->db->query("SELECT * FROM platforms ORDER BY name");
            $platforms = $stmt->fetchAll();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $platforms
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\PDOException $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка при получении списка платформ',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
} 