<?php
/**
 * Парсер курсов Udemy с использованием Selenium WebDriver
 * 
 * Для работы требуется:
 * 1. Установка php-webdriver: composer require php-webdriver/webdriver
 * 2. ChromeDriver: https://chromedriver.chromium.org/downloads
 * 3. Google Chrome
 */

require 'vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;

// Путь к ChromeDriver (укажите свой путь)
$chromeDriverPath = dirname(__FILE__) . '/chromedriver.exe'; // Windows
// $chromeDriverPath = '/usr/local/bin/chromedriver'; // MacOS/Linux

// Устанавливаем путь к ChromeDriver
putenv("webdriver.chrome.driver={$chromeDriverPath}");

// Функция для случайной задержки
function randomSleep($min = 3, $max = 6) {
    $delay = rand($min * 10, $max * 10) / 10;
    echo "Ожидание {$delay} секунд...\n";
    usleep($delay * 1000000);
}

// Функция для сохранения промежуточных результатов
function saveIntermediateResults($data, $filename = 'udemy_courses_temp.json') {
    file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "Промежуточные данные сохранены в файл: {$filename}\n";
}

// Функция для получения данных о курсе
function getCourseData($driver, $url) {
    try {
        echo "Открываем страницу курса: {$url}\n";
        $driver->get($url);
        
        // Ждем загрузки страницы и появления заголовка
        try {
            $driver->wait(10, 500)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('h1'))
            );
        } catch (TimeoutException $e) {
            echo "Ошибка ожидания загрузки страницы: {$e->getMessage()}\n";
        }
        
        // Даем дополнительное время для полной загрузки страницы
        randomSleep(2, 4);
        
        // Получаем URL после возможных редиректов
        $currentUrl = $driver->getCurrentURL();
        
        // Извлекаем ID курса из URL
        $urlParts = explode('/', rtrim($currentUrl, '/'));
        $courseId = end($urlParts);
        
        // Получаем заголовок курса
        $title = 'N/A';
        try {
            $titleElement = $driver->findElement(WebDriverBy::tagName('h1'));
            $title = $titleElement->getText();
        } catch (NoSuchElementException $e) {
            echo "Не удалось найти заголовок курса\n";
        }
        
        // Получаем описание курса
        $description = 'N/A';
        try {
            // Сначала пробуем найти через мета-тег
            $metaDescription = $driver->findElement(WebDriverBy::cssSelector('meta[name="description"]'));
            $description = $metaDescription->getAttribute('content');
        } catch (NoSuchElementException $e) {
            // Если не получилось, пробуем найти через элементы страницы
            try {
                $descElement = $driver->findElement(WebDriverBy::cssSelector('.ud-component--course-landing-page-udlite--description'));
                $description = $descElement->getText();
            } catch (NoSuchElementException $e2) {
                echo "Не удалось найти описание курса\n";
            }
        }
        
        // Получаем рейтинг курса
        $rating = 'N/A';
        try {
            $ratingElement = $driver->findElement(WebDriverBy::cssSelector('.star-rating-numeric, .ud-heading-sm[data-purpose="rating-number"]'));
            $rating = $ratingElement->getText();
        } catch (NoSuchElementException $e) {
            echo "Не удалось найти рейтинг курса\n";
        }
        
        // Получаем категорию курса (если возможно)
        $category = 'N/A';
        try {
            $breadcrumbs = $driver->findElements(WebDriverBy::cssSelector('.ud-breadcrumb a'));
            if (count($breadcrumbs) > 1) {
                $category = $breadcrumbs[1]->getText();
            }
        } catch (Exception $e) {
            echo "Не удалось определить категорию курса\n";
        }
        
        // Проверяем, что мы действительно получили данные курса
        if ($title !== 'N/A' || $description !== 'N/A') {
            return [
                'id' => $courseId,
                'title' => $title,
                'description' => $description,
                'rating' => $rating,
                'category' => $category,
                'url' => $currentUrl,
            ];
        }
        
        echo "Не удалось получить достаточно данных о курсе\n";
        return null;
        
    } catch (Exception $e) {
        echo "Ошибка при получении данных о курсе: {$e->getMessage()}\n";
        return null;
    }
}

// Функция для получения ссылок на курсы из категории
function getCoursesFromCategory($driver, $categoryUrl) {
    $courseLinks = [];
    
    try {
        echo "Открываем страницу категории: {$categoryUrl}\n";
        $driver->get($categoryUrl);
        
        // Ждем загрузки страницы
        try {
            $driver->wait(10, 500)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('a[href*="/course/"]'))
            );
        } catch (TimeoutException $e) {
            echo "Ошибка ожидания загрузки страницы категории: {$e->getMessage()}\n";
        }
        
        // Даем дополнительное время для полной загрузки страницы
        randomSleep(2, 4);
        
        // Скроллим страницу вниз для загрузки дополнительного контента
        $driver->executeScript('window.scrollTo(0, 800);');
        randomSleep(1, 2);
        $driver->executeScript('window.scrollTo(0, 1600);');
        randomSleep(1, 2);
        
        // Получаем все ссылки на курсы
        $links = $driver->findElements(WebDriverBy::cssSelector('a[href*="/course/"]'));
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, '/course/') !== false && !empty($href)) {
                // Избегаем дублирования ссылок
                if (!in_array($href, $courseLinks)) {
                    $courseLinks[] = $href;
                }
            }
        }
        
        echo "Найдено ссылок на курсы: " . count($courseLinks) . "\n";
        
    } catch (Exception $e) {
        echo "Ошибка при получении ссылок из категории: {$e->getMessage()}\n";
    }
    
    return $courseLinks;
}

// Основная функция
function main() {
    // Настройка Chrome
    $options = new ChromeOptions();
    
    // Добавляем аргументы для режима без UI (раскомментируйте для запуска в фоновом режиме)
    // $options->addArguments(['--headless', '--disable-gpu', '--window-size=1920,1080']);
    
    // Скрываем автоматизацию
    $options->addArguments([
        '--disable-blink-features=AutomationControlled',
        '--disable-infobars',
        '--start-maximized',
        '--disable-extensions'
    ]);
    
    $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
    $options->setExperimentalOption('useAutomationExtension', false);
    
    // Устанавливаем пользовательский user-agent
    $options->addArguments(['user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36']);
    
    // Создаем драйвер
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    
    $driver = ChromeDriver::start($capabilities);
    
    // Максимальный размер окна
    $driver->manage()->window()->maximize();
    
    $all_courses_data = [];
    
    try {
        // Устанавливаем тайм-аут для загрузки страницы
        $driver->manage()->timeouts()->pageLoadTimeout(30);
        
        // Сначала посещаем главную страницу для установки cookies
        echo "Открываем главную страницу для инициализации сессии...\n";
        $driver->get('https://www.udemy.com/');
        randomSleep(3, 5);
        
        echo "------------------------\n";
        echo "Метод 1: Использование прямых ссылок на курсы\n";
        echo "------------------------\n";
        
        // Список известных популярных курсов Udemy
        $courseUrls = [
            'https://www.udemy.com/course/the-complete-web-developer-zero-to-mastery/',
            'https://www.udemy.com/course/the-complete-javascript-course/',
            'https://www.udemy.com/course/the-web-developer-bootcamp/',
            'https://www.udemy.com/course/100-days-of-code/',
            'https://www.udemy.com/course/python-the-complete-python-developer-course/',
        ];
        
        // Ограничиваем количество курсов для демонстрации
        $courseUrls = array_slice($courseUrls, 0, 3);
        
        foreach ($courseUrls as $index => $url) {
            echo "Обрабатываем курс " . ($index + 1) . "/" . count($courseUrls) . ": {$url}\n";
            
            $courseData = getCourseData($driver, $url);
            
            if ($courseData) {
                $all_courses_data[] = $courseData;
                echo "Данные о курсе успешно получены\n";
            } else {
                echo "Не удалось получить данные о курсе\n";
            }
            
            echo "------------------------\n";
            
            // Делаем паузу между запросами
            randomSleep();
            
            // Сохраняем промежуточные результаты
            if (count($all_courses_data) > 0) {
                saveIntermediateResults($all_courses_data);
            }
        }
        
        echo "------------------------\n";
        echo "Метод 2: Сбор курсов по категориям\n";
        echo "------------------------\n";
        
        // Список категорий для парсинга
        $categories = [
            'development' => 'https://www.udemy.com/courses/development/',
            'business' => 'https://www.udemy.com/courses/business/',
            'it-and-software' => 'https://www.udemy.com/courses/it-and-software/',
        ];
        
        // Ограничиваем количество категорий для демонстрации
        $categories = array_slice($categories, 0, 2, true);
        
        foreach ($categories as $categoryName => $categoryUrl) {
            echo "Обрабатываем категорию: {$categoryName}\n";
            
            $courseLinks = getCoursesFromCategory($driver, $categoryUrl);
            
            // Ограничиваем количество курсов для обработки
            $courseLinks = array_slice($courseLinks, 0, 2);
            
            if (!empty($courseLinks)) {
                foreach ($courseLinks as $index => $link) {
                    echo "Обрабатываем курс " . ($index + 1) . "/" . count($courseLinks) . " из категории {$categoryName}: {$link}\n";
                    
                    $courseData = getCourseData($driver, $link);
                    
                    if ($courseData) {
                        $courseData['category'] = $categoryName;
                        $all_courses_data[] = $courseData;
                        echo "Данные о курсе успешно получены\n";
                    } else {
                        echo "Не удалось получить данные о курсе\n";
                    }
                    
                    echo "------------------------\n";
                    
                    // Делаем паузу между запросами
                    randomSleep();
                    
                    // Сохраняем промежуточные результаты
                    if (count($all_courses_data) > 0) {
                        saveIntermediateResults($all_courses_data);
                    }
                }
            } else {
                echo "Не удалось получить ссылки на курсы из категории {$categoryName}\n";
            }
            
            // Делаем паузу между категориями
            randomSleep(5, 8);
        }
        
    } catch (Exception $e) {
        echo "Произошла критическая ошибка: {$e->getMessage()}\n";
        echo "Трассировка: {$e->getTraceAsString()}\n";
    } finally {
        // Закрываем браузер
        $driver->quit();
        
        // Сохраняем окончательные результаты
        if (count($all_courses_data) > 0) {
            $output_file = 'udemy_courses.json';
            file_put_contents($output_file, json_encode($all_courses_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo "Данные успешно сохранены в файл: {$output_file}\n";
            echo "Всего собрано курсов: " . count($all_courses_data) . "\n";
        } else {
            echo "Не удалось собрать данные о курсах\n";
        }
    }
}

// Запускаем парсер
main();