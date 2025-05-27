<?php
namespace App\Utils;
class JsonDataReader {
private $basePath;
public function __construct($basePath = null) {
    $this->basePath = $basePath ?? __DIR__ . '/../../data';
}

public function readStepikCourses() {
    $filePath = $this->basePath . '/stepik_courses.json';
    return $this->readJsonFile($filePath);
}

public function readSkillboxCourses() {
    $filePath = $this->basePath . '/skillbox_courses.json';
    return $this->readJsonFile($filePath);
}

public function readGeekBrainsCourses() {
    $filePath = $this->basePath . '/geekbrains_courses.json';
    return $this->readJsonFile($filePath);
}

private function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        throw new \Exception("Файл не найден: $filePath");
    }

    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Ошибка парсинга JSON: " . json_last_error_msg());
    }

    return $data;
}

public function filterCourses($courses, $filters = []) {
    return array_filter($courses, function($course) use ($filters) {
        foreach ($filters as $key => $value) {
            if (!isset($course[$key]) || $course[$key] != $value) {
                return false;
            }
        }
        return true;
    });
}
}