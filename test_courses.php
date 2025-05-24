<?php
// Подключаем самописный автозагрузчик
require_once __DIR__ . '/autoload.php';
use App\Services\CourseService;
use App\Services\ParserService;

// Тестирование работы с курсами
function testCourseService() {
    echo "===== Тестирование CourseService =====\n";
    $courseService = new CourseService(); // Без передачи пути
    
    // Получаем все курсы
    echo "1. Получение всех курсов:\n";
    $allCourses = $courseService->getAllCourses();
    echo "Всего курсов: " . count($allCourses) . "\n";
    
    // Подсчет курсов по источникам
    $countBySource = [];
    foreach ($allCourses as $course) {
        $source = $course->getSource();
        if (!isset($countBySource[$source])) {
            $countBySource[$source] = 0;
        }
        $countBySource[$source]++;
    }
    
    echo "Распределение курсов по источникам:\n";
    foreach ($countBySource as $source => $count) {
        echo "- {$source}: {$count} курсов\n";
    }
    
    // Вывод первых 2 курсов по каждому источнику
    echo "\nПримеры курсов по источникам:\n";
    $sourceExamples = [];
    foreach ($allCourses as $course) {
        $source = $course->getSource();
        if (!isset($sourceExamples[$source])) {
            $sourceExamples[$source] = [];
        }
        if (count($sourceExamples[$source]) < 2) {
            $sourceExamples[$source][] = $course;
        }
    }
    
    foreach ($sourceExamples as $source => $courses) {
        echo "Курсы из {$source}:\n";
        foreach ($courses as $course) {
            echo "  ID: {$course->getId()}, Название: {$course->getTitle()}, Цена: {$course->getPrice()} {$course->getCurrency()}\n";
        }
    }
    
    // Поиск курсов
    echo "\n2. Поиск курсов:\n";
    
    // Поиск по запросу 'python'
    echo "a) Поиск по запросу 'python':\n";
    $pythonCourses = $courseService->searchCourses('python');
    echo "Найдено курсов: " . count($pythonCourses) . "\n";
    
    if (count($pythonCourses) > 0) {
        foreach (array_slice($pythonCourses, 0, 3) as $course) {
            echo "ID: {$course->getId()}, Название: {$course->getTitle()}, Источник: {$course->getSource()}\n";
        }
        if (count($pythonCourses) > 3) {
            echo "... и еще " . (count($pythonCourses) - 3) . " курсов\n";
        }
    }
    
    // Поиск по запросу 'дизайн'
    echo "\nb) Поиск по запросу 'дизайн':\n";
    $designCourses = $courseService->searchCourses('дизайн');
    echo "Найдено курсов: " . count($designCourses) . "\n";
    
    if (count($designCourses) > 0) {
        foreach (array_slice($designCourses, 0, 3) as $course) {
            echo "ID: {$course->getId()}, Название: {$course->getTitle()}, Источник: {$course->getSource()}\n";
        }
        if (count($designCourses) > 3) {
            echo "... и еще " . (count($designCourses) - 3) . " курсов\n";
        }
    }
    
    // Поиск по запросу 'разработчик'
    echo "\nc) Поиск по запросу 'разработчик':\n";
    $devCourses = $courseService->searchCourses('разработчик');
    echo "Найдено курсов: " . count($devCourses) . "\n";
    
    if (count($devCourses) > 0) {
        foreach (array_slice($devCourses, 0, 3) as $course) {
            echo "ID: {$course->getId()}, Название: {$course->getTitle()}, Источник: {$course->getSource()}\n";
        }
        if (count($devCourses) > 3) {
            echo "... и еще " . (count($devCourses) - 3) . " курсов\n";
        }
    }
    
    // Получение конкретного курса по каждому источнику
    echo "\n3. Получение курса по ID для каждого источника:\n";
    
    // Stepik
    $stepikId = '67'; // ID реального курса из Stepik
    $course = $courseService->getCourseById($stepikId, 'stepik');
    if ($course) {
        echo "a) Stepik: Курс найден: {$course->getTitle()} (ID: {$course->getId()})\n";
        echo "   Описание: " . substr($course->getDescription(), 0, 100) . "...\n";
    } else {
        echo "a) Stepik: Курс с ID {$stepikId} не найден\n";
    }
    
    // Skillbox
    $skillboxId = 'python-basic'; // ID реального курса из Skillbox
    $course = $courseService->getCourseById($skillboxId, 'skillbox');
    if ($course) {
        echo "b) Skillbox: Курс найден: {$course->getTitle()} (ID: {$course->getId()})\n";
        echo "   Описание: " . substr($course->getDescription(), 0, 100) . "...\n";
    } else {
        echo "b) Skillbox: Курс с ID {$skillboxId} не найден\n";
    }
    
    // GeekBrains
    $geekbrainsId = 'developer-gb'; // ID реального курса из GeekBrains
    $course = $courseService->getCourseById($geekbrainsId, 'geekbrains');
    if ($course) {
        echo "c) GeekBrains: Курс найден: {$course->getTitle()} (ID: {$course->getId()})\n";
        echo "   Описание: " . substr($course->getDescription(), 0, 100) . "...\n";
    } else {
        echo "c) GeekBrains: Курс с ID {$geekbrainsId} не найден\n";
    }
    
    // Попытка получить несуществующий курс
    $fakeId = 'non-existent-course-123';
    $course = $courseService->getCourseById($fakeId, 'stepik');
    if ($course) {
        echo "d) Несуществующий курс найден (это ошибка!)\n";
    } else {
        echo "d) Тест на несуществующий курс пройден успешно: Курс с ID {$fakeId} не найден\n";
    }
}

// Тестирование работы парсеров
function testParserService() {
    echo "\n===== Тестирование ParserService =====\n";
    $parserService = new ParserService();
    
    // Получение статистики парсеров
    echo "1. Статистика парсеров:\n";
    $statistics = $parserService->getParserStatistics();
    
    foreach ($statistics as $parserName => $stats) {
        echo "Парсер: {$parserName}\n";
        foreach ($stats as $key => $value) {
            if ($key === 'lastUpdated' && !is_null($value)) {
                echo "  {$key}: " . date('Y-m-d H:i:s', $value) . "\n";
            } else {
                echo "  {$key}: {$value}\n";
            }
        }
        echo "\n";
    }
    
    // Проверка списка доступных парсеров
    echo "2. Проверка доступности парсеров:\n";
    $availableParsers = ['stepik', 'skillbox', 'geekbrains'];
    
    foreach ($availableParsers as $parserName) {
        try {
            // Просто проверяем, что не выбросит исключение
            $parserService->runSpecificParser($parserName);
            echo "Парсер '{$parserName}' доступен\n";
        } catch (Exception $e) {
            echo "Ошибка при проверке парсера '{$parserName}': {$e->getMessage()}\n";
        }
    }
    
    // Проверка обработки несуществующего парсера
    echo "\n3. Проверка обработки несуществующего парсера:\n";
    try {
        $parserService->runSpecificParser('nonexistent_parser');
        echo "ОШИБКА: Должно было быть выброшено исключение!\n";
    } catch (Exception $e) {
        echo "Правильное поведение: {$e->getMessage()}\n";
    }
}

// Тестирование производительности
function testPerformance() {
    echo "\n===== Тестирование производительности =====\n";
    $courseService = new CourseService();
    
    // Замер времени на получение всех курсов
    echo "1. Замер времени получения всех курсов:\n";
    $startTime = microtime(true);
    $allCourses = $courseService->getAllCourses();
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);
    $courses = count($allCourses);
    echo "Получено {$courses} курсов за {$duration} мс (" . round($courses / ($duration / 1000), 2) . " курсов/сек)\n";
    
    // Замер времени поиска курсов
    echo "\n2. Замер времени поиска курсов:\n";
    $queries = ['python', 'дизайн', 'разработка', 'программирование', 'web'];
    
    foreach ($queries as $query) {
        $startTime = microtime(true);
        $courses = $courseService->searchCourses($query);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        $count = count($courses);
        echo "Поиск по запросу '{$query}': найдено {$count} курсов за {$duration} мс\n";
    }
}

// Запуск тестов
function runTests() {
    try {
        testCourseService();
        testParserService();
        testPerformance();
        
        echo "\n===== Тестирование завершено успешно =====\n";
    } catch (Exception $e) {
        echo "\n===== ОШИБКА ПРИ ВЫПОЛНЕНИИ ТЕСТОВ =====\n";
        echo "Сообщение: " . $e->getMessage() . "\n";
        echo "Файл: " . $e->getFile() . "\n";
        echo "Строка: " . $e->getLine() . "\n";
        echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    }
}

// Выполнение тестов
runTests();