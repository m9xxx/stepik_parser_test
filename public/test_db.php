<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

try {
    // Проверяем подключение к БД
    $db = \App\Models\Database::getInstance();
    $connection = $db->getConnection();
    
    // Проверяем таблицы
    $tables = $connection->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        // Если таблиц нет, создаем их
        $db = new \App\Models\Database();
    }
    
    // Импортируем данные
    $courseService = new \App\Services\CourseService();
    $results = $courseService->importCoursesFromJson();
    
    echo json_encode([
        'success' => true,
        'message' => 'База данных работает, импорт выполнен',
        'tables' => $tables,
        'import_results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} 