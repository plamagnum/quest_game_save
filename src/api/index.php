<?php
/**
 * Головний маршрутизатор API
 * Обробляє всі API запити та направляє їх до відповідних обробників
 */

require_once __DIR__ . '/../includes/config.php';
setJsonHeaders();

// Отримання шляху запиту
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Видалення параметрів запиту та базового шляху
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);
$path = rtrim($path, '/');

// Маршрутизація запитів
switch (true) {
    // Авторизація
    case $path === '/login' && $method === 'POST':
        handleLogin();
        break;
    case $path === '/logout' && $method === 'POST':
        handleLogout();
        break;
    case $path === '/check-auth' && $method === 'GET':
        handleCheckAuth();
        break;

    // Класи
    case $path === '/classes' && $method === 'GET':
        handleGetClasses();
        break;
    case $path === '/classes' && $method === 'POST':
        requireAdmin();
        handleCreateClass();
        break;
    case preg_match('#^/classes/(\d+)$#', $path, $m) && $method === 'PUT':
        requireAdmin();
        handleUpdateClass((int)$m[1]);
        break;
    case preg_match('#^/classes/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAdmin();
        handleDeleteClass((int)$m[1]);
        break;

    // Предмети
    case $path === '/subjects' && $method === 'GET':
        handleGetSubjects();
        break;
    case $path === '/subjects' && $method === 'POST':
        requireAdmin();
        handleCreateSubject();
        break;
    case preg_match('#^/subjects/(\d+)$#', $path, $m) && $method === 'PUT':
        requireAdmin();
        handleUpdateSubject((int)$m[1]);
        break;
    case preg_match('#^/subjects/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAdmin();
        handleDeleteSubject((int)$m[1]);
        break;

    // Теми
    case $path === '/topics' && $method === 'GET':
        handleGetTopics();
        break;
    case $path === '/topics' && $method === 'POST':
        requireAdmin();
        handleCreateTopic();
        break;
    case preg_match('#^/topics/(\d+)$#', $path, $m) && $method === 'PUT':
        requireAdmin();
        handleUpdateTopic((int)$m[1]);
        break;
    case preg_match('#^/topics/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAdmin();
        handleDeleteTopic((int)$m[1]);
        break;

    // Категорії
    case $path === '/categories' && $method === 'GET':
        handleGetCategories();
        break;
    case $path === '/categories' && $method === 'POST':
        requireAdmin();
        handleCreateCategory();
        break;
    case preg_match('#^/categories/(\d+)$#', $path, $m) && $method === 'PUT':
        requireAdmin();
        handleUpdateCategory((int)$m[1]);
        break;
    case preg_match('#^/categories/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAdmin();
        handleDeleteCategory((int)$m[1]);
        break;

    // Запитання
    case $path === '/questions' && $method === 'GET':
        handleGetQuestions();
        break;
    case $path === '/questions' && $method === 'POST':
        requireAdmin();
        handleCreateQuestion();
        break;
    case preg_match('#^/questions/(\d+)$#', $path, $m) && $method === 'GET':
        handleGetQuestion((int)$m[1]);
        break;
    case preg_match('#^/questions/(\d+)$#', $path, $m) && $method === 'PUT':
        requireAdmin();
        handleUpdateQuestion((int)$m[1]);
        break;
    case preg_match('#^/questions/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAdmin();
        handleDeleteQuestion((int)$m[1]);
        break;

    // Імпорт запитань через JSON
    case $path === '/questions/import' && $method === 'POST':
        requireAdmin();
        handleImportQuestions();
        break;

    // Ігри
    case $path === '/games' && $method === 'POST':
        requireAdmin();
        handleCreateGame();
        break;
    case preg_match('#^/games/(\d+)$#', $path, $m) && $method === 'GET':
        handleGetGame((int)$m[1]);
        break;
    case preg_match('#^/games/(\d+)/start$#', $path, $m) && $method === 'POST':
        requireAdmin();
        handleStartGame((int)$m[1]);
        break;
    case preg_match('#^/games/(\d+)/finish$#', $path, $m) && $method === 'POST':
        requireAdmin();
        handleFinishGame((int)$m[1]);
        break;
    case preg_match('#^/games/(\d+)/select-question$#', $path, $m) && $method === 'POST':
        requireAdmin();
        handleSelectQuestion((int)$m[1]);
        break;
    case preg_match('#^/games/(\d+)/answer$#', $path, $m) && $method === 'POST':
        handleSubmitAnswer((int)$m[1]);
        break;
    case $path === '/games/active' && $method === 'GET':
        handleGetActiveGame();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Маршрут не знайдено']);
        break;
}

// ===================== АВТОРИЗАЦІЯ =====================

/**
 * Обробка входу адміністратора
 */
function handleLogin(): void {
    $data = getJsonInput();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть логін та пароль']);
        return;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        echo json_encode(['success' => true, 'username' => $admin['username']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Невірний логін або пароль']);
    }
}

/**
 * Обробка виходу
 */
function handleLogout(): void {
    session_destroy();
    echo json_encode(['success' => true]);
}

/**
 * Перевірка стану авторизації
 */
function handleCheckAuth(): void {
    echo json_encode([
        'authenticated' => isAdmin(),
        'username' => $_SESSION['admin_username'] ?? null
    ]);
}

// ===================== КЛАСИ =====================

function handleGetClasses(): void {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM classes ORDER BY name");
    echo json_encode($stmt->fetchAll());
}

function handleCreateClass(): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву класу']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO classes (name) VALUES (?)");
    $stmt->execute([$name]);
    echo json_encode(['id' => $db->lastInsertId(), 'name' => $name]);
}

function handleUpdateClass(int $id): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву класу']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE classes SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    echo json_encode(['success' => true]);
}

function handleDeleteClass(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

// ===================== ПРЕДМЕТИ =====================

function handleGetSubjects(): void {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM subjects ORDER BY name");
    echo json_encode($stmt->fetchAll());
}

function handleCreateSubject(): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву предмету']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO subjects (name) VALUES (?)");
    $stmt->execute([$name]);
    echo json_encode(['id' => $db->lastInsertId(), 'name' => $name]);
}

function handleUpdateSubject(int $id): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву предмету']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE subjects SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    echo json_encode(['success' => true]);
}

function handleDeleteSubject(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

// ===================== ТЕМИ =====================

function handleGetTopics(): void {
    $db = getDB();
    $subjectId = $_GET['subject_id'] ?? null;
    if ($subjectId) {
        $stmt = $db->prepare("SELECT t.*, s.name as subject_name FROM topics t JOIN subjects s ON t.subject_id = s.id WHERE t.subject_id = ? ORDER BY t.name");
        $stmt->execute([(int)$subjectId]);
    } else {
        $stmt = $db->query("SELECT t.*, s.name as subject_name FROM topics t JOIN subjects s ON t.subject_id = s.id ORDER BY t.name");
    }
    echo json_encode($stmt->fetchAll());
}

function handleCreateTopic(): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    $subjectId = (int)($data['subject_id'] ?? 0);
    if (empty($name) || $subjectId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву теми та предмет']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (?, ?)");
    $stmt->execute([$subjectId, $name]);
    echo json_encode(['id' => $db->lastInsertId(), 'name' => $name, 'subject_id' => $subjectId]);
}

function handleUpdateTopic(int $id): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    $subjectId = (int)($data['subject_id'] ?? 0);
    if (empty($name) || $subjectId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву теми та предмет']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE topics SET name = ?, subject_id = ? WHERE id = ?");
    $stmt->execute([$name, $subjectId, $id]);
    echo json_encode(['success' => true]);
}

function handleDeleteTopic(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

// ===================== КАТЕГОРІЇ =====================

function handleGetCategories(): void {
    $db = getDB();
    $topicId = $_GET['topic_id'] ?? null;
    if ($topicId) {
        $stmt = $db->prepare("SELECT c.*, t.name as topic_name FROM categories c JOIN topics t ON c.topic_id = t.id WHERE c.topic_id = ? ORDER BY c.name");
        $stmt->execute([(int)$topicId]);
    } else {
        $stmt = $db->query("SELECT c.*, t.name as topic_name FROM categories c JOIN topics t ON c.topic_id = t.id ORDER BY c.name");
    }
    echo json_encode($stmt->fetchAll());
}

function handleCreateCategory(): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    $topicId = (int)($data['topic_id'] ?? 0);
    $color = sanitize($data['color'] ?? '#3498db');
    if (empty($name) || $topicId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву категорії та тему']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO categories (topic_id, name, color) VALUES (?, ?, ?)");
    $stmt->execute([$topicId, $name, $color]);
    echo json_encode(['id' => $db->lastInsertId(), 'name' => $name]);
}

function handleUpdateCategory(int $id): void {
    $data = getJsonInput();
    $name = sanitize($data['name'] ?? '');
    $color = sanitize($data['color'] ?? '#3498db');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть назву категорії']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ?");
    $stmt->execute([$name, $color, $id]);
    echo json_encode(['success' => true]);
}

function handleDeleteCategory(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

// ===================== ЗАПИТАННЯ =====================

function handleGetQuestions(): void {
    $db = getDB();
    $categoryId = $_GET['category_id'] ?? null;
    if ($categoryId) {
        $stmt = $db->prepare(
            "SELECT q.*, c.name as category_name 
             FROM questions q 
             JOIN categories c ON q.category_id = c.id 
             WHERE q.category_id = ? 
             ORDER BY q.id"
        );
        $stmt->execute([(int)$categoryId]);
    } else {
        $stmt = $db->query(
            "SELECT q.*, c.name as category_name 
             FROM questions q 
             JOIN categories c ON q.category_id = c.id 
             ORDER BY q.id"
        );
    }
    $questions = $stmt->fetchAll();

    // Додавання варіантів відповідей до кожного запитання
    foreach ($questions as &$question) {
        $answerStmt = $db->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY sort_order");
        $answerStmt->execute([$question['id']]);
        $question['answers'] = $answerStmt->fetchAll();
    }

    echo json_encode($questions);
}

function handleGetQuestion(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT q.*, c.name as category_name FROM questions q JOIN categories c ON q.category_id = c.id WHERE q.id = ?");
    $stmt->execute([$id]);
    $question = $stmt->fetch();

    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Запитання не знайдено']);
        return;
    }

    $answerStmt = $db->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY sort_order");
    $answerStmt->execute([$id]);
    $question['answers'] = $answerStmt->fetchAll();

    echo json_encode($question);
}

function handleCreateQuestion(): void {
    $data = getJsonInput();
    $questionText = sanitize($data['question_text'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);
    $points = (int)($data['points'] ?? 1);
    $answers = $data['answers'] ?? [];

    if (empty($questionText) || $categoryId <= 0 || count($answers) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть текст запитання, категорію та мінімум 2 варіанти відповідей']);
        return;
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO questions (category_id, question_text, points) VALUES (?, ?, ?)");
        $stmt->execute([$categoryId, $questionText, $points]);
        $questionId = $db->lastInsertId();

        $answerStmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES (?, ?, ?, ?)");
        foreach ($answers as $i => $answer) {
            $answerStmt->execute([
                $questionId,
                sanitize($answer['answer_text'] ?? ''),
                (int)($answer['is_correct'] ?? 0),
                $i + 1
            ]);
        }

        $db->commit();
        echo json_encode(['id' => $questionId, 'success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Помилка створення запитання']);
    }
}

function handleUpdateQuestion(int $id): void {
    $data = getJsonInput();
    $questionText = sanitize($data['question_text'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);
    $points = (int)($data['points'] ?? 1);
    $answers = $data['answers'] ?? [];

    if (empty($questionText) || $categoryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть текст запитання та категорію']);
        return;
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE questions SET question_text = ?, category_id = ?, points = ? WHERE id = ?");
        $stmt->execute([$questionText, $categoryId, $points, $id]);

        if (!empty($answers)) {
            $db->prepare("DELETE FROM answers WHERE question_id = ?")->execute([$id]);
            $answerStmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES (?, ?, ?, ?)");
            foreach ($answers as $i => $answer) {
                $answerStmt->execute([
                    $id,
                    sanitize($answer['answer_text'] ?? ''),
                    (int)($answer['is_correct'] ?? 0),
                    $i + 1
                ]);
            }
        }

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Помилка оновлення запитання']);
    }
}

function handleDeleteQuestion(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

/**
 * Імпорт запитань з JSON
 * Формат: { "questions": [{ "category_id": 1, "question_text": "...", "points": 1, "answers": [{ "answer_text": "...", "is_correct": 0 }] }] }
 */
function handleImportQuestions(): void {
    $data = getJsonInput();
    $questions = $data['questions'] ?? [];

    if (empty($questions)) {
        http_response_code(400);
        echo json_encode(['error' => 'Не знайдено запитань для імпорту']);
        return;
    }

    $db = getDB();
    $db->beginTransaction();
    $imported = 0;

    try {
        foreach ($questions as $q) {
            $questionText = sanitize($q['question_text'] ?? '');
            $categoryId = (int)($q['category_id'] ?? 0);
            $points = (int)($q['points'] ?? 1);
            $answers = $q['answers'] ?? [];

            if (empty($questionText) || $categoryId <= 0 || count($answers) < 2) {
                continue;
            }

            $stmt = $db->prepare("INSERT INTO questions (category_id, question_text, points) VALUES (?, ?, ?)");
            $stmt->execute([$categoryId, $questionText, $points]);
            $questionId = $db->lastInsertId();

            $answerStmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES (?, ?, ?, ?)");
            foreach ($answers as $i => $answer) {
                $answerStmt->execute([
                    $questionId,
                    sanitize($answer['answer_text'] ?? ''),
                    (int)($answer['is_correct'] ?? 0),
                    $i + 1
                ]);
            }
            $imported++;
        }

        $db->commit();
        echo json_encode(['success' => true, 'imported' => $imported]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Помилка імпорту запитань']);
    }
}

// ===================== ІГРИ =====================

/**
 * Створення нової гри з двома командами
 */
function handleCreateGame(): void {
    $data = getJsonInput();
    $topicId = (int)($data['topic_id'] ?? 0);
    $classId = !empty($data['class_id']) ? (int)$data['class_id'] : null;
    $team1Name = sanitize($data['team1_name'] ?? '');
    $team2Name = sanitize($data['team2_name'] ?? '');
    $team1Color = sanitize($data['team1_color'] ?? '#3498db');
    $team2Color = sanitize($data['team2_color'] ?? '#e74c3c');

    if ($topicId <= 0 || empty($team1Name) || empty($team2Name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть тему та назви обох команд']);
        return;
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        // Створення гри
        $stmt = $db->prepare("INSERT INTO games (topic_id, class_id, status) VALUES (?, ?, 'waiting')");
        $stmt->execute([$topicId, $classId]);
        $gameId = $db->lastInsertId();

        // Скидання використаних запитань для цієї теми
        $db->prepare(
            "UPDATE questions SET is_used = 0 WHERE category_id IN (SELECT id FROM categories WHERE topic_id = ?)"
        )->execute([$topicId]);

        // Створення команд
        $teamStmt = $db->prepare("INSERT INTO teams (game_id, name, score, color) VALUES (?, ?, 0, ?)");
        $teamStmt->execute([$gameId, $team1Name, $team1Color]);
        $teamStmt->execute([$gameId, $team2Name, $team2Color]);

        $db->commit();
        echo json_encode(['id' => $gameId, 'success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Помилка створення гри']);
    }
}

/**
 * Отримання інформації про гру
 */
function handleGetGame(int $id): void {
    $db = getDB();

    // Дані гри
    $stmt = $db->prepare(
        "SELECT g.*, t.name as topic_name, c.name as class_name 
         FROM games g 
         JOIN topics t ON g.topic_id = t.id 
         LEFT JOIN classes c ON g.class_id = c.id 
         WHERE g.id = ?"
    );
    $stmt->execute([$id]);
    $game = $stmt->fetch();

    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Гру не знайдено']);
        return;
    }

    // Команди
    $teamStmt = $db->prepare("SELECT * FROM teams WHERE game_id = ?");
    $teamStmt->execute([$id]);
    $game['teams'] = $teamStmt->fetchAll();

    // Категорії з кількістю невикористаних запитань
    $catStmt = $db->prepare(
        "SELECT c.*, 
                (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id AND q.is_used = 0) as remaining_questions,
                (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id) as total_questions
         FROM categories c 
         WHERE c.topic_id = ? 
         ORDER BY c.name"
    );
    $catStmt->execute([$game['topic_id']]);
    $game['categories'] = $catStmt->fetchAll();

    // Поточне запитання
    if ($game['current_question_id']) {
        $qStmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
        $qStmt->execute([$game['current_question_id']]);
        $question = $qStmt->fetch();
        if ($question) {
            $aStmt = $db->prepare("SELECT id, answer_text, sort_order FROM answers WHERE question_id = ? ORDER BY sort_order");
            $aStmt->execute([$question['id']]);
            $question['answers'] = $aStmt->fetchAll();
        }
        $game['current_question'] = $question;
    }

    echo json_encode($game);
}

/**
 * Запуск гри
 */
function handleStartGame(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE games SET status = 'active' WHERE id = ? AND status = 'waiting'");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

/**
 * Завершення гри
 */
function handleFinishGame(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE games SET status = 'finished', finished_at = NOW(), current_question_id = NULL WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

/**
 * Вибір запитання з категорії (адмін)
 */
function handleSelectQuestion(int $gameId): void {
    $data = getJsonInput();
    $categoryId = (int)($data['category_id'] ?? 0);

    if ($categoryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть категорію']);
        return;
    }

    $db = getDB();

    // Отримання випадкового невикористаного запитання з категорії
    $stmt = $db->prepare(
        "SELECT id FROM questions WHERE category_id = ? AND is_used = 0 ORDER BY RAND() LIMIT 1"
    );
    $stmt->execute([$categoryId]);
    $question = $stmt->fetch();

    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Немає доступних запитань у цій категорії']);
        return;
    }

    // Позначення запитання як використаного та встановлення його поточним
    $db->prepare("UPDATE questions SET is_used = 1 WHERE id = ?")->execute([$question['id']]);
    $db->prepare("UPDATE games SET current_question_id = ? WHERE id = ?")->execute([$question['id'], $gameId]);

    // Повернення запитання з відповідями
    $qStmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $qStmt->execute([$question['id']]);
    $fullQuestion = $qStmt->fetch();

    $aStmt = $db->prepare("SELECT id, answer_text, sort_order FROM answers WHERE question_id = ? ORDER BY sort_order");
    $aStmt->execute([$question['id']]);
    $fullQuestion['answers'] = $aStmt->fetchAll();

    echo json_encode($fullQuestion);
}

/**
 * Відповідь на запитання
 */
function handleSubmitAnswer(int $gameId): void {
    $data = getJsonInput();
    $teamId = (int)($data['team_id'] ?? 0);
    $answerId = (int)($data['answer_id'] ?? 0);

    if ($teamId <= 0 || $answerId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Вкажіть команду та відповідь']);
        return;
    }

    $db = getDB();

    // Отримання поточного запитання гри
    $gameStmt = $db->prepare("SELECT current_question_id FROM games WHERE id = ?");
    $gameStmt->execute([$gameId]);
    $game = $gameStmt->fetch();

    if (!$game || !$game['current_question_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Немає активного запитання']);
        return;
    }

    $questionId = $game['current_question_id'];

    // Перевірка правильності відповіді
    $answerStmt = $db->prepare("SELECT is_correct FROM answers WHERE id = ? AND question_id = ?");
    $answerStmt->execute([$answerId, $questionId]);
    $answer = $answerStmt->fetch();

    if (!$answer) {
        http_response_code(400);
        echo json_encode(['error' => 'Невірний варіант відповіді']);
        return;
    }

    $isCorrect = (bool)$answer['is_correct'];

    // Збереження відповіді команди
    $saveStmt = $db->prepare("INSERT INTO team_answers (team_id, question_id, answer_id, is_correct) VALUES (?, ?, ?, ?)");
    $saveStmt->execute([$teamId, $questionId, $answerId, $isCorrect ? 1 : 0]);

    // Нарахування балів при правильній відповіді
    if ($isCorrect) {
        $pointsStmt = $db->prepare("SELECT points FROM questions WHERE id = ?");
        $pointsStmt->execute([$questionId]);
        $question = $pointsStmt->fetch();
        $points = $question['points'] ?? 1;

        $db->prepare("UPDATE teams SET score = score + ? WHERE id = ?")->execute([$points, $teamId]);
    }

    // Отримання правильної відповіді для показу
    $correctStmt = $db->prepare("SELECT id FROM answers WHERE question_id = ? AND is_correct = 1");
    $correctStmt->execute([$questionId]);
    $correctAnswer = $correctStmt->fetch();

    // Зняття поточного запитання
    $db->prepare("UPDATE games SET current_question_id = NULL WHERE id = ?")->execute([$gameId]);

    // Отримання оновлених балів
    $teamStmt = $db->prepare("SELECT id, name, score, color FROM teams WHERE game_id = ?");
    $teamStmt->execute([$gameId]);
    $teams = $teamStmt->fetchAll();

    echo json_encode([
        'is_correct' => $isCorrect,
        'correct_answer_id' => $correctAnswer['id'] ?? null,
        'teams' => $teams
    ]);
}

/**
 * Отримання активної гри
 */
function handleGetActiveGame(): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM games WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $game = $stmt->fetch();

    if ($game) {
        handleGetGame((int)$game['id']);
    } else {
        echo json_encode(null);
    }
}
