<?php
namespace App\Controllers\API;

use App\Models\Favorite;

class FavoriteController
{
    // Добавить курс в избранное
    public function add()
    {
        // Получаем JSON данные из тела запроса
        $jsonData = json_decode(file_get_contents('php://input'), true);

        $userId = $jsonData['user_id'] ?? null;
        $courseId = $jsonData['course_id'] ?? null;

        if (!$userId || !$courseId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id and course_id required']);
            return;
        }

        $favorite = new Favorite(['user_id' => $userId, 'course_id' => $courseId]);
        if ($favorite->save()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add to favorites']);
        }
    }

    // Удалить курс из избранного
    public function remove()
    {
        // Получаем JSON данные из тела запроса
        $jsonData = json_decode(file_get_contents('php://input'), true);
        
        $userId = $jsonData['user_id'] ?? null;
        $courseId = $jsonData['course_id'] ?? null;

        if (!$userId || !courseId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id and course_id required']);
            return;
        }

        $favorite = new Favorite(['user_id' => $userId, 'course_id' => $courseId]);
        if ($favorite->delete()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove from favorites']);
        }
    }

    // Получить список избранных курсов пользователя
    public function list()
    {
        $userId = $_GET['user_id'] ?? null;

        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id required']);
            return;
        }

        $favorites = Favorite::getUserFavorites($userId);
        echo json_encode(['favorites' => $favorites]);
    }
}