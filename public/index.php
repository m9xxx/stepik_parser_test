<?php
// Файл public/index.php

// Подключаем автозагрузчик
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Проверяем, если запрос к API, перенаправляем на api.php
if (strpos($_SERVER['REQUEST_URI'], '/api/v1/') === 0) {
    require_once __DIR__ . '/api.php';
    exit();
}

// Если не API запрос - показываем приветственную страницу
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Агрегатор курсов</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
        }
        h1 {
            color: #333;
        }
        ul {
            margin-top: 20px;
        }
        li {
            margin-bottom: 10px;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Агрегатор образовательных курсов</h1>
        <p>API для получения данных о курсах с различных образовательных платформ.</p>
        
        <h2>Доступные API-эндпоинты:</h2>
        <ul>
            <li><a href="/api/v1/courses">/api/v1/courses</a> - получить все курсы</li>
            <li><a href="/api/v1/courses/search?q=python">/api/v1/courses/search?q=python</a> - поиск курсов по запросу</li>
            <li><a href="/api/v1/parsers/statistics">/api/v1/parsers/statistics</a> - статистика парсеров</li>
        </ul>
        
        <p>Для удобного тестирования API используйте: <a href="/test_api.html">Тестовый интерфейс</a></p>
    </div>
</body>
</html>