<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\ClientException;

// Отключаем предупреждения об SSL-сертификатах
error_reporting(E_ALL & ~E_WARNING);

// Настройки
$sleep_between_requests = 2; // Задержка между запросами в секундах
$fetch_course_details = true; // Собирать ли детальную информацию со страниц курсов

$client = new Client([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ],
    'verify' => false, // Отключаем проверку SSL-сертификата
]);
$all_courses_data = [];

// Путь к файлу данных
$dataFile = __DIR__ . '/data/geekbrains_courses.json';

// Функция для получения детальной информации о курсе
function getCourseDetails($url, $courseData) {
    global $client, $sleep_between_requests;
    
    echo "Получение детальной информации о курсе: {$url}\n";
    
    try {
        // Добавляем задержку между запросами
        sleep($sleep_between_requests);
        
        $response = $client->get($url);
        $html = (string) $response->getBody();
        $crawler = new Crawler($html);
        
        // Сначала ищем данные в JSON-LD структуре (schema.org)
        $jsonLdScripts = $crawler->filter('script[type="application/ld+json"]');
        $ratingFound = false;
        
        if ($jsonLdScripts->count() > 0) {
            foreach ($jsonLdScripts as $script) {
                $jsonText = $script->textContent;
                $jsonData = json_decode($jsonText, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Проверяем наличие рейтинга
                    if (isset($jsonData['aggregateRating']['ratingValue'])) {
                        $courseData['rating'] = $jsonData['aggregateRating']['ratingValue'];
                        $ratingFound = true;
                    }
                    
                    // Проверяем количество отзывов
                    if (isset($jsonData['aggregateRating']['ratingCount'])) {
                        $courseData['reviews_count'] = $jsonData['aggregateRating']['ratingCount'];
                        $ratingFound = true;
                    }
                    
                    // Получаем более подробное описание, если есть
                    if (isset($jsonData['description']) && strlen($jsonData['description']) > strlen($courseData['description'])) {
                        $courseData['full_description'] = $jsonData['description'];
                    }
                }
            }
        }
        
        // Если не нашли в JSON-LD, ищем в HTML
        if (!$ratingFound) {
            // Пытаемся найти информацию о рейтинге в HTML
            if ($crawler->filter('.course-header__rating-value')->count() > 0) {
                $courseData['rating'] = trim($crawler->filter('.course-header__rating-value')->text());
            } else if ($crawler->filter('.rating__value')->count() > 0) {
                $courseData['rating'] = trim($crawler->filter('.rating__value')->text());
            } else {
                $courseData['rating'] = 'N/A';
            }
            
            // Количество отзывов из HTML
            if ($crawler->filter('.course-header__rating-count')->count() > 0) {
                $courseData['reviews_count'] = trim($crawler->filter('.course-header__rating-count')->text());
                // Очищаем от скобок и прочих символов
                $courseData['reviews_count'] = preg_replace('/[^0-9]/', '', $courseData['reviews_count']);
            } else if ($crawler->filter('.rating__count')->count() > 0) {
                $courseData['reviews_count'] = trim($crawler->filter('.rating__count')->text());
                $courseData['reviews_count'] = preg_replace('/[^0-9]/', '', $courseData['reviews_count']);
            } else {
                $courseData['reviews_count'] = 'N/A';
            }
        }
        
        // Программа курса (темы/модули)
        $modules = [];
        if ($crawler->filter('.course-program__item')->count() > 0) {
            $crawler->filter('.course-program__item')->each(function (Crawler $node) use (&$modules) {
                $title = $node->filter('.course-program__title')->count() > 0 ? 
                       trim($node->filter('.course-program__title')->text()) : 'N/A';
                
                $description = '';
                if ($node->filter('.course-program__desc')->count() > 0) {
                    $description = trim($node->filter('.course-program__desc')->text());
                }
                
                $modules[] = [
                    'title' => $title,
                    'description' => $description
                ];
            });
        } elseif ($crawler->filter('.program__module-title')->count() > 0) {
            // Альтернативная структура модулей
            $crawler->filter('.program__module')->each(function (Crawler $node) use (&$modules) {
                $title = $node->filter('.program__module-title')->count() > 0 ? 
                       trim($node->filter('.program__module-title')->text()) : 'N/A';
                
                $description = '';
                $topics = [];
                
                if ($node->filter('.program__module-topic')->count() > 0) {
                    $node->filter('.program__module-topic')->each(function (Crawler $topicNode) use (&$topics) {
                        $topics[] = trim($topicNode->text());
                    });
                    
                    $description = implode('; ', $topics);
                }
                
                $modules[] = [
                    'title' => $title,
                    'description' => $description,
                    'topics' => $topics
                ];
            });
        }
        
        if (!empty($modules)) {
            $courseData['modules'] = $modules;
        }
        
        // Пробуем найти дополнительную информацию о курсе
        // Целевая аудитория
        if ($crawler->filter('.audience__list-item')->count() > 0) {
            $audience = [];
            $crawler->filter('.audience__list-item')->each(function (Crawler $node) use (&$audience) {
                $audience[] = trim($node->text());
            });
            $courseData['target_audience'] = $audience;
        } elseif ($crawler->filter('.audience-list__item')->count() > 0) {
            $audience = [];
            $crawler->filter('.audience-list__item')->each(function (Crawler $node) use (&$audience) {
                $audience[] = trim($node->text());
            });
            $courseData['target_audience'] = $audience;
        }
        
        // Требования к курсу
        if ($crawler->filter('.course-requirements__item')->count() > 0) {
            $requirements = [];
            $crawler->filter('.course-requirements__item')->each(function (Crawler $node) use (&$requirements) {
                $requirements[] = trim($node->text());
            });
            $courseData['requirements'] = $requirements;
        } elseif ($crawler->filter('.requirement__item')->count() > 0) {
            $requirements = [];
            $crawler->filter('.requirement__item')->each(function (Crawler $node) use (&$requirements) {
                $requirements[] = trim($node->text());
            });
            $courseData['requirements'] = $requirements;
        }
        
        // Форматы обучения
        if ($crawler->filter('.gb-tabs__tab')->count() > 0) {
            $formats = [];
            $crawler->filter('.gb-tabs__tab')->each(function (Crawler $node) use (&$formats) {
                $format = trim($node->text());
                if (!empty($format)) {
                    $formats[] = $format;
                }
            });
            if (!empty($formats)) {
                $courseData['formats'] = $formats;
            }
        }
        
        echo "Детальная информация о курсе получена\n";
        
    } catch (Exception $e) {
        echo "Ошибка при получении детальной информации о курсе: " . $e->getMessage() . "\n";
    }
    
    return $courseData;
}

// Чтение существующих данных
$existingData = [];
if (file_exists($dataFile)) {
    $existingData = json_decode(file_get_contents($dataFile), true) ?: [];
}

try {
    // Получаем страницу со всеми курсами
    echo "Получение списка всех курсов с https://gb.ru/courses/all\n";
    
    try {
        $response = $client->get('https://gb.ru/courses/all');
        $html = (string) $response->getBody();
    } catch (Exception $e) {
        echo "Ошибка при получении страницы: " . $e->getMessage() . "\n";
        echo "Пробуем альтернативный URL...\n";
        
        // Если основной URL не работает, пробуем альтернативный с www
        $response = $client->get('https://www.gb.ru/courses/all');
        $html = (string) $response->getBody();
    }
    
    $crawler = new Crawler($html);
    
    echo "Страница получена, начинаем извлечение данных...\n";
    
    // Ищем JSON данные о курсах в скрипте application/ld+json
    $jsonScripts = $crawler->filter('script[type="application/ld+json"]');
    $coursesFound = false;
    
    if ($jsonScripts->count() > 0) {
        echo "Найдено {$jsonScripts->count()} блоков JSON. Анализируем...\n";
        
        foreach ($jsonScripts as $script) {
            $jsonText = $script->textContent;
            $jsonData = json_decode($jsonText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "Ошибка декодирования JSON: " . json_last_error_msg() . "\n";
                echo "Пробуем очистить JSON перед декодированием...\n";
                
                // Иногда JSON может содержать управляющие символы или быть неправильно форматированным
                $jsonText = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $jsonText);
                $jsonData = json_decode($jsonText, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "Декодирование по-прежнему не удалось: " . json_last_error_msg() . "\n";
                    continue;
                }
            }
            
            // Проверяем, что это JSON для списка курсов
            if (isset($jsonData['@type']) && $jsonData['@type'] === 'ItemList' && isset($jsonData['itemListElement'])) {
                echo "Найден список курсов, содержащий " . count($jsonData['itemListElement']) . " элементов\n";
                $coursesFound = true;
                
                // Обрабатываем каждый курс из списка
                foreach ($jsonData['itemListElement'] as $item) {
                    if (isset($item['item']) && $item['item']['@type'] === 'Course') {
                        $course = $item['item'];
                        
                        // Извлекаем ID курса из URL
                        $courseId = null;
                        if (isset($course['url']) && preg_match('/\/([^\/]+)$/', $course['url'], $matches)) {
                            $courseId = $matches[1];
                        } else {
                            $courseId = 'unknown-' . uniqid();
                        }
                
                        // Формируем данные о курсе
                        $courseData = [
                            'id' => $courseId,
                            'title' => $course['name'] ?? 'N/A',
                            'description' => $course['description'] ?? 'N/A',
                            'url' => $course['url'] ?? 'N/A',
                            'duration' => isset($course['hasCourseInstance']['courseWorkload']) ? 
                                      str_replace('P', '', $course['hasCourseInstance']['courseWorkload']) : 'N/A',
                            'start_date' => $course['hasCourseInstance']['startDate'] ?? 'N/A',
                            'end_date' => $course['hasCourseInstance']['endDate'] ?? 'N/A',
                            'price' => isset($course['offers']['price']) ? $course['offers']['price'] : 
                                    (isset($course['offers']['lowPrice']) ? $course['offers']['lowPrice'] : 'N/A'),
                            'currency' => isset($course['offers']['priceCurrency']) ? $course['offers']['priceCurrency'] : 'N/A',
                        ];
                        
                        // Получаем рейтинг и количество отзывов из JSON, если доступно
                        if (isset($course['aggregateRating'])) {
                            $courseData['rating'] = $course['aggregateRating']['ratingValue'] ?? 'N/A';
                            $courseData['reviews_count'] = $course['aggregateRating']['ratingCount'] ?? 'N/A';
                        }
                        
                        // Если нужно получить дополнительную информацию со страницы курса
                        if ($fetch_course_details && isset($course['url']) && !empty($course['url'])) {
                            try {
                                $courseUrl = $course['url'];
                                // Проверяем, является ли URL относительным
                                if (strpos($courseUrl, 'http') !== 0) {
                                    $courseUrl = 'https://gb.ru' . $courseUrl;
                                }
                                $courseData = getCourseDetails($courseUrl, $courseData);
                            } catch (Exception $e) {
                                echo "Ошибка при получении детальной информации: " . $e->getMessage() . "\n";
                            }
                        }
                        
                        // Добавляем данные курса в общий массив
                        $all_courses_data[] = $courseData;
                        echo "Данные для курса '{$courseData['title']}' добавлены\n";
                    }
                }
            }
        }
    } else {
        echo "JSON данные о курсах не найдены. Пробуем извлечь из HTML.\n";
        
        // Альтернативный способ: парсинг из HTML-структуры
        $courseCards = $crawler->filter('.direction-card');
        
        foreach ($courseCards as $idx => $card) {
            $cardCrawler = new Crawler($card);
            
            $title = $cardCrawler->filter('.direction-card__title-text')->count() > 0 ? 
                     trim($cardCrawler->filter('.direction-card__title-text')->text()) : 'N/A';
                     
            $description = $cardCrawler->filter('.direction-card__text')->count() > 0 ? 
                          trim($cardCrawler->filter('.direction-card__text')->text()) : 'N/A';
                          
            $url = $cardCrawler->filter('.card_full_link')->count() > 0 ? 
                  $cardCrawler->filter('.card_full_link')->attr('href') : 'N/A';
                  
            // Извлекаем ID курса из URL
            $courseId = null;
            if ($url !== 'N/A' && preg_match('/\/([^\/]+)$/', $url, $matches)) {
                $courseId = $matches[1];
            } else {
                $courseId = 'unknown-' . ($idx + 1);
            }
            
            // Извлекаем срок обучения
            $duration = 'N/A';
            $durationText = $cardCrawler->filter('.direction-card__info-text')->count() > 0 ? 
                          $cardCrawler->filter('.direction-card__info-text')->text() : '';
            if (preg_match('/(\d+)\s+месяц/', $durationText, $matches)) {
                $duration = $matches[1] . 'M';
            }
            
            // Извлекаем скидку если есть
            $discount = null;
            $discountElement = $cardCrawler->filter('.direction-card__info-label');
            if ($discountElement->count() > 0) {
                $discount = trim($discountElement->text());
            }
            
            $courseData = [
                'id' => $courseId,
                'title' => $title,
                'description' => $description,
                'url' => $url !== 'N/A' ? ('https://gb.ru' . $url) : 'N/A',
                'duration' => $duration,
                'discount' => $discount,
            ];
            
            // Если нужно получить дополнительную информацию о курсе
            if ($fetch_course_details && $url !== 'N/A') {
                try {
                    $courseUrl = 'https://gb.ru' . $url;
                    $courseData = getCourseDetails($courseUrl, $courseData);
                } catch (Exception $e) {
                    echo "Ошибка при получении детальной информации: " . $e->getMessage() . "\n";
                }
            }
            
            $all_courses_data[] = $courseData;
            echo "Данные для курса '{$title}' добавлены из HTML\n";
        }
    }
    
    // Если не удалось найти курсы ни одним из способов
    if (empty($all_courses_data)) {
        echo "Не удалось извлечь информацию о курсах. Проверьте структуру HTML.\n";
    }
    
} catch (ClientException $e) {
    echo "Ошибка при получении страницы: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Общая ошибка: " . $e->getMessage() . "\n";
}

// Фильтрация новых курсов
$newCourses = [];
foreach ($all_courses_data as $newCourse) {
    if (empty($newCourse['id'])) {
        continue; // Пропускаем курсы без ID
    }
    
    $isDuplicate = false;
    foreach ($existingData as $existingCourse) {
        if (isset($existingCourse['id']) && $newCourse['id'] == $existingCourse['id']) {
            $isDuplicate = true;
            break;
        }
    }
    if (!$isDuplicate) {
        $newCourses[] = $newCourse;
    }
}

// Объединение данных
$combinedData = array_merge($existingData, $newCourses);

// Создаем директорию, если она не существует
if (!is_dir(dirname($dataFile))) {
    mkdir(dirname($dataFile), 0755, true);
}

// Сохранение
file_put_contents(
    $dataFile,
    json_encode($combinedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "Добавлено новых курсов: " . count($newCourses) . "\n";
echo "Всего курсов в файле: " . count($combinedData) . "\n";

// Статистика по обработке
echo "\n=== Статистика ===\n";
echo "Курсов обработано за этот запуск: " . count($all_courses_data) . "\n";
$ratingAvailable = 0;
foreach ($all_courses_data as $course) {
    if (isset($course['rating']) && $course['rating'] !== 'N/A') {
        $ratingAvailable++;
    }
}
echo "Курсов с информацией о рейтинге: {$ratingAvailable}\n";