<?php
require 'vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Models/CourseDB.php';
require_once dirname(__DIR__) . '/app/Models/Platform.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\ClientException;

// Путь к актуальному файлу CA-сертификатов
// $caPath = __DIR__ . '/cacert.pem'; // Файл нужно скачать с https://curl.se/ca/cacert.pem

$client = new Client([
    'verify' => false, // Быстрое решение для отключения проверки SSL (не рекомендуется для production)
    'headers' => [
        'User-Agent' => 'StepikCourseBot/1.0 (education_research; your@email.com)'
    ]
]);

$typical_stub_description = "Образовательная платформа — Stepik. Выберите подходящий вам онлайн-курс из более чем 20 тысяч и начните получать востребованные навыки.";
$typical_stub_title = "Stepik";

// Путь к файлу данных
$dataFile = dirname(__DIR__) . '/data/stepik_courses.json';

// Статистика
$processed = 0;
$added = 0;
$skipped = 0;
$skippedEnded = 0;
$skippedStub = 0;
$skippedError = 0;

// Чтение существующих данных
// $existingData = [];
// if (file_exists($dataFile)) {
//     $existingData = json_decode(file_get_contents($dataFile), true) ?: [];
// }

// Создаем индекс существующих ID для быстрого поиска
// $existingIds = [];
// foreach ($existingData as $course) {
//     $existingIds[$course['id']] = true;
// }

function isStubCourse($title, $description, $rating) {
    global $typical_stub_description, $typical_stub_title;

    $is_stub = false;

    if (stripos($title, $typical_stub_title) !== false && stripos($description, $typical_stub_description) !== false) {
        $is_stub = true;
    }

    if ($rating == 'N/A' && stripos($title, $typical_stub_title) !== false) {
        $is_stub = true;
    }

    return $is_stub;
}

function isCourseEnded($html) {
    // Проверяем несколько вариантов определения завершившегося курса
    
    // Вариант 1: Поиск класса course-promo-enrollment__course-ended-warn
    if (strpos($html, 'course-promo-enrollment__course-ended-warn') !== false) {
        return true;
    }
    
    // Вариант 2: Поиск текста "Завершился" рядом с датой
    if (preg_match('/[Зз]авершился\s+(\d+|[а-яА-Я]+)\s+(дн|лет|год|недел)/ui', $html)) {
        return true;
    }
    
    // Вариант 3: Проверка через JSON данные в scripts
    if (preg_match('/"is_active":\s*false/i', $html) && preg_match('/"is_archived":\s*true/i', $html)) {
        return true;
    }
    
    // Вариант 4: Проверка через shoebox-main-store
    if (preg_match('/<script id="shoebox-main-store" type="fastboot\/shoebox">(.*?)<\/script>/s', $html, $matches)) {
        $jsonData = json_decode($matches[1], true);
        
        if (isset($jsonData['records']['course']['courses'][0])) {
            $courseData = $jsonData['records']['course']['courses'][0];
            
            // Проверяем статус активности/архивности
            if (isset($courseData['is_active']) && $courseData['is_active'] === false) {
                return true;
            }
            
            if (isset($courseData['is_archived']) && $courseData['is_archived'] === true) {
                return true;
            }
        }
    }
    
    // Вариант 5: Проверка через __stepik_shoebox__
    if (preg_match('/__stepik_shoebox__ = JSON\.parse\(\'(.*?)\'\)/s', $html, $matches)) {
        $escapedJson = stripcslashes($matches[1]);
        $jsonData = json_decode($escapedJson, true);
        
        if (isset($jsonData['courses'][0])) {
            $courseData = $jsonData['courses'][0];
            
            if ((isset($courseData['is_active']) && $courseData['is_active'] === false) || 
                (isset($courseData['is_archived']) && $courseData['is_archived'] === true)) {
                return true;
            }
        }
    }
    
    return false;
}

function testCoursePriceExtraction($courseId) {
    global $client;
    
    echo "=== ТЕСТИРОВАНИЕ ПАРСИНГА ЦЕНЫ КУРСА ID: $courseId ===\n";
    
    try {
        $courseUrl = "https://stepik.org/course/{$courseId}/promo";
        $response = $client->get($courseUrl);
        $html = (string) $response->getBody();
        
        // Получаем базовую информацию о курсе
        $crawler = new Crawler($html);
        $title = $crawler->filter('title')->count() > 0 
            ? trim(str_replace(' — Stepik', '', $crawler->filter('title')->text())) 
            : 'Название не найдено';
        
        echo "Название курса: $title\n\n";
        
        // Прямая проверка - есть ли шаблоны стоимости в HTML
        $priceSnippet = false;
        
        // Стандартный JSON формат
        if (preg_match('/"price": "([0-9.]+)"/', $html)) {
            $priceSnippet = true;
        }
        
        // Unicode вариант
        if (preg_match('/\\\\u0022price\\\\u0022:\\s*\\\\u0022([0-9.]+)\\\\u0022/', $html)) {
            $priceSnippet = true;
        }
        
        echo "Наличие фрагмента цены в HTML: " . ($priceSnippet ? "ДА" : "НЕТ") . "\n\n";
        
        // Используем нашу функцию с режимом отладки
        $result = extractCoursePrice($html, true);
        
        echo "РЕЗУЛЬТАТ ИЗВЛЕЧЕНИЯ ЦЕНЫ: " . $result['price'] . "\n\n";
        echo "ОТЛАДОЧНАЯ ИНФОРМАЦИЯ:\n" . $result['debug'] . "\n";
        
        // Специальный поиск именно для курсов 1564 и 1565
        if ($courseId == 1564 || $courseId == 1565) {
            echo "СПЕЦИАЛЬНЫЙ ПОИСК ДЛЯ КУРСОВ 1564/1565:\n";
            
            // Поиск определенных шаблонов
            if (preg_match('/\\\\u0022price\\\\u0022:\\s*\\\\u0022([0-9.]+)\\\\u0022/', $html, $matches)) {
                echo "  Найдена цена в Unicode формате: " . $matches[1] . "\n";
            } else {
                echo "  Цена в Unicode формате не найдена\n";
            }
            
            if (preg_match('/\\\\u0022currency_code\\\\u0022:\\s*\\\\u0022([A-Z]+)\\\\u0022/', $html, $matches)) {
                echo "  Найдена валюта в Unicode формате: " . $matches[1] . "\n";
            } else {
                echo "  Валюта в Unicode формате не найдена\n";
            }
            
            if (preg_match('/\\\\u0022display_price\\\\u0022:\\s*\\\\u0022(.*?)\\\\u0022/', $html, $matches)) {
                echo "  Найден display_price в Unicode формате: " . $matches[1] . "\n";
                
                // Декодируем строку
                $decodedPrice = preg_replace_callback('/\\\\u005Cu([0-9a-fA-F]{4})/', function($matches) {
                    return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8');
                }, $matches[1]);
                
                echo "  Декодированный display_price: " . $decodedPrice . "\n";
            } else {
                echo "  display_price в Unicode формате не найден\n";
            }
            
            // Прямой поиск в исходном коде фрагмента с ценой
            $rawCodeSnippet = '';
            if (preg_match('/\\\\u0022price\\\\u0022.*?\\\\u0022display_price\\\\u0022.*?\\\\u0022/s', $html, $snippetMatches)) {
                $rawCodeSnippet = $snippetMatches[0];
                echo "  Найден сырой фрагмент с ценой (первые 100 символов): " . substr($rawCodeSnippet, 0, 100) . "...\n";
            } else {
                echo "  Сырой фрагмент с ценой не найден\n";
            }
        }
        
    } catch (Exception $e) {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
    }
    
    echo "=== КОНЕЦ ТЕСТИРОВАНИЯ КУРСА ID: $courseId ===\n\n";
}

// echo "------------ ПЕРВОЕ ТЕСТИРОВАНИЕ ------------\n";
// testCoursePriceExtraction(1000); // Курс "Создание платного курса на Stepik"

// echo "------------ ВТОРОЕ ТЕСТИРОВАНИЕ ------------\n";
// testCoursePriceExtraction(1564); // Курс "Латинский язык: Грамматический конструктор"

// echo "------------ ТРЕТЬЕ ТЕСТИРОВАНИЕ ------------\n"; 
// testCoursePriceExtraction(1565); // Курс "Французский язык: Грамматический конструктор"

// Тестируем функцию на проблемных курсах
/*
// Раскомментируйте эти строки для запуска тестов

*/

function extractCoursePrice($html, $debug = false) {
    $price = 'Бесплатно'; // Устанавливаем значение по умолчанию "Бесплатно"
    $debugInfo = "";
    
    // Метод 1: Поиск в shoebox-main-store
    if (preg_match('/<script id="shoebox-main-store" type="fastboot\/shoebox">(.*?)<\/script>/s', $html, $matches)) {
        if ($debug) $debugInfo .= "Метод 1: Найден shoebox-main-store\n";
        
        try {
            $jsonData = json_decode($matches[1], true);
            
            if (isset($jsonData['records']['course']['courses'][0])) {
                $courseData = $jsonData['records']['course']['courses'][0];
                
                if ($debug) {
                    $debugInfo .= "  is_paid: " . (isset($courseData['is_paid']) ? ($courseData['is_paid'] ? 'true' : 'false') : 'не найдено') . "\n";
                    $debugInfo .= "  price: " . (isset($courseData['price']) ? $courseData['price'] : 'не найдено') . "\n";
                    $debugInfo .= "  currency_code: " . (isset($courseData['currency_code']) ? $courseData['currency_code'] : 'не найдено') . "\n";
                    $debugInfo .= "  display_price: " . (isset($courseData['display_price']) ? $courseData['display_price'] : 'не найдено') . "\n";
                }
                
                // Проверка на цену > 0, даже если is_paid = false
                if ((isset($courseData['price']) && floatval($courseData['price']) > 0) || 
                    (isset($courseData['is_paid']) && $courseData['is_paid'] === true)) {
                    
                    if (isset($courseData['display_price']) && !empty($courseData['display_price'])) {
                        $price = $courseData['display_price'];
                        if ($debug) $debugInfo .= "  Установлена цена из display_price: $price\n";
                    } elseif (isset($courseData['price']) && isset($courseData['currency_code'])) {
                        $price = $courseData['price'];
                        $currency = $courseData['currency_code'];
                        $price = "$price $currency";
                        if ($debug) $debugInfo .= "  Установлена цена из price + currency_code: $price\n";
                    }
                }
            }
        } catch (Exception $e) {
            if ($debug) $debugInfo .= "  Ошибка декодирования JSON: " . $e->getMessage() . "\n";
        }
    }
    
    // Метод 2: Поиск в __stepik_shoebox__
    if ($price === 'Бесплатно' && preg_match('/__stepik_shoebox__ = JSON\.parse\(\'(.*?)\'\)/s', $html, $matches)) {
        if ($debug) $debugInfo .= "Метод 2: Найден __stepik_shoebox__\n";
        
        // Будем извлекать информацию напрямую через регулярки из строки JSON
        if (preg_match('/\\"price\\":\\s*\\"([^"]+)\\"/i', $matches[1], $priceMatch)) {
            $rawPrice = $priceMatch[1];
            if ($debug) $debugInfo .= "  Найдена цена в shoebox: $rawPrice\n";
            
            // Проверяем, что цена > 0
            if (is_numeric(str_replace('.', '', $rawPrice)) && floatval($rawPrice) > 0) {
                if (preg_match('/\\"currency_code\\":\\s*\\"([^"]+)\\"/i', $matches[1], $currencyMatch)) {
                    $rawCurrency = $currencyMatch[1];
                    if ($debug) $debugInfo .= "  Найдена валюта в shoebox: $rawCurrency\n";
                    $price = "$rawPrice $rawCurrency";
                    if ($debug) $debugInfo .= "  Установлена цена из shoebox строки: $price\n";
                }
            }
        }
        
        // Декодирование полного JSON если первый метод не сработал
        if ($price === 'Бесплатно') {
            try {
                // Декодируем строку с экранированными Unicode символами
                $escapedJson = stripcslashes($matches[1]);
                
                // Вместо полного декодирования JSON пытаемся извлечь курс напрямую через регулярки
                if (preg_match('/\\"courses\\"\\s*:\\s*\\[\\s*{([^}]+)}/s', $escapedJson, $courseMatch)) {
                    $courseData = $courseMatch[1];
                    
                    if ($debug) $debugInfo .= "  Извлечены данные курса через регулярное выражение\n";
                    
                    // Проверяем цену напрямую
                    if (preg_match('/\\"price\\"\\s*:\\s*\\"([^"]+)\\"/i', $courseData, $priceMatch)) {
                        $rawPrice = $priceMatch[1];
                        if ($debug) $debugInfo .= "  Найдена цена: $rawPrice\n";
                        
                        // Проверяем, что цена > 0
                        if (is_numeric(str_replace('.', '', $rawPrice)) && floatval($rawPrice) > 0) {
                            if (preg_match('/\\"currency_code\\"\\s*:\\s*\\"([^"]+)\\"/i', $courseData, $currencyMatch)) {
                                $rawCurrency = $currencyMatch[1];
                                if ($debug) $debugInfo .= "  Найдена валюта: $rawCurrency\n";
                                $price = "$rawPrice $rawCurrency";
                                if ($debug) $debugInfo .= "  Установлена цена из регулярки: $price\n";
                            }
                        }
                    }
                    
                    // Проверяем display_price напрямую
                    if ($price === 'Бесплатно' && preg_match('/\\"display_price\\"\\s*:\\s*\\"([^"]+)\\"/i', $courseData, $displayPriceMatch)) {
                        $rawDisplayPrice = $displayPriceMatch[1];
                        if ($debug) $debugInfo .= "  Найден display_price: $rawDisplayPrice\n";
                        
                        // Обработка экранированных Unicode-символов в display_price
                        $decodedPrice = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                            return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8');
                        }, $rawDisplayPrice);
                        
                        if (!empty($decodedPrice) && $decodedPrice !== '-') {
                            $price = $decodedPrice;
                            if ($debug) $debugInfo .= "  Установлена декодированная цена: $price\n";
                        }
                    }
                }
            } catch (Exception $e) {
                if ($debug) $debugInfo .= "  Ошибка при обработке JSON строки: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Метод 3: Прямой поиск цен в HTML
    if ($price === 'Бесплатно') {
        if ($debug) $debugInfo .= "Метод 3: Прямой поиск цен в HTML\n";
        
        // Ищем строку вида "price":"5000.00","currency_code":"RUB" 
        // с учетом возможных пробелов и разных кавычек
        if (preg_match('/["\']price["\']\\s*:\\s*["\']([0-9.]+)["\']\\s*,\\s*["\']currency_code["\']\\s*:\\s*["\']([A-Z]+)["\']/i', $html, $matches)) {
            $rawPrice = $matches[1];
            $rawCurrency = $matches[2];
            
            if ($debug) {
                $debugInfo .= "  Найдена цена: $rawPrice и валюта: $rawCurrency через прямой поиск\n";
            }
            
            // Проверяем, что цена > 0
            if (floatval($rawPrice) > 0) {
                $price = "$rawPrice $rawCurrency";
                if ($debug) $debugInfo .= "  Установлена цена: $price\n";
            }
        }
        
        // Ищем строку вида display_price":"5000 ₽"
        if ($price === 'Бесплатно' && preg_match('/["\']display_price["\']\\s*:\\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            $displayPrice = $matches[1];
            
            if ($debug) {
                $debugInfo .= "  Найден display_price: $displayPrice через прямой поиск\n";
            }
            
            // Декодируем Unicode символы, если они есть
            $decodedPrice = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8');
            }, $displayPrice);
            
            if (!empty($decodedPrice) && $decodedPrice !== '-') {
                $price = $decodedPrice;
                if ($debug) $debugInfo .= "  Установлена декодированная цена: $price\n";
            }
        }
    }
    
    // Метод 4: Интенсивный поиск по шаблонам цен в HTML
    if ($price === 'Бесплатно') {
        if ($debug) $debugInfo .= "Метод 4: Интенсивный поиск по шаблонам цен\n";
        
        // Поиск строк с ценами, даже если они не в формате JSON
        // Ищем "price":"5000.00" или похожие варианты
        if (preg_match('/["\']price["\']\\s*:\\s*["\']([0-9.]+)["\']/', $html, $matches)) {
            $rawPrice = $matches[1];
            
            // Убедимся, что цена не нулевая
            if (floatval($rawPrice) > 0) {
                if ($debug) $debugInfo .= "  Найдена ненулевая цена: $rawPrice\n";
                
                // Пытаемся найти валюту поблизости
                if (preg_match('/["\']currency_code["\']\\s*:\\s*["\']([A-Z]+)["\']/', $html, $currencyMatches)) {
                    $currency = $currencyMatches[1];
                    if ($debug) $debugInfo .= "  Найдена валюта: $currency\n";
                    $price = "$rawPrice $currency";
                    if ($debug) $debugInfo .= "  Установлена цена с валютой: $price\n";
                } else {
                    // Если валюта не найдена, используем цену без валюты
                    $price = $rawPrice;
                    if ($debug) $debugInfo .= "  Установлена цена без валюты: $price\n";
                }
            }
        }
        
        // Специальный поиск для курсов 1564 и 1565 (и похожих случаев)
        // Ищем \u0022price\u0022: \u00225000.00\u0022, \u0022currency_code\u0022: \u0022RUB\u0022
        if ($price === 'Бесплатно' && preg_match('/\\\\u0022price\\\\u0022:\\s*\\\\u0022([0-9.]+)\\\\u0022/', $html, $matches)) {
            $rawPrice = $matches[1];
            
            if (floatval($rawPrice) > 0) {
                if ($debug) $debugInfo .= "  Найдена цена в Unicode формате: $rawPrice\n";
                
                if (preg_match('/\\\\u0022currency_code\\\\u0022:\\s*\\\\u0022([A-Z]+)\\\\u0022/', $html, $currencyMatches)) {
                    $currency = $currencyMatches[1];
                    if ($debug) $debugInfo .= "  Найдена валюта в Unicode формате: $currency\n";
                    $price = "$rawPrice $currency";
                    if ($debug) $debugInfo .= "  Установлена цена с валютой из Unicode: $price\n";
                }
            }
        }
        
        // Специальная обработка display_price в Unicode формате
        if ($price === 'Бесплатно' && preg_match('/\\\\u0022display_price\\\\u0022:\\s*\\\\u0022(.*?)\\\\u0022/', $html, $matches)) {
            $encodedPrice = $matches[1];
            if ($debug) $debugInfo .= "  Найден закодированный display_price: $encodedPrice\n";
            
            // Пытаемся декодировать Unicode последовательности
            // Например, \u005Cu00a0\u005Cu20bd для пробела и символа рубля
            $decodedPrice = preg_replace_callback('/\\\\u005Cu([0-9a-fA-F]{4})/', function($matches) {
                return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8');
            }, $encodedPrice);
            
            if (!empty($decodedPrice) && $decodedPrice !== '-') {
                $price = $decodedPrice;
                if ($debug) $debugInfo .= "  Установлена декодированная цена из Unicode: $price\n";
            }
        }
    }
    
    // Убедимся, что возвращаемое значение не null
    if ($price === null) {
        $price = 'Бесплатно';
    }

    // Добавляем "костыль" для обработки бесплатных курсов
    if ($price === "\\u002D" || $price === '-') { 
        $price = "Бесплатно";
    }
    
    if ($debug) {
        return ['price' => $price, 'debug' => $debugInfo];
    }
    
    return $price;
}

// Основной цикл обработки
for ($i = 1566; $i <= 116340; $i++) {
    $processed++;
    $courseUrl = "https://stepik.org/course/{$i}/promo";
    
    // Проверяем, не обрабатывали ли мы уже этот курс (по базе)
    $platform = \App\Models\Platform::findByName('stepik');
    if (!$platform) {
        echo "Платформа stepik не найдена в базе.\n";
        break;
    }
    $existing = \App\Models\CourseDB::search([
        'platform_id' => $platform->getId(),
        'external_id' => $i
    ]);
    if (!empty($existing)) {
        echo "Курс с ID {$i} уже есть в базе, пропускаем.\n";
        echo "------------------------\n";
        $skipped++;
        continue;
    }

    try {
        $response = $client->get($courseUrl);
        $html = (string) $response->getBody();
        $crawler = new Crawler($html);

        // Проверяем наличие ключевых элементов
        $titleElement = $crawler->filter('title');
        $descriptionElement = $crawler->filter('meta[name="description"]');

        $hasCourseContent = $titleElement->count() > 0 || $descriptionElement->count() > 0;

        if ($hasCourseContent) {
            $title = $titleElement->count() > 0 ? trim(str_replace(' — Stepik', '', $titleElement->text())) : 'N/A';
            $description = $descriptionElement->count() > 0 ? $descriptionElement->attr('content') : 'N/A';

            // Средний рейтинг и количество отзывов
            $ratingValueElement = $crawler->filter('script[type="application/ld+json"]');
            $rating = 'N/A';
            $reviewCount = 'N/A';
            
            if ($ratingValueElement->count() > 0) {
                $ratingJson = json_decode($ratingValueElement->text(), true);
                if (isset($ratingJson['aggregateRating']['ratingValue'])) {
                    $rating = $ratingJson['aggregateRating']['ratingValue'];
                }
                if (isset($ratingJson['aggregateRating']['ratingCount'])) {
                    $reviewCount = $ratingJson['aggregateRating']['ratingCount'];
                }
            }
            
            // Извлекаем стоимость курса
            $price = extractCoursePrice($html);
            
            // Проверяем, не завершился ли курс
            $isEnded = isCourseEnded($html);
            
            // Для отладки - проверяем и выводим участки HTML с упоминанием о завершении
            $debugInfo = "";
            if (strpos($html, 'course-promo-enrollment__course-ended-warn') !== false) {
                preg_match('/<div[^>]*class="[^"]*course-promo-enrollment__course-ended-warn[^"]*"[^>]*>(.*?)<\/div>/si', $html, $endedMatches);
                $debugInfo .= "🔍 Найден class course-promo-enrollment__course-ended-warn: " . 
                              (isset($endedMatches[1]) ? trim($endedMatches[1]) : "текст не извлечен") . "\n";
            }
            
            if (preg_match('/[Зз]авершился\s+(\d+|[а-яА-Я]+)\s+(дн|лет|год|недел)/ui', $html, $textMatches)) {
                $debugInfo .= "🔍 Найден текст о завершении: " . $textMatches[0] . "\n";
            }
            
            // Все данные собраны, теперь вывод
            echo "  Название: {$title}\n";
            echo "  Рейтинг: {$rating} (отзывов: {$reviewCount})\n";
            echo "  Цена: " . ($price === null ? 'Бесплатно' : $price) . "\n";
            
            if (!empty($debugInfo)) {
                echo "  Отладка: \n" . $debugInfo;
            }
            
            if ($isEnded) {
                echo "  ⚠️ Курс определен как завершившийся и будет пропущен\n";
                echo "------------------------\n";
                $skippedEnded++;
                continue;
            }

            if (!isStubCourse($title, $description, $rating)) {
            // Извлекаем цену курса с использованием улучшенной функции
            $price = extractCoursePrice($html);
    
            // Дополнительная проверка для платных курсов
            $isPaidInHtml = strpos($html, '"is_paid":true') !== false || strpos($html, '"is_paid": true') !== false;
    
            // Если в HTML есть явное указание, что курс платный, но наша функция вернула "Бесплатно",
            // попробуем использовать прямой метод извлечения
            if ($isPaidInHtml && $price === 'Бесплатно') {
                if (preg_match('/"price":\s*"([^"]+)"/', $html, $priceMatches) &&
                    preg_match('/"currency_code":\s*"([^"]+)"/', $html, $currencyMatches)) {
                    $rawPrice = $priceMatches[1];
                    $rawCurrency = $currencyMatches[1];
                    $price = "$rawPrice $rawCurrency";
                    echo "  Внимание: исправлена цена для платного курса: {$price}\n";
                }
            }
    
            $course_data = [
                'id' => $i,
                'title' => $title,
                'description' => $description,
                'rating' => $rating,
                'review_count' => $reviewCount,
                'price' => $price,
                'url' => $courseUrl,
                'parsed_at' => date('Y-m-d H:i:s')
            ];
    
            // Сохраняем курс в БД
            $courseDB = new \App\Models\CourseDB($course_data, 'stepik');
            $courseDB->setPlatformId($platform->getId());
            $courseDB->setParsedAt($course_data['parsed_at']);
            $courseDB->save();
            $added++;
    
            echo "ID курса: {$i} - данные добавлены в БД\n";
            echo "  Название: {$title}\n";
            echo "  Рейтинг: {$rating} (отзывов: {$reviewCount})\n";
            echo "  Цена: {$price}\n";
        } else {
            echo "Курс с ID {$i} - заглушка, пропущен.\n";
            $skippedStub++;
        }
            echo "------------------------\n";

        } else {
            echo "Курс с ID {$i} - заглушка, пропущен.\n";
            echo "------------------------\n";
            $skippedStub++;
        }

    } catch (ClientException $e) {
        if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
            echo "Курс с ID {$i} не найден (404).\n";
            echo "------------------------\n";
            $skippedError++;
        } else {
            echo "Ошибка при получении страницы: " . $e->getMessage() . "\n";
            echo "Не удалось получить данные для курса с ID: {$i}\n";
            echo "------------------------\n";
            $skippedError++;
        }
    } catch (Exception $e) {
        echo "Ошибка при обработке: " . $e->getMessage() . "\n";
        echo "Не удалось получить данные для курса с ID: {$i}\n";
        echo "------------------------\n";
        $skippedError++;
    }

    sleep(1); // Небольшая пауза между запросами
}

// Итоговая статистика
echo "\n=== Итоги работы парсера ===\n";
echo "Обработано ID: {$processed}\n";
echo "Добавлено новых курсов: {$added}\n";
echo "Пропущено (всего): {$skipped}\n";
echo "Пропущено завершившихся курсов: {$skippedEnded}\n";
echo "Пропущено заглушек: {$skippedStub}\n";
echo "Пропущено из-за ошибок: {$skippedError}\n";