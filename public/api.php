<?php
// public/api.php

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1); // Временно включаем отображение ошибок для отладки
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Включаем заголовки для CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Если это запрос OPTIONS, отвечаем только заголовками и завершаем работу
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Подключаем автозагрузчики
    require_once __DIR__ . '/../vendor/autoload.php';  // Composer autoloader
    require_once __DIR__ . '/../autoload.php';         // Наш autoloader
    
    // Получаем URI запроса
    $uri = $_SERVER['REQUEST_URI'];
    
    // Убираем query string из URI
    $uri = explode('?', $uri)[0];
    
    // Убираем базовый путь проекта и api.php из URI
    $uri = str_replace('/stepik_parser_test', '', $uri);
    $uri = str_replace('/public/api.php', '', $uri);
    
    // Разбираем URI на части
    $uriParts = explode('/', trim($uri, '/'));
    
    // Пропускаем части api/v1 если они есть
    if (count($uriParts) >= 2 && $uriParts[0] === 'api' && $uriParts[1] === 'v1') {
        $uriParts = array_slice($uriParts, 2);
    }
    
    // Инициализируем необходимые переменные
    $controller = null;
    $action = null;
    $params = [];
    
    // Определяем маршрут
    if (empty($uriParts[0]) || $uriParts[0] === 'courses') {
        $controller = new \App\Controllers\API\CourseController();
        
        if (empty($uriParts[0]) || count($uriParts) === 1) {
            // GET /api/v1/courses - Получение всех курсов
            $action = 'index';
        } elseif ($uriParts[1] === 'search') {
            // GET /api/v1/courses/search?q=query - Поиск курсов
            $action = 'search';
        } elseif (count($uriParts) === 2) {
            // GET /api/v1/courses/{id} - Получение курса по ID
            $action = 'show';
            $params[] = $uriParts[1];
        } elseif (count($uriParts) === 3) {
            // GET /api/v1/courses/{source}/{id} - Получение курса по источнику и ID
            $action = 'showBySourceAndId';
            $params[] = $uriParts[1]; // source
            $params[] = $uriParts[2]; // id
        }
    } elseif ($uriParts[0] === 'parsers') {
        $controller = new \App\Controllers\API\CourseController();
        
        if (count($uriParts) === 1) {
            // POST /api/v1/parsers - Запуск всех парсеров
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = 'runAllParsers';
            }
        } elseif ($uriParts[1] === 'statistics') {
            // GET /api/v1/parsers/statistics - Получение статистики парсеров
            $action = 'getParserStatistics';
        } elseif ($uriParts[1] === 'run' && count($uriParts) === 3) {
            // POST /api/v1/parsers/run/{parser} - Запуск конкретного парсера
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = 'runParser';
                $params[] = $uriParts[2]; // parser name
            }
        }
    } elseif ($uriParts[0] === 'import') {
        $controller = new \App\Controllers\API\CourseController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = 'importCourses';
        }
    }
    
    // Добавляем отладочную информацию
    $debug = [
        'request_uri' => $_SERVER['REQUEST_URI'],
        'processed_uri' => $uri,
        'uri_parts' => $uriParts,
        'controller' => $controller ? get_class($controller) : 'not found',
        'action' => $action,
        'params' => $params,
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    // Если контроллер и действие определены, вызываем метод
    if ($controller && $action && method_exists($controller, $action)) {
        // Выполняем действие и получаем результат
        call_user_func_array([$controller, $action], $params);
    } else {
        // Если маршрут не найден, возвращаем 404
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Маршрут не найден',
            'debug' => $debug
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    // Обработка исключений
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Внутренняя ошибка сервера',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Error $e) {
    // Обработка фатальных ошибок PHP
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Фатальная ошибка PHP',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}