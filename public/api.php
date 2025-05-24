<?php

// Этот файл мы будем использовать как точку входа для API
// Поместите его в корневую директорию проекта или в директорию public

// Включаем заголовки для CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Если это запрос OPTIONS, отвечаем только заголовками и завершаем работу
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Подключаем автозагрузчик
require_once __DIR__ . '/../autoload.php';

// Импортируем необходимые классы
use App\Controllers\API\CourseController;

// Получаем URI запроса
$uri = $_SERVER['REQUEST_URI'];

// Убираем query string из URI
$uri = explode('?', $uri)[0];

// Обрабатываем URL без базового пути
$basePath = '/api/v1';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Разбираем URI на части
$uriParts = explode('/', trim($uri, '/'));

// Инициализируем необходимые переменные
$controller = null;
$action = null;
$params = [];

// Определяем маршрут
if (empty($uriParts[0]) || $uriParts[0] === 'courses') {
    $controller = new CourseController();
    
    if (empty($uriParts[0]) || count($uriParts) === 1) {
        // GET /api/v1/courses - Получение всех курсов
        $action = 'index';
    } elseif ($uriParts[1] === 'search') {
        // GET /api/v1/courses/search?q=query - Поиск курсов
        $action = 'search';
        $params[] = isset($_GET['q']) ? $_GET['q'] : null;
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
    $controller = new CourseController();
    
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
    $controller = new CourseController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = 'importCourses';
    }
}

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
        'message' => 'Маршрут не найден'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}