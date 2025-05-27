<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Database;

try {
    $db = Database::getInstance()->getConnection();
    
    // Читаем и выполняем SQL файл для создания таблицы пользователей
    $sql = file_get_contents(__DIR__ . '/migrations/create_users_table.sql');
    $db->exec($sql);
    
    echo "Миграция успешно выполнена!\n";
} catch (PDOException $e) {
    echo "Ошибка при выполнении миграции: " . $e->getMessage() . "\n";
    exit(1);
} 