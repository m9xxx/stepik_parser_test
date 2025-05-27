<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

try {
    $db = \App\Models\Database::getInstance();
    $connection = $db->getConnection();
    
    // Получаем количество курсов по платформам
    $stmt = $connection->query("
        SELECT platform_id, COUNT(*) as count 
        FROM courses 
        GROUP BY platform_id
    ");
    $courseCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем названия платформ
    $stmt = $connection->query("SELECT * FROM platforms");
    $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Формируем статистику
    $statistics = [];
    foreach ($platforms as $platform) {
        $count = 0;
        foreach ($courseCounts as $courseCount) {
            if ($courseCount['platform_id'] == $platform['id']) {
                $count = $courseCount['count'];
                break;
            }
        }
        $statistics[$platform['name']] = $count;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Статистика по курсам',
        'statistics' => $statistics
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} 