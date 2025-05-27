<?php
namespace App\Models;

class Database 
{
    private static $instance = null;
    private $pdo;

    private function __construct() 
    {
        $config = [
            'host' => 'localhost',
            'dbname' => 'course_aggregator',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ];

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->pdo = new \PDO($dsn, $config['username'], $config['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (\PDOException $e) {
            // Логируем ошибку подключения
            error_log('Database connection error: ' . $e->getMessage());
            
            // Проверяем, существует ли база данных
            try {
                $testDsn = "mysql:host={$config['host']};charset={$config['charset']}";
                $testPdo = new \PDO($testDsn, $config['username'], $config['password']);
                
                // Создаем базу данных если её нет
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS {$config['dbname']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Переподключаемся к созданной базе
                $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
                $this->pdo = new \PDO($dsn, $config['username'], $config['password'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                // Создаем таблицы если их нет
                $this->createTablesIfNotExist();
                
            } catch (\PDOException $createError) {
                error_log('Database creation error: ' . $createError->getMessage());
                throw new \Exception('Ошибка подключения к базе данных: ' . $createError->getMessage());
            }
        }
    }

    private function createTablesIfNotExist() 
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS platforms (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            platform_id INT NOT NULL,
            external_id VARCHAR(50) NOT NULL,
            title VARCHAR(500) NOT NULL,
            description TEXT,
            rating DECIMAL(3,2),
            review_count INT DEFAULT 0,
            price VARCHAR(100),
            url VARCHAR(500) NOT NULL,
            parsed_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
            UNIQUE KEY unique_course_per_platform (platform_id, external_id)
        );

        INSERT IGNORE INTO platforms (name, url) VALUES 
        ('stepik', 'https://stepik.org'),
        ('skillbox', 'https://skillbox.ru'),
        ('geekbrains', 'https://geekbrains.ru');
        ";

        $this->pdo->exec($sql);
    }

    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() 
    {
        return $this->pdo;
    }
}