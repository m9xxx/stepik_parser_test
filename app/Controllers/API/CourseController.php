<?php
namespace App\Controllers\API;

use App\Services\CourseService;
use App\Services\ParserService;
use App\Utils\JsonDataReader;

class CourseController {
    private $courseService;
    private $parserService;

    public function __construct() {
        $this->courseService = new CourseService();
        $this->parserService = new ParserService();
    }

    // Получение всех курсов
    public function index() {
        $source = $_GET['source'] ?? null;
        $filters = [];
        
        if ($source) {
            if (is_array($source)) {
                $filters['source'] = $source;
            } else {
                $filters['source'] = explode(',', $source);
            }
        }
        
        $courses = $this->courseService->searchCourses('', $filters);
        return $this->jsonResponse(
            array_map(function($course) { return $course->toArray(); }, $courses)
        );
    }

    public function importCourses() {
        try {
            $results = $this->courseService->importCoursesFromJson();
            
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Импорт завершен',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Ошибка импорта: ' . $e->getMessage()
            ], 500);
        }
    }

    // Получение курса по ID
    public function show($id) {
        // Попробуем найти курс по ID в разных источниках
        foreach (['stepik', 'skillbox', 'geekbrains'] as $source) {
            $course = $this->courseService->getCourseById($id, $source);
            if ($course) {
                return $this->jsonResponse($course->toArray());
            }
        }
        
        return $this->jsonResponse(['error' => 'Курс не найден'], 404);
    }

    // Получение курса по ID из конкретного источника
    public function showBySourceAndId($source, $id) {
        $course = $this->courseService->getCourseById($id, $source);
        
        if (!$course) {
            return $this->jsonResponse(['error' => 'Курс не найден'], 404);
        }

        return $this->jsonResponse($course->toArray());
    }

    // Поиск курсов
    public function search() {
        $query = $_GET['q'] ?? '';
        $filters = [
            'source' => $_GET['source'] ?? null,
            'rating' => $_GET['rating'] ?? null
        ];

        $courses = $this->courseService->searchCourses($query, array_filter($filters));

        return $this->jsonResponse(
            array_map(function($course) { return $course->toArray(); }, $courses)
        );
    }

    // Получение статистики парсеров
    public function getParserStatistics() {
        $statistics = $this->parserService->getParserStatistics();
        return $this->jsonResponse($statistics);
    }

    // Запуск конкретного парсера
    public function runParser($parser) {
        try {
            $result = $this->parserService->runSpecificParser($parser);
            return $this->jsonResponse([
                'success' => true,
                'message' => "Парсер $parser запущен успешно",
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // Запуск всех парсеров
    public function runAllParsers() {
        try {
            $results = [];
            foreach (['stepik', 'skillbox', 'geekbrains'] as $parser) {
                $results[$parser] = $this->parserService->runSpecificParser($parser);
            }
            
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Все парсеры запущены успешно',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // Вспомогательный метод для формирования JSON-ответа
    private function jsonResponse($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        
        $response = $data;
        if (is_array($data) && !isset($data['success'])) {
            $response = [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'data' => $data
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}