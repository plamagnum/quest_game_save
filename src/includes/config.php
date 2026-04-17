<?php
/**
 * Конфігурація підключення до бази даних
 * Файл містить налаштування для з'єднання з MySQL
 */

// Параметри підключення до бази даних
define('DB_HOST', 'mysql');
define('DB_NAME', 'quest_game');
define('DB_USER', 'quest_user');
define('DB_PASS', 'quest_password');
define('DB_CHARSET', 'utf8mb4');

/**
 * Створення підключення до бази даних через PDO
 * @return PDO об'єкт підключення
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Помилка підключення до бази даних']));
        }
    }
    
    return $pdo;
}

/**
 * Налаштування сесії з безпечними параметрами
 */
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
session_start();

/**
 * Встановлення заголовків для JSON API
 */
function setJsonHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

/**
 * Перевірка авторизації адміністратора
 * @return bool чи авторизований адмін
 */
function isAdmin(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Вимога авторизації адміністратора
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        http_response_code(401);
        die(json_encode(['error' => 'Необхідна авторизація']));
    }
}

/**
 * Очищення вхідних даних
 * @param string $data вхідні дані
 * @return string очищені дані
 */
function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Отримання JSON даних з тіла запиту
 * @return array розпарсені дані
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}
