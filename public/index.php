<?php
// Файл public/index.php

// Подключаем автозагрузчик
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Проверяем, если запрос к API, перенаправляем на api.php
if (strpos($_SERVER['REQUEST_URI'], '/stepik_parser_test/public/api/v1/') !== false) {
    require_once __DIR__ . '/api.php';
    exit();
}

// Если не API запрос - показываем Vue.js приложение
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Aggregator</title>
    @vite('resources/css/app.css')
</head>
<body>
    <div id="app"></div>
    @vite('resources/js/app.js')
</body>
</html>