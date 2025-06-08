<?php
namespace App\Controllers\API;

use App\Models\Playlist;
use App\Models\Database;

class PlaylistController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $_GET['user_id'] ?? null;

            if (!$userId) {
                throw new \Exception('user_id required', 400);
            }

            $playlists = Playlist::getUserPlaylists($userId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlists
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function publicPlaylists() {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;

            $playlists = Playlist::getPublicPlaylists($limit, $offset);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlists
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }

    public function show($id) {
        try {
            $playlist = Playlist::findById($id);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            // Проверяем доступ
            if (!$playlist->getIsPublic() && 
                (!isset($_SESSION['user_id']) || $playlist->getUserId() != $_SESSION['user_id'])) {
                throw new \Exception('Доступ запрещен', 403);
            }

            $playlistData = $playlist->toArray();
            $playlistData['courses'] = $playlist->getCourses();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlistData
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function store() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) {
                throw new \Exception('user_id required', 400);
            }

            if (!isset($data['name']) || empty($data['name'])) {
                throw new \Exception('Название подборки обязательно', 400);
            }

            $playlist = new Playlist([
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'is_public' => $data['is_public'] ?? false
            ]);

            if (!$playlist->save()) {
                throw new \Exception('Ошибка при создании подборки');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlist->toArray()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function update($id) {
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new \Exception('Unauthorized', 401);
            }

            $playlist = Playlist::findById($id);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            if ($playlist->getUserId() != $_SESSION['user_id']) {
                throw new \Exception('Доступ запрещен', 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['name'])) {
                $playlist->setName($data['name']);
            }
            if (isset($data['description'])) {
                $playlist->setDescription($data['description']);
            }
            if (isset($data['is_public'])) {
                $playlist->setIsPublic($data['is_public']);
            }

            if (!$playlist->save()) {
                throw new \Exception('Ошибка при обновлении подборки');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlist->toArray()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function destroy($id) {
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new \Exception('Unauthorized', 401);
            }

            $playlist = Playlist::findById($id);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            if ($playlist->getUserId() != $_SESSION['user_id']) {
                throw new \Exception('Доступ запрещен', 403);
            }

            if (!$playlist->delete()) {
                throw new \Exception('Ошибка при удалении подборки');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Подборка успешно удалена'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function addCourse($playlistId) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) {
                throw new \Exception('user_id required', 400);
            }

            if (!isset($data['course_id'])) {
                throw new \Exception('ID курса обязателен', 400);
            }

            $playlist = Playlist::findById($playlistId);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            if ($playlist->getUserId() != $data['user_id']) {
                throw new \Exception('Доступ запрещен', 403);
            }

            $position = $data['position'] ?? 0;
            
            if (!$playlist->addCourse($data['course_id'], $position)) {
                throw new \Exception('Ошибка при добавлении курса в подборку');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Курс успешно добавлен в подборку'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function removeCourse($playlistId, $courseId) {
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new \Exception('Unauthorized', 401);
            }

            $playlist = Playlist::findById($playlistId);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            if ($playlist->getUserId() != $_SESSION['user_id']) {
                throw new \Exception('Доступ запрещен', 403);
            }

            if (!$playlist->removeCourse($courseId)) {
                throw new \Exception('Ошибка при удалении курса из подборки');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Курс успешно удален из подборки'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function getCourses($playlistId) {
        try {
            $userId = $_GET['user_id'] ?? null;

            if (!$userId) {
                throw new \Exception('user_id required', 400);
            }

            $playlist = Playlist::findById($playlistId);
            
            if (!$playlist) {
                throw new \Exception('Подборка не найдена', 404);
            }

            // Проверяем доступ
            if (!$playlist->getIsPublic() && $playlist->getUserId() != $userId) {
                throw new \Exception('Доступ запрещен', 403);
            }

            $courses = $playlist->getCourses();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $courses
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    private function sendError($message, $code = 500) {
        header('Content-Type: application/json');
        http_response_code($code ?: 500);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function search() {
        try {
            $query = $_GET['search'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;

            // Определяем область поиска
            $isPublic = true;
            $userId = null;

            // Если пользователь авторизован и указан параметр include_private
            if (isset($_SESSION['user_id']) && isset($_GET['include_private']) && $_GET['include_private'] === 'true') {
                $isPublic = false;
                $userId = $_SESSION['user_id'];
            }

            $playlists = Playlist::searchPlaylists($query, $isPublic, $userId, $limit, $offset);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $playlists
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
} 