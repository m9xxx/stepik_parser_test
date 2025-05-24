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
            'username' => 'root',           // для XAMPP по умолчанию root
            'password' => '',               // для XAMPP по умолчанию пустой
            'charset' => 'utf8mb4'
        ];

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (\PDOException $e) {
            throw new \Exception('Ошибка подключения к базе данных: ' . $e->getMessage());
        }
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