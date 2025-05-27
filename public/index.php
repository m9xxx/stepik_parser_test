<?php
// Временный код для отладки
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
error_log("PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set'));
error_log("POST data: " . file_get_contents('php://input'));

// Файл public/index.php

// Включаем вывод ошибок для разработки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подробное логирование
error_log("=== Новый запрос ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? ''));
error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("RAW POST DATA: " . file_get_contents('php://input'));

// Подключаем автозагрузчик
require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\API\AuthController;
use App\Controllers\API\CourseController;
use App\Middleware\AuthMiddleware;

// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Обрабатываем preflight запросы
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Получаем путь запроса
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Удаляем все возможные префиксы из пути
$prefixes = ['/stepik_parser_test/public', '/stepik_parser_test', '/public'];
foreach ($prefixes as $prefix) {
    if (strpos($request, $prefix) === 0) {
        $request = substr($request, strlen($prefix));
        break;
    }
}

// Удаляем query string из пути
if (($pos = strpos($request, '?')) !== false) {
    $request = substr($request, 0, $pos);
}

error_log("Processed request path: " . $request);
error_log("Request method: " . $method);

// Определяем маршруты
$routes = [
    'POST:/api/v1/auth/register' => function() {
        $controller = new AuthController();
        $data = json_decode(file_get_contents('php://input'), true);
        return $controller->register($data ?? []);
    },
    'POST:/api/v1/auth/login' => function() {
        $controller = new AuthController();
        $data = json_decode(file_get_contents('php://input'), true);
        return $controller->login($data ?? []);
    },
    'GET:/api/v1/courses' => function() {
        $controller = new CourseController();
        return $controller->index();
    },
    'GET:/api/v1/courses/search' => function() {
        $controller = new CourseController();
        return $controller->search();
    },
    'GET:/api/v1/parser/statistics' => [CourseController::class, 'getParserStatistics'],
    'POST:/api/v1/parser/run' => [CourseController::class, 'runAllParsers']
];

try {
    // Формируем ключ маршрута
    $routeKey = $method . ':' . $request;
    error_log("Looking for route: " . $routeKey);

    // Проверяем существование маршрута
    if (isset($routes[$routeKey])) {
        error_log("Route found: " . $routeKey);
        $handler = $routes[$routeKey];
        $response = $handler();
        
        // Отправляем ответ
        echo json_encode($response);
        exit();
    }

    // Если маршрут не найден
    error_log("Route not found: " . $routeKey);
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Route not found',
        'request' => $request,
        'method' => $method
    ]);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error: ' . $e->getMessage()
    ]);
}