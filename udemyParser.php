<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Парсер для получения данных о курсах с Udemy
 * 
 * Этот скрипт собирает информацию о курсах на платформе Udemy,
 * используя разные стратегии доступа к данным, включая прямые запросы
 * к страницам курсов и использование доступных публичных API
 */

// Функция для сохранения промежуточных результатов
function saveIntermediateResults($data, $filename = 'udemy_courses_temp.json') {
    file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "Промежуточные данные сохранены в файл: {$filename}\n";
}

// Функция для генерации случайной задержки
function randomSleep($min = 5, $max = 10) {
    $delay = rand($min * 10, $max * 10) / 10;
    echo "Ожидание {$delay} секунд...\n";
    usleep($delay * 1000000);
}

// Функция для получения данных о курсе через JavaScript API
function getCourseDataFromApi($courseId, $client) {
    try {
        $url = "https://www.udemy.com/api-2.0/courses/{$courseId}/?fields[course]=title,headline,description,rating,url";
        
        $response = $client->get($url);
        $data = json_decode((string) $response->getBody(), true);
        
        if (isset($data['title'])) {
            return [
                'id' => $courseId,
                'title' => $data['title'],
                'description' => isset($data['description']) ? $data['description'] : (isset($data['headline']) ? $data['headline'] : 'N/A'),
                'rating' => isset($data['rating']) ? $data['rating'] : 'N/A',
                'url' => "https://www.udemy.com/course/{$courseId}/",
            ];
        }
        
        return null;
    } catch (Exception $e) {
        echo "Ошибка при получении данных через API для курса {$courseId}: " . $e->getMessage() . "\n";
        return null;
    }
}

// Функция для извлечения идентификатора курса из URL
function extractCourseId($url) {
    // Формат URL: https://www.udemy.com/course/course-name/
    $pattern = '/\/course\/([^\/]+)/';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    return null;
}

// Функция для получения данных из страницы курса
function scrapeCoursePage($url, $client) {
    try {
        $response = $client->get($url, [
            'allow_redirects' => true,
        ]);
        
        $html = (string) $response->getBody();
        $crawler = new Crawler($html);
        
        // Поиск данных о курсе на странице
        $title = 'N/A';
        $description = 'N/A';
        $rating = 'N/A';
        
        // Попытка получить данные из JSON-LD
        $jsonLdElements = $crawler->filter('script[type="application/ld+json"]');
        if ($jsonLdElements->count() > 0) {
            foreach ($jsonLdElements as $element) {
                $jsonData = json_decode($element->textContent, true);
                if (isset($jsonData['@type']) && $jsonData['@type'] === 'Course') {
                    $title = isset($jsonData['name']) ? $jsonData['name'] : $title;
                    $description = isset($jsonData['description']) ? $jsonData['description'] : $description;
                    if (isset($jsonData['aggregateRating']['ratingValue'])) {
                        $rating = $jsonData['aggregateRating']['ratingValue'];
                    }
                    break;
                }
            }
        }
        
        // Если не удалось получить данные из JSON-LD, пробуем получить из DOM
        if ($title === 'N/A') {
            // Заголовок курса
            $titleElement = $crawler->filter('h1');
            if ($titleElement->count() > 0) {
                $title = trim($titleElement->text());
            }
            
            // Описание курса
            $descElement = $crawler->filter('meta[name="description"]');
            if ($descElement->count() > 0) {
                $description = $descElement->attr('content');
            }
            
            // Рейтинг курса
            $ratingElement = $crawler->filter('.star-rating-numeric');
            if ($ratingElement->count() > 0) {
                $rating = trim($ratingElement->text());
            }
        }
        
        // Если данные найдены, возвращаем их
        if ($title !== 'N/A' || $description !== 'N/A') {
            $courseId = extractCourseId($url);
            return [
                'id' => $courseId,
                'title' => $title,
                'description' => $description,
                'rating' => $rating,
                'url' => $url,
            ];
        }
        
        return null;
    } catch (Exception $e) {
        echo "Ошибка при парсинге страницы курса {$url}: " . $e->getMessage() . "\n";
        return null;
    }
}

// Главная функция
function main() {
    // Создаем Cookie Jar для сохранения кук между запросами
    $cookieJar = new CookieJar();
    
    // Более полный набор заголовков, имитирующих обычный браузер
    $client = new Client([
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Cache-Control' => 'max-age=0',
            'Sec-Ch-Ua' => '"Google Chrome";v="119", "Chromium";v="119", "Not?A_Brand";v="24"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Referer' => 'https://www.udemy.com/',
        ],
        'cookies' => $cookieJar,
        'verify' => false, // Отключаем проверку SSL, если нужно
        'timeout' => 30.0,
        'connect_timeout' => 30.0,
        'http_errors' => false, // Отключаем исключения для HTTP ошибок
    ]);
    
    $all_courses_data = [];
    
    // Сначала посетим главную страницу, чтобы получить первичные куки
    try {
        echo "Посещение главной страницы для инициализации сессии...\n";
        $response = $client->get('https://www.udemy.com/');
        echo "Статус ответа: " . $response->getStatusCode() . "\n";
        
        if ($response->getStatusCode() == 200) {
            echo "Успешно получена главная страница. Куки установлены.\n";
        } else {
            echo "Предупреждение: Получен нестандартный статус при доступе к главной странице.\n";
        }
        
        // Дадим время для обработки потенциальной защиты от ботов
        randomSleep(3, 5);
        
    } catch (Exception $e) {
        echo "Ошибка при посещении главной страницы: " . $e->getMessage() . "\n";
        echo "Продолжаем выполнение...\n";
    }
    
    echo "------------------------\n";
    echo "Метод 1: Использование доступных API\n";
    echo "------------------------\n";
    
    // Попробуем получить данные о курсах напрямую через API
    $courseIds = [
        '1430746', // Python для начинающих
        '567828',  // 2020 Complete Web Development Bootcamp
        '625204',  // Machine Learning A-Z
        '762616',  // Angular - The Complete Guide
        '1565838', // The Complete 2023 Web Development Bootcamp
        '2776760', // 100 Days of Code: The Complete Python Pro Bootcamp
        '1565838', // The Complete 2023 Web Development Bootcamp
        '1362070', // React - The Complete Guide
        '995016',  // JavaScript - The Complete Guide 2023
        '993906',  // Complete Java Masterclass
    ];
    
    echo "Получаем данные о курсах через API...\n";
    
    foreach ($courseIds as $index => $courseId) {
        echo "Обрабатываем курс ID: {$courseId} (" . ($index + 1) . "/" . count($courseIds) . ")\n";
        
        $courseData = getCourseDataFromApi($courseId, $client);
        
        if ($courseData) {
            $all_courses_data[] = $courseData;
            echo "Данные о курсе {$courseId} успешно получены.\n";
        } else {
            echo "Не удалось получить данные о курсе {$courseId} через API.\n";
            
            // Попробуем получить данные через прямой парсинг страницы
            $courseUrl = "https://www.udemy.com/course/{$courseId}/";
            echo "Пробуем получить данные через парсинг страницы: {$courseUrl}\n";
            
            $courseData = scrapeCoursePage($courseUrl, $client);
            
            if ($courseData) {
                $all_courses_data[] = $courseData;
                echo "Данные о курсе {$courseId} успешно получены через парсинг страницы.\n";
            } else {
                echo "Не удалось получить данные о курсе {$courseId} ни через API, ни через парсинг.\n";
            }
        }
        
        echo "------------------------\n";
        
        // Делаем случайную задержку между запросами
        randomSleep();
        
        // Сохраняем промежуточные результаты после каждых 3 курсов
        if (($index + 1) % 3 == 0) {
            saveIntermediateResults($all_courses_data);
        }
    }
    
    echo "------------------------\n";
    echo "Метод 2: Парсинг популярных курсов\n";
    echo "------------------------\n";
    
    // Массив URL популярных категорий
    $categories = [
        'development' => 'https://www.udemy.com/courses/development/',
        'business' => 'https://www.udemy.com/courses/business/',
        'design' => 'https://www.udemy.com/courses/design/',
        'marketing' => 'https://www.udemy.com/courses/marketing/',
        'it-and-software' => 'https://www.udemy.com/courses/it-and-software/',
    ];
    
    foreach ($categories as $categoryName => $categoryUrl) {
        echo "Обрабатываем категорию: {$categoryName}\n";
        
        try {
            $response = $client->get($categoryUrl);
            
            if ($response->getStatusCode() == 200) {
                $html = (string) $response->getBody();
                $crawler = new Crawler($html);
                
                // Ищем ссылки на курсы
                $courseLinks = $crawler->filter('a[href*="/course/"]');
                
                if ($courseLinks->count() > 0) {
                    echo "Найдено ссылок на курсы: " . $courseLinks->count() . "\n";
                    
                    // Ограничимся первыми 2 курсами из каждой категории
                    $processedLinks = [];
                    $coursesProcessed = 0;
                    
                    foreach ($courseLinks as $link) {
                        $href = $link->getAttribute('href');
                        
                        // Проверяем, что это ссылка на курс
                        if (preg_match('/\/course\/[^\/]+\/?/', $href)) {
                            $courseUrl = 'https://www.udemy.com' . $href;
                            
                            // Избегаем дублирования ссылок
                            if (in_array($courseUrl, $processedLinks)) {
                                continue;
                            }
                            
                            $processedLinks[] = $courseUrl;
                            
                            echo "Обрабатываем курс: {$courseUrl}\n";
                            
                            $courseData = scrapeCoursePage($courseUrl, $client);
                            
                            if ($courseData) {
                                // Добавляем категорию к данным курса
                                $courseData['category'] = $categoryName;
                                
                                // Добавляем данные в общий массив
                                $all_courses_data[] = $courseData;
                                echo "Данные о курсе успешно получены.\n";
                            } else {
                                echo "Не удалось получить данные о курсе.\n";
                            }
                            
                            echo "------------------------\n";
                            
                            $coursesProcessed++;
                            
                            // Делаем случайную задержку между запросами
                            randomSleep();
                            
                            // Ограничиваем количество курсов для обработки
                            if ($coursesProcessed >= 2) {
                                break;
                            }
                        }
                    }
                } else {
                    echo "Не найдено ссылок на курсы в категории {$categoryName}\n";
                }
            } else {
                echo "Не удалось получить доступ к странице категории: " . $response->getStatusCode() . "\n";
            }
        } catch (Exception $e) {
            echo "Ошибка при обработке категории {$categoryName}: " . $e->getMessage() . "\n";
        }
        
        echo "------------------------\n";
        
        // Сохраняем промежуточные результаты после каждой категории
        saveIntermediateResults($all_courses_data);
        
        // Делаем случайную задержку между категориями
        randomSleep(8, 15);
    }
    
    // Проверяем, что мы собрали какие-то данные
    if (empty($all_courses_data)) {
        echo "Не удалось собрать данные о курсах.\n";
        exit(1);
    }
    
    // Сохраняем финальные результаты
    $output_file = 'udemy_courses.json';
    file_put_contents($output_file, json_encode($all_courses_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo "Данные успешно сохранены в файл: {$output_file}\n";
    echo "Всего собрано курсов: " . count($all_courses_data) . "\n";
}

// Запускаем основную функцию
main();