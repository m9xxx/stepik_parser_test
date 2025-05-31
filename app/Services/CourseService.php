<?php
namespace App\Services;

use App\Utils\JsonDataReader;
use App\Models\Course;
use App\Models\CourseDB;
use App\Models\Platform;

class CourseService {
    private $jsonDataReader;

    public function __construct(JsonDataReader $jsonDataReader = null) {
        $this->jsonDataReader = $jsonDataReader ?? new JsonDataReader(dirname(__DIR__, 2) . '/data');
    }

    // Получить все курсы из БД
    public function getAllCourses() {
        return CourseDB::search(['limit' => 1000]); // Увеличиваем лимит до 1000 курсов
    }

    // Получить курс по ID из БД
    public function getCourseById($id, $source = null) {
        if ($source) {
            // Поиск по внешнему ID и платформе
            $platform = Platform::findByName($source);
            if ($platform) {
                $courses = CourseDB::search([
                    'platform_id' => $platform->getId(),
                    'external_id' => $id
                ]);
                return !empty($courses) ? $courses[0] : null;
            }
        } else {
            // Поиск по внутреннему ID
            return CourseDB::findById($id);
        }
        return null;
    }

    // Поиск курсов в БД
    public function searchCourses($query, $filters = []) {
        $searchFilters = [];
        
        if (!empty($query)) {
            $searchFilters['search'] = $query;
        }
        
        if (!empty($filters['source'])) {
            $sources = is_array($filters['source']) ? $filters['source'] : [$filters['source']];
            $platformIds = [];
            foreach ($sources as $src) {
                $platform = Platform::findByName($src);
                if ($platform) {
                    $platformIds[] = $platform->getId();
                }
            }
            if ($platformIds) {
                $searchFilters['platform_id'] = $platformIds;
                $searchFilters['limit'] = 1000;
            }
        }
        
        if (!empty($filters['rating'])) {
            $searchFilters['min_rating'] = $filters['rating'];
        }

        if (empty($searchFilters['limit'])) {
            $searchFilters['limit'] = 1000; // Оставляем стандартный лимит если не указан источник
        }

        return CourseDB::search($searchFilters);
    }

    // Импорт курсов из JSON в БД
    public function importCoursesFromJson() {
        $results = [];
        
        // Импорт Stepik
        $results['stepik'] = $this->importFromSource('stepik');
        
        // Импорт Skillbox
        $results['skillbox'] = $this->importFromSource('skillbox');
        
        // Импорт GeekBrains
        $results['geekbrains'] = $this->importFromSource('geekbrains');
        
        return $results;
    }

    private function importFromSource($sourceName) {
        try {
            // Найти или создать платформу
            $platform = Platform::findByName($sourceName);
            if (!$platform) {
                $platformData = [
                    'name' => $sourceName,
                    'url' => $this->getPlatformUrl($sourceName)
                ];
                $platform = new Platform($platformData);
                $platform->save();
            }

            // Получить курсы из JSON
            $courses = [];
            switch($sourceName) {
                case 'stepik':
                    $courses = $this->jsonDataReader->readStepikCourses();
                    break;
                case 'skillbox':
                    $courses = $this->jsonDataReader->readSkillboxCourses();
                    break;
                case 'geekbrains':
                    $courses = $this->jsonDataReader->readGeekBrainsCourses();
                    break;
            }

            $importedCount = 0;
            foreach ($courses as $courseData) {
                $courseDB = new CourseDB($courseData, $sourceName);
                $courseDB->setPlatformId($platform->getId());
                $courseDB->setParsedAt(date('Y-m-d H:i:s'));
                
                if ($courseDB->save()) {
                    $importedCount++;
                }
            }

            return [
                'success' => true,
                'imported' => $importedCount,
                'total' => count($courses)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getPlatformUrl($sourceName) {
        $urls = [
            'stepik' => 'https://stepik.org',
            'skillbox' => 'https://skillbox.ru',
            'geekbrains' => 'https://geekbrains.ru'
        ];
        
        return $urls[$sourceName] ?? '';
    }

    // DEPRECATED: Старые методы для обратной совместимости
    public function getAllCoursesFromJson() {
        $stepikCourses = $this->jsonDataReader->readStepikCourses();
        $skillboxCourses = $this->jsonDataReader->readSkillboxCourses();
        $geekbrainsCourses = $this->jsonDataReader->readGeekBrainsCourses();

        $courses = [];
        foreach ($stepikCourses as $courseData) {
            $courses[] = new Course($courseData, 'stepik');
        }
        foreach ($skillboxCourses as $courseData) {
            $courses[] = new Course($courseData, 'skillbox');
        }
        foreach ($geekbrainsCourses as $courseData) {
            $courses[] = new Course($courseData, 'geekbrains');
        }

        return $courses;
    }
}