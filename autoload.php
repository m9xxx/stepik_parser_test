<?php
// autoload.php
spl_autoload_register(function ($class) {
    // Преобразуем название класса в путь к файлу
    $class = str_replace('App\\', '', $class);
    $class = str_replace('\\', '/', $class);
    $file = __DIR__ . '/app/' . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    } else {
        // Логируем отсутствующие файлы для отладки
        error_log("Autoload: файл не найден - " . $file);
    }
});