<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

try {
    $courseService = new \App\Services\CourseService();
    $results = $courseService->importCoursesFromJson();
    
    echo json_encode([
        'success' => true,
        'message' => 'Импорт завершен',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при импорте: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} 