<?php

namespace App\Controllers\API;

use App\Services\CourseService;
use App\Services\ParserService;
use Exception;

class CourseController
{
    protected $courseService;
    protected $parserService;

    /**
     * Конструктор контроллера
     */
    public function __construct(CourseService $courseService = null, ParserService $parserService = null)
    {
        $this->courseService = $courseService ?: new CourseService();
        $this->parserService = $parserService ?: new ParserService();
    }

    /**
     * Получение всех курсов
     * 
     * @return array
     */
    public function index()
    {
        try {
            $courses = $this->courseService->getAllCourses();
            
            // Преобразуем объекты курсов в массивы для JSON-ответа
            $coursesData = array_map(function($course) {
                return $this->formatCourseData($course);
            }, $courses);
            
            return [
                'success' => true,
                'data' => [
                    'courses' => $coursesData,
                    'total' => count($coursesData),
                ]
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Поиск курсов по запросу
     * 
     * @param string $query
     * @return array
     */
    public function search($query = null)
    {
        try {
            if (empty($query)) {
                return [
                    'success' => false,
                    'message' => 'Необходимо указать параметр поиска (q)',
                    'status' => 400
                ];
            }
            
            $courses = $this->courseService->searchCourses($query);
            
            // Преобразуем объекты курсов в массивы для JSON-ответа
            $coursesData = array_map(function($course) {
                return $this->formatCourseData($course);
            }, $courses);
            
            return [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'courses' => $coursesData,
                    'total' => count($coursesData),
                ]
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Получение конкретного курса по ID
     * 
     * @param string $id
     * @return array
     */
    public function show($id)
    {
        try {
            // Пытаемся найти во всех источниках
            $sources = ['stepik', 'skillbox', 'geekbrains'];
            
            foreach ($sources as $source) {
                $course = $this->courseService->getCourseById($id, $source);
                if ($course) {
                    return [
                        'success' => true,
                        'data' => $this->formatCourseData($course)
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => "Курс с ID '{$id}' не найден",
                'status' => 404
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Получение конкретного курса по ID и источнику
     * 
     * @param string $source
     * @param string $id
     * @return array
     */
    public function showBySourceAndId($source, $id)
    {
        try {
            $course = $this->courseService->getCourseById($id, $source);
            
            if ($course) {
                return [
                    'success' => true,
                    'data' => $this->formatCourseData($course)
                ];
            }
            
            return [
                'success' => false,
                'message' => "Курс с ID '{$id}' из источника '{$source}' не найден",
                'status' => 404
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Получение статистики парсеров
     * 
     * @return array
     */
    public function getParserStatistics()
    {
        try {
            $statistics = $this->parserService->getParserStatistics();
            
            // Преобразуем временные метки в читаемый формат
            foreach ($statistics as $parserName => &$stats) {
                if (isset($stats['lastUpdated'])) {
                    $stats['lastUpdated'] = $stats['lastUpdated'] ? 
                        date('Y-m-d H:i:s', $stats['lastUpdated']) : null;
                }
            }
            
            return [
                'success' => true,
                'data' => $statistics
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Запуск конкретного парсера
     * 
     * @param string $parser
     * @return array
     */
    public function runParser($parser)
    {
        try {
            $result = $this->parserService->runSpecificParser($parser);
            
            return [
                'success' => true,
                'message' => "Парсер '{$parser}' успешно запущен",
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Запуск всех парсеров
     * 
     * @return array
     */
    public function runAllParsers()
    {
        try {
            $results = $this->parserService->runAllParsers();
            
            return [
                'success' => true,
                'message' => "Все парсеры успешно запущены",
                'data' => $results
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Форматирование данных курса для ответа
     * 
     * @param object $course
     * @return array
     */
    private function formatCourseData($course)
    {
        return [
            'id' => $course->getId(),
            'title' => $course->getTitle(),
            'description' => $course->getDescription(),
            'price' => $course->getPrice(),
            'currency' => $course->getCurrency(),
            'source' => $course->getSource(),
            'url' => $course->getUrl(),
            'imageUrl' => $course->getImageUrl(),
            'duration' => $course->getDuration(),
            'skills' => $course->getSkills(),
        ];
    }

    /**
     * Формирование ответа с ошибкой
     * 
     * @param string $message
     * @param int $statusCode
     * @return array
     */
    private function errorResponse($message, $statusCode = 500)
    {
        return [
            'success' => false,
            'message' => $message,
            'status' => $statusCode
        ];
    }
}