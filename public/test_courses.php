<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

try {
    $db = \App\Models\Database::getInstance();
    $connection = $db->getConnection();
    
    // Проверяем, запрошен ли конкретный курс
    $courseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if ($courseId) {
        // Получаем информацию о конкретном курсе
        $stmt = $connection->prepare("
            SELECT c.*, p.name as platform_name 
            FROM courses c
            JOIN platforms p ON c.platform_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($course) {
            echo json_encode([
                'success' => true,
                'message' => 'Информация о курсе',
                'data' => $course
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Курс не найден'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        // Получаем количество курсов по платформам
        $stmt = $connection->query("
            SELECT p.name as platform_name, COUNT(*) as count 
            FROM courses c
            JOIN platforms p ON c.platform_id = p.id
            GROUP BY p.name
        ");
        $statistics = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Получаем список всех курсов
        $stmt = $connection->query("
            SELECT c.*, p.name as platform_name 
            FROM courses c
            JOIN platforms p ON c.platform_id = p.id
            ORDER BY c.id DESC
            LIMIT 10
        ");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Статистика и список последних курсов',
            'statistics' => $statistics,
            'courses' => $courses
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} 