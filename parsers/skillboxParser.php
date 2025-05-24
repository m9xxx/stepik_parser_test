<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\ClientException;

$client = new Client([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
    ],
    'timeout' => 30,
    'verify' => false, // Отключаем проверку SSL для отладки, в продакшене включить
]);

$all_courses_data = []; // Массив для хранения данных всех курсов

// Функция для получения списка URL курсов с помощью разных методов
function getCourseUrls($client) {
    $courseUrls = [];
    
    // Метод 1: Получаем курсы из категорий
    $courseUrls = array_merge($courseUrls, getCourseUrlsFromCategories($client));
    
    // Метод 2: Проверяем XML карту сайта
    $courseUrls = array_merge($courseUrls, getCourseUrlsFromXmlSitemap($client));
    
    // Метод 3: Проверка специальных курсов из robots.txt
    $specialCourses = [
        'https://skillbox.ru/course/qualitative-research/',
        'https://skillbox.ru/course/quantitative-research/'
    ];
    $courseUrls = array_merge($courseUrls, $specialCourses);
    
    // Удаляем дубликаты
    $courseUrls = array_unique($courseUrls);
    
    echo "Всего найдено " . count($courseUrls) . " уникальных URL курсов\n";
    return $courseUrls;
}

// Получение URL курсов из категорий
function getCourseUrlsFromCategories($client) {
    $categories = [
        'programming' => 'https://skillbox.ru/code/',
        'design' => 'https://skillbox.ru/design/',
        'marketing' => 'https://skillbox.ru/marketing/',
        'management' => 'https://skillbox.ru/management/',
        'analytics' => 'https://skillbox.ru/analytics/',
    ];
    
    $courseUrls = [];
    
    foreach ($categories as $name => $url) {
        echo "Получение курсов из категории: $name ($url)\n";
        
        try {
            $response = $client->get($url);
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);
            
            // Поиск карточек курсов (пробуем разные селекторы)
            $selectors = [
                '.sb-courseCard a[href*="/course/"]', // Новый селектор
                '.course-card a[href*="/course/"]',   // Старый селектор
                'a[href*="/course/"]',                // Общий селектор
            ];
            
            foreach ($selectors as $selector) {
                $crawler->filter($selector)->each(function ($node) use (&$courseUrls) {
                    $href = $node->attr('href');
                    if (strpos($href, '/course/') !== false) {
                        // Если ссылка не начинается с https://, добавляем базовый URL
                        if (strpos($href, 'https://') !== 0) {
                            $href = 'https://skillbox.ru' . $href;
                        }
                        $courseUrls[] = $href;
                    }
                });
                
                // Если найдены курсы с текущим селектором, прерываем цикл
                if (count($courseUrls) > 0) {
                    break;
                }
            }
            
            // Для каждой категории проверяем наличие пагинации
            $paginationCheck = $crawler->filter('[class*="pagination"], .pagination, [class*="Pagination"]');
            if ($paginationCheck->count() > 0) {
                // Проверяем страницы с пагинацией (до 5 страниц)
                for ($page = 2; $page <= 5; $page++) {
                    $pageUrl = "{$url}?PAGEN_1={$page}";
                    echo "Обработка страницы категории: {$pageUrl}\n";
                    
                    try {
                        $pageResponse = $client->get($pageUrl);
                        $pageHtml = (string) $pageResponse->getBody();
                        $pageCrawler = new Crawler($pageHtml);
                        
                        // Проверяем те же селекторы
                        foreach ($selectors as $selector) {
                            $pageCrawler->filter($selector)->each(function ($node) use (&$courseUrls) {
                                $href = $node->attr('href');
                                if (strpos($href, '/course/') !== false) {
                                    if (strpos($href, 'https://') !== 0) {
                                        $href = 'https://skillbox.ru' . $href;
                                    }
                                    $courseUrls[] = $href;
                                }
                            });
                            
                            // Если найдены курсы с текущим селектором, прерываем цикл
                            if (count($courseUrls) > 0) {
                                break;
                            }
                        }
                        
                        sleep(1); // Задержка между запросами страниц
                    } catch (Exception $e) {
                        echo "Ошибка при получении страницы {$pageUrl}: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            sleep(1); // Задержка между запросами категорий
        } catch (Exception $e) {
            echo "Ошибка при получении курсов из категории {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Найдено " . count($courseUrls) . " URL курсов из категорий\n";
    return $courseUrls;
}

// Получение URL курсов из XML карты сайта
function getCourseUrlsFromXmlSitemap($client) {
    $possibleSitemaps = [
        'https://skillbox.ru/sitemap.xml',
        'https://skillbox.ru/sitemap_index.xml',
        'https://skillbox.ru/sitemap/sitemap.xml',
    ];
    
    $courseUrls = [];
    
    foreach ($possibleSitemaps as $sitemapUrl) {
        echo "Проверка карты сайта: $sitemapUrl\n";
        
        try {
            $response = $client->get($sitemapUrl);
            $xml = (string) $response->getBody();
            
            // Ищем URL курсов в XML
            $crawler = new Crawler($xml);
            $urlNodes = $crawler->filterXPath('//url/loc');
            
            $urlNodes->each(function ($node) use (&$courseUrls) {
                $url = $node->text();
                if (strpos($url, '/course/') !== false) {
                    $courseUrls[] = $url;
                }
            });
            
            // Проверяем, является ли это индексом sitemap
            $sitemapNodes = $crawler->filterXPath('//sitemap/loc');
            
            if ($sitemapNodes->count() > 0) {
                echo "Найден индекс карт сайта, проверяем подкарты...\n";
                
                $sitemapNodes->each(function ($node) use (&$courseUrls, $client) {
                    $subSitemapUrl = $node->text();
                    echo "Проверка подкарты: $subSitemapUrl\n";
                    
                    try {
                        $subResponse = $client->get($subSitemapUrl);
                        $subXml = (string) $subResponse->getBody();
                        
                        $subCrawler = new Crawler($subXml);
                        $subUrlNodes = $subCrawler->filterXPath('//url/loc');
                        
                        $subUrlNodes->each(function ($subNode) use (&$courseUrls) {
                            $url = $subNode->text();
                            if (strpos($url, '/course/') !== false) {
                                $courseUrls[] = $url;
                            }
                        });
                        
                        sleep(1); // Задержка между запросами подкарт
                    } catch (Exception $e) {
                        echo "Ошибка при получении подкарты {$subSitemapUrl}: " . $e->getMessage() . "\n";
                    }
                });
            }
            
            // Если нашли хотя бы один URL, прерываем проверку других возможных sitemap
            if (count($courseUrls) > 0) {
                break;
            }
            
            sleep(1); // Задержка между запросами карт сайта
        } catch (Exception $e) {
            echo "Ошибка при получении карты сайта {$sitemapUrl}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Найдено " . count($courseUrls) . " URL курсов из XML карты сайта\n";
    return $courseUrls;
}

// Функция для парсинга данных курса (улучшенная версия)
function parseCourseData($client, $url) {
    try {
        $response = $client->get($url);
        $html = (string) $response->getBody();
        $crawler = new Crawler($html);
        
        // Извлекаем информацию о курсе
        $title = 'N/A';
        $titleSelectors = ['h1', '.course-title', '.hero__title', '.sb-courseHeader__title'];
        foreach ($titleSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $title = trim($crawler->filter($selector)->text());
                break;
            }
        }
        
        // Описание курса (может находиться в разных элементах)
        $description = 'N/A';
        $descriptionSelectors = [
            '.hero__subtitle',
            '.course-description',
            '.sb-courseHeader__subtitle',
            '.sb-courseInfo__description',
            'meta[name="description"]'
        ];
        
        foreach ($descriptionSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                if ($selector === 'meta[name="description"]') {
                    $description = $crawler->filter($selector)->attr('content');
                } else {
                    $description = trim($crawler->filter($selector)->text());
                }
                break;
            }
        }
        
        // Рейтинг (если есть)
        $rating = 'N/A';
        $ratingSelectors = ['.course-rating__value', '.sb-courseRating__value', '[data-qa="course-rating"]'];
        foreach ($ratingSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $rating = trim($crawler->filter($selector)->text());
                break;
            }
        }
        
        // Цена курса
        $price = 'N/A';
        $priceSelectors = [
            '.price__value',
            '.course-price__actual',
            '.sb-coursePrice__actual',
            '[data-qa="course-price"]'
        ];
        foreach ($priceSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $price = trim($crawler->filter($selector)->text());
                break;
            }
        }
        
        // Длительность курса
        $duration = 'N/A';
        $durationSelectors = [
            '.course-params__value:contains("месяц")',
            '.sb-courseParams__value:contains("месяц")',
            '[data-qa="course-duration"]'
        ];
        foreach ($durationSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $duration = trim($crawler->filter($selector)->text());
                break;
            }
        }

        // Проверка наличия JSON-LD (структурированных данных)
        $jsonLdScripts = $crawler->filter('script[type="application/ld+json"]');
        if ($jsonLdScripts->count() > 0) {
            $jsonLdScripts->each(function ($node) use (&$title, &$description, &$rating, &$price) {
                try {
                    $data = json_decode($node->text(), true);
                    if (isset($data['@type']) && in_array($data['@type'], ['Course', 'Product'])) {
                        if (isset($data['name']) && $title == 'N/A') {
                            $title = $data['name'];
                        }
                        if (isset($data['description']) && $description == 'N/A') {
                            $description = $data['description'];
                        }
                        if (isset($data['aggregateRating']['ratingValue']) && $rating == 'N/A') {
                            $rating = $data['aggregateRating']['ratingValue'];
                        }
                        if (isset($data['offers']['price']) && $price == 'N/A') {
                            $price = $data['offers']['price'];
                        }
                    }
                } catch (Exception $e) {
                    // Игнорируем ошибки декодирования JSON
                }
            });
        }
        
        // Проверяем наличие данных в метатегах
        if ($crawler->filter('meta[property="og:title"]')->count() > 0 && $title == 'N/A') {
            $title = $crawler->filter('meta[property="og:title"]')->attr('content');
        }
        
        if ($crawler->filter('meta[property="og:description"]')->count() > 0 && $description == 'N/A') {
            $description = $crawler->filter('meta[property="og:description"]')->attr('content');
        }
        
        // Извлекаем ID курса из URL
        $id = 'N/A';
        if (preg_match('/\/course\/([^\/\?]+)/', $url, $matches)) {
            $id = $matches[1];
        }
        
        // Проверяем, есть ли в HTML скрытые данные с ID курса
        $dataIdSelectors = ['[data-course-id]', '[data-id]', '[data-product-id]'];
        foreach ($dataIdSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $dataAttribute = str_replace(['[', ']'], '', explode('=', $selector)[0]);
                $dataId = $crawler->filter($selector)->attr($dataAttribute);
                if (!empty($dataId)) {
                    $id = $dataId;
                    break;
                }
            }
        }
        
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'rating' => $rating,
            'price' => $price,
            'duration' => $duration,
            'url' => $url,
        ];
    } catch (Exception $e) {
        echo "Ошибка при парсинге курса {$url}: " . $e->getMessage() . "\n";
        return null;
    }
}

// Получаем список URL курсов
$courseUrls = getCourseUrls($client);

// Проверяем наличие аргумента ограничения для тестирования
$limit = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : count($courseUrls);
echo "Ограничение на количество обрабатываемых курсов: {$limit}\n";

// Ограничиваем количество курсов для обработки
$courseUrls = array_slice($courseUrls, 0, $limit);

// Создаем файл для хранения данных (с перезаписью) и пишем заголовок
$csv_file = fopen('skillbox_courses.csv', 'w');
fputcsv($csv_file, ['id', 'title', 'description', 'rating', 'price', 'duration', 'url']);

// Проходим по каждому URL и парсим данные
foreach ($courseUrls as $index => $url) {
    echo "Обработка курса " . ($index + 1) . " из " . count($courseUrls) . ": {$url}\n";
    
    $course_data = parseCourseData($client, $url);
    
    if ($course_data) {
        $all_courses_data[] = $course_data;
        
        // Записываем данные в CSV-файл
        fputcsv($csv_file, [
            $course_data['id'],
            $course_data['title'],
            $course_data['description'],
            $course_data['rating'],
            $course_data['price'],
            $course_data['duration'],
            $course_data['url'],
        ]);
        
        echo "Данные для курса {$course_data['id']} успешно добавлены\n";
    } else {
        echo "Не удалось получить данные для курса: {$url}\n";
    }
    
    echo "------------------------\n";
    
    // Делаем случайную паузу между запросами (от 1 до 3 секунд)
    $sleep_time = rand(1, 3);
    echo "Пауза {$sleep_time} сек...\n";
    sleep($sleep_time);
}

// Закрываем CSV-файл
fclose($csv_file);

// Записываем данные в JSON-файл
$json_data = json_encode($all_courses_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/data/skillbox_courses.json', $json_data);

echo "Всего обработано курсов: " . count($all_courses_data) . "\n";
echo "Данные сохранены в файлы 'skillbox_courses.json' и 'skillbox_courses.csv'\n";

// Функция для преобразования относительного URL в абсолютный
function makeAbsoluteUrl($url, $baseUrl = 'https://skillbox.ru') {
    if (empty($url)) {
        return null;
    }
    
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    
    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}