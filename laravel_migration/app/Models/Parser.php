<?php
namespace App\Models;
class Parser {
private $id;
private $name;
private $lastRunTime;
private $status;
private $coursesCount;
public function __construct($name) {
    $this->name = $name;
    $this->id = $this->generateId();
}

private function generateId() {
    return md5($this->name . time());
}

public function run() {
    $startTime = microtime(true);
    
    try {
        $parserPath = dirname(__DIR__, 2) . "/parsers/{$this->name}Parser.php";
        
        if (!file_exists($parserPath)) {
            throw new \Exception("Парсер не найден: {$this->name}");
        }

        // Выполнение парсера
        $output = shell_exec("php {$parserPath} 2>&1");
        
        $this->lastRunTime = microtime(true) - $startTime;
        $this->status = 'success';
        $this->coursesCount = $this->countParsedCourses();

        return [
            'output' => $output,
            'runTime' => $this->lastRunTime,
            'coursesCount' => $this->coursesCount
        ];

    } catch (\Exception $e) {
        $this->status = 'error';
        return [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

private function countParsedCourses() {
    $dataFile = dirname(__DIR__, 2) . "/data/{$this->name}_courses.json";
    
    if (!file_exists($dataFile)) {
        return 0;
    }

    $courses = json_decode(file_get_contents($dataFile), true);
    return count($courses);
}

// Геттеры
public function getId() { return $this->id; }
public function getName() { return $this->name; }
public function getLastRunTime() { return $this->lastRunTime; }
public function getStatus() { return $this->status; }
public function getCoursesCount() { return $this->coursesCount; }
}