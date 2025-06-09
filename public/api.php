<?php
// public/api.php

// Логируем детали запроса
$requestData = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'raw_input' => file_get_contents('php://input'),
    'post_data' => $_POST,
    'json_data' => json_decode(file_get_contents('php://input'), true)
];

file_put_contents(__DIR__ . '/debug_request.txt', print_r($requestData, true) . "\n\n", FILE_APPEND);

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1); // Временно включаем отображение ошибок для отладки
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Создаем директорию для логов, если её нет
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Логируем все входящие запросы
file_put_contents(__DIR__ . '/../logs/api_requests.log', 
    date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n", 
    FILE_APPEND
);

// Включаем заголовки для CORS
header("Access-Control-Allow-Origin: http://127.0.0.1:8000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");

// Если это предварительный запрос OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit(0);
}

header("Content-Type: application/json; charset=UTF-8");

try {
    // Подключаем автозагрузчики
    require_once __DIR__ . '/../vendor/autoload.php';  // Composer autoloader
    require_once __DIR__ . '/../autoload.php';         // Наш autoloader
    
    // Получаем URI запроса
    $uri = $_SERVER['REQUEST_URI'];
    
    // Логируем исходный URI для отладки
    file_put_contents(__DIR__ . '/../logs/uri_debug.log', 
        date('Y-m-d H:i:s') . " Original URI: " . $uri . "\n", 
        FILE_APPEND
    );

    // Убираем базовый путь проекта
    $basePathToRemove = '/stepik_parser_test/public';
    if (strpos($uri, $basePathToRemove) === 0) {
        $uri = substr($uri, strlen($basePathToRemove));
    }
    
    // Убираем query string из URI если есть
    $uri = strtok($uri, '?');
    
    // Разбираем URI на части
    $uriParts = array_values(array_filter(explode('/', $uri)));
    
    // Логируем обработанные части URI для отладки
    file_put_contents(__DIR__ . '/../logs/uri_debug.log', 
        date('Y-m-d H:i:s') . " Processed URI parts: " . print_r($uriParts, true) . "\n", 
        FILE_APPEND
    );

    // Инициализируем необходимые переменные
    $controller = null;
    $action = null;
    $params = [];
    
    // Определяем маршрут
    if (empty($uriParts) || 
        (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'courses')) {
        $controller = new \App\Controllers\API\CourseController();
        
        if (empty($uriParts) || count($uriParts) === 3) {
            // GET /api/v1/courses - Получение всех курсов
            $action = 'index';
        } elseif ($uriParts[3] === 'search') {
            // GET /api/v1/courses/search - Поиск курсов
            $action = 'search';
        } elseif (count($uriParts) === 4 && is_numeric($uriParts[3])) {
            // GET /api/v1/courses/{id} - Получение курса по ID
            $action = 'show';
            $params[] = $uriParts[3];
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'platforms') {
        // GET /api/v1/platforms - Получение списка платформ
        $controller = new \App\Controllers\API\PlatformController();
        $action = 'index';
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'parsers') {
        $controller = new \App\Controllers\API\CourseController();
        
        if ($uriParts[3] === 'statistics') {
            // GET /api/v1/parsers/statistics - Получение статистики парсеров
            $action = 'getParserStatistics';
        } elseif ($uriParts[3] === 'run' && isset($uriParts[4])) {
            // POST /api/v1/parsers/run/{parser} - Запуск конкретного парсера
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = 'runParser';
                $params[] = $uriParts[4];
            }
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'import') {
        $controller = new \App\Controllers\API\CourseController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = 'importCourses';
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'auth') {
        $controller = new \App\Controllers\API\AuthController();
        
        if ($uriParts[3] === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = 'register';
        } elseif ($uriParts[3] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = 'login';
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'favorites') {
        require_once __DIR__ . '/../app/Controllers/API/FavoriteController.php';
        $controller = new \App\Controllers\API\FavoriteController();
    
        if (count($uriParts) === 3 && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/v1/favorites?user_id=...
            $action = 'list';
        } elseif (count($uriParts) === 4 && $uriParts[3] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST /api/v1/favorites/add
            $action = 'add';
        } elseif (count($uriParts) === 4 && $uriParts[3] === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST /api/v1/favorites/remove
            $action = 'remove';
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'playlists') {
        require_once __DIR__ . '/../app/Controllers/API/PlaylistController.php';
        $controller = new \App\Controllers\API\PlaylistController();
        
        if (count($uriParts) === 3) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /api/v1/playlists - Получить все плейлисты пользователя
                $action = 'index';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // POST /api/v1/playlists - Создать новый плейлист
                $action = 'store';
            }
        } elseif (count($uriParts) === 4 && $uriParts[3] === 'public' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/v1/playlists/public - Получить публичные плейлисты
            $action = 'publicPlaylists';
        } elseif (count($uriParts) === 4 && $uriParts[3] === 'search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/v1/playlists/search - Поиск плейлистов
            $action = 'search';
        } elseif (count($uriParts) === 4 && is_numeric($uriParts[3])) {
            $params[] = $uriParts[3]; // ID плейлиста
            
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /api/v1/playlists/{id} - Получить конкретный плейлист
                $action = 'show';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                // PUT /api/v1/playlists/{id} - Обновить плейлист
                $action = 'update';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                // DELETE /api/v1/playlists/{id} - Удалить плейлист
                $action = 'destroy';
            }
        } elseif (count($uriParts) === 5 && is_numeric($uriParts[3]) && $uriParts[4] === 'courses') {
            $params[] = $uriParts[3]; // ID плейлиста
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // POST /api/v1/playlists/{id}/courses - Добавить курс в плейлист
                $action = 'addCourse';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /api/v1/playlists/{id}/courses - Получить курсы из плейлиста
                $action = 'getCourses';
            }
        } elseif (count($uriParts) === 6 && is_numeric($uriParts[3]) && $uriParts[4] === 'courses' && is_numeric($uriParts[5])) {
            $params[] = $uriParts[3]; // ID плейлиста
            $params[] = $uriParts[5]; // ID курса
            
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                // DELETE /api/v1/playlists/{id}/courses/{courseId} - Удалить курс из плейлиста
                $action = 'removeCourse';
            }
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'user') {
        require_once __DIR__ . '/../app/Controllers/API/UserController.php';
        $controller = new \App\Controllers\API\UserController();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($uriParts[3] === 'profile') {
                $action = 'updateProfile';
            } elseif ($uriParts[3] === 'password') {
                $action = 'updatePassword';
            }
        }
    } elseif (count($uriParts) >= 3 && $uriParts[0] === 'api' && $uriParts[1] === 'v1' && $uriParts[2] === 'users') {
        require_once __DIR__ . '/../app/Controllers/API/UserController.php';
        $controller = new \App\Controllers\API\UserController();

        if (count($uriParts) === 3 && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/v1/users?ids=1,2,3 - Получить нескольких пользователей
            $action = 'getMultiple';
        } elseif (count($uriParts) === 4 && is_numeric($uriParts[3]) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/v1/users/{id} - Получить одного пользователя
            $action = 'show';
            $params[] = $uriParts[3];
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
        'method' => $_SERVER['REQUEST_METHOD'],
        'server' => $_SERVER
    ];
    
    // Логируем отладочную информацию
    file_put_contents(__DIR__ . '/../logs/api_debug.log', 
        date('Y-m-d H:i:s') . " - Debug Info:\n" . print_r($debug, true) . "\n\n", 
        FILE_APPEND
    );
    
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