<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\ClientException;

$client = new Client();
$all_courses_data = [];
$typical_stub_description = "Образовательная платформа — Stepik. Выберите подходящий вам онлайн-курс из более чем 20 тысяч и начните получать востребованные навыки.";
$typical_stub_title = "Stepik";

// Путь к файлу данных
$dataFile = __DIR__ . '/data/stepik_courses.json';

// Чтение существующих данных
$existingData = [];
if (file_exists($dataFile)) {
    $existingData = json_decode(file_get_contents($dataFile), true) ?: [];
}

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


for ($i = 51; $i <= 100; $i++) {
    $courseUrl = "https://stepik.org/course/{$i}/promo";

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

            // Средний рейтинг
            $ratingValueElement = $crawler->filter('script[type="application/ld+json"]');
            $rating = 'N/A';
            if ($ratingValueElement->count() > 0) {
                $ratingJson = json_decode($ratingValueElement->text(), true);
                if (isset($ratingJson['aggregateRating']['ratingValue'])) {
                    $rating = $ratingJson['aggregateRating']['ratingValue'];
                }
            }

            if (!isStubCourse($title, $description, $rating)) {
                $course_data = [
                    'id' => $i,
                    'title' => $title,
                    'description' => $description,
                    'rating' => $rating,
                    'url' => $courseUrl,
                ];
                $all_courses_data[] = $course_data; // Добавляем данные курса в массив
                echo "ID курса: {$i} - данные добавлены\n";
            } else {
                echo "Курс с ID {$i} - заглушка, пропущен.\n";
            }
            echo "------------------------\n";


        } else {
            echo "Курс с ID {$i} - заглушка, пропущен.\n";
            echo "------------------------\n";
        }

    } catch (ClientException $e) {
        if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
            echo "Курс с ID {$i} не найден (404).\n";
            echo "------------------------\n";
        } else {
            echo "Ошибка при получении страницы: " . $e->getMessage() . "\n";
            echo "Не удалось получить данные для курса с ID: {$i}\n";
            echo "------------------------\n";
        }
    } catch (Exception $e) {
        echo "Ошибка при обработке: " . $e->getMessage() . "\n";
        echo "Не удалось получить данные для курса с ID: {$i}\n";
        echo "------------------------\n";
    }

    sleep(1);
}

// Фильтрация новых курсов
$newCourses = [];
foreach ($all_courses_data as $newCourse) {
    $isDuplicate = false;
    foreach ($existingData as $existingCourse) {
        if ($newCourse['id'] == $existingCourse['id']) {
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

// Сохранение
file_put_contents(
    $dataFile,
    json_encode($combinedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "Добавлено новых курсов: " . count($newCourses) . "\n";
echo "Всего курсов в файле: " . count($combinedData) . "\n";