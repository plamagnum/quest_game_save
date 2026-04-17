-- Ініціалізація бази даних для квест-гри
-- Створення бази даних та таблиць

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE quest_game;

-- Таблиця класів (5-9 класи)
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'Назва класу',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця предметів
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Назва предмету',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця тем
CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL COMMENT 'ID предмету',
    name VARCHAR(255) NOT NULL COMMENT 'Назва теми',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця категорій запитань
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL COMMENT 'ID теми',
    name VARCHAR(255) NOT NULL COMMENT 'Назва категорії',
    color VARCHAR(7) DEFAULT '#3498db' COMMENT 'Колір категорії',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця запитань
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL COMMENT 'ID категорії',
    question_text TEXT NOT NULL COMMENT 'Текст запитання',
    points INT DEFAULT 1 COMMENT 'Кількість балів за правильну відповідь',
    is_used TINYINT(1) DEFAULT 0 COMMENT 'Чи було використано у поточній грі',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця варіантів відповідей
CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL COMMENT 'ID запитання',
    answer_text TEXT NOT NULL COMMENT 'Текст відповіді',
    is_correct TINYINT(1) DEFAULT 0 COMMENT 'Чи правильна відповідь',
    sort_order INT DEFAULT 0 COMMENT 'Порядок відображення',
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця ігор
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL COMMENT 'ID теми гри',
    class_id INT DEFAULT NULL COMMENT 'ID класу',
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting' COMMENT 'Статус гри',
    current_question_id INT DEFAULT NULL COMMENT 'Поточне запитання',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (current_question_id) REFERENCES questions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця команд
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL COMMENT 'ID гри',
    name VARCHAR(100) NOT NULL COMMENT 'Назва команди',
    score INT DEFAULT 0 COMMENT 'Поточний рахунок',
    color VARCHAR(7) DEFAULT '#3498db' COMMENT 'Колір команди',
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця відповідей команд
CREATE TABLE IF NOT EXISTS team_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL COMMENT 'ID команди',
    question_id INT NOT NULL COMMENT 'ID запитання',
    answer_id INT NOT NULL COMMENT 'ID обраної відповіді',
    is_correct TINYINT(1) DEFAULT 0 COMMENT 'Чи правильна відповідь',
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця адміністраторів
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Логін адміністратора',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Хеш пароля',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Додавання адміністратора за замовчуванням (логін: admin, пароль: admin123)
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Додавання початкових класів
INSERT INTO classes (name) VALUES
('5 клас'), ('6 клас'), ('7 клас'), ('8 клас'), ('9 клас');

-- Додавання початкових предметів
INSERT INTO subjects (name) VALUES
('Безпека життєдіяльності'), ('Інформатика');

-- Додавання початкових тем
INSERT INTO topics (subject_id, name) VALUES
(1, 'Мінна безпека'),
(2, 'Негативний вплив інтернету та смартфонів');

-- Додавання категорій для теми "Мінна безпека"
INSERT INTO categories (topic_id, name, color) VALUES
(1, 'Загальні знання про міни', '#e74c3c'),
(1, 'Правила поведінки', '#f39c12'),
(1, 'Знаки небезпеки', '#2ecc71'),
(1, 'Перша допомога', '#9b59b6');

-- Додавання категорій для теми "Негативний вплив інтернету"
INSERT INTO categories (topic_id, name, color) VALUES
(2, 'Кібербезпека', '#3498db'),
(2, 'Здоров\'я та екранний час', '#1abc9c'),
(2, 'Соціальні мережі', '#e67e22'),
(2, 'Цифрова грамотність', '#8e44ad');

-- Додавання запитань для категорії "Загальні знання про міни"
INSERT INTO questions (category_id, question_text, points) VALUES
(1, 'Що таке протипіхотна міна?', 1),
(1, 'Яка основна небезпека мін після завершення бойових дій?', 1),
(1, 'Що робити, якщо ви побачили підозрілий предмет на землі?', 1);

-- Варіанти відповідей для запитання 1
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(1, 'Вибуховий пристрій, що спрацьовує від тиску людини', 1, 1),
(1, 'Корисна копалина', 0, 2),
(1, 'Будівельний матеріал', 0, 3),
(1, 'Військова техніка', 0, 4);

-- Варіанти відповідей для запитання 2
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(2, 'Вони стають безпечними з часом', 0, 1),
(2, 'Вони залишаються небезпечними десятиліттями', 1, 2),
(2, 'Вони розчиняються у ґрунті', 0, 3),
(2, 'Вони самознищуються через рік', 0, 4);

-- Варіанти відповідей для запитання 3
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(3, 'Підняти та розглянути', 0, 1),
(3, 'Кинути каменем у нього', 0, 2),
(3, 'Не чіпати та повідомити дорослих або ДСНС', 1, 3),
(3, 'Закопати у землю', 0, 4);

-- Додавання запитань для категорії "Правила поведінки"
INSERT INTO questions (category_id, question_text, points) VALUES
(2, 'Як правильно поводитися на території з попереджувальними знаками про міни?', 1),
(2, 'Що означає знак з червоним трикутником та написом "Міни"?', 1);

-- Варіанти відповідей для запитання 4
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(4, 'Можна гуляти обережно', 0, 1),
(4, 'Негайно покинути територію тим самим шляхом', 1, 2),
(4, 'Бігти в будь-якому напрямку', 0, 3),
(4, 'Сісти на місці і чекати', 0, 4);

-- Варіанти відповідей для запитання 5
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(5, 'Територія безпечна для прогулянок', 0, 1),
(5, 'Тут можна збирати гриби', 0, 2),
(5, 'Небезпечна територія, заборонено входити', 1, 3),
(5, 'Тут проводяться військові навчання', 0, 4);

-- Додавання запитань для категорії "Кібербезпека"
INSERT INTO questions (category_id, question_text, points) VALUES
(5, 'Що таке фішинг в інтернеті?', 1),
(5, 'Яку інформацію НІКОЛИ не можна повідомляти в інтернеті?', 1),
(5, 'Що таке кібербулінг?', 1);

-- Варіанти відповідей для запитання 6
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(6, 'Онлайн-гра про рибалку', 0, 1),
(6, 'Шахрайство з метою отримання особистих даних', 1, 2),
(6, 'Програма для захисту комп\'ютера', 0, 3),
(6, 'Соціальна мережа', 0, 4);

-- Варіанти відповідей для запитання 7
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(7, 'Улюблений колір', 0, 1),
(7, 'Назву школи', 0, 2),
(7, 'Паролі та дані банківських карток', 1, 3),
(7, 'Улюблену книгу', 0, 4);

-- Варіанти відповідей для запитання 8
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(8, 'Комп\'ютерна гра', 0, 1),
(8, 'Цькування та переслідування в інтернеті', 1, 2),
(8, 'Вид програмування', 0, 3),
(8, 'Онлайн-навчання', 0, 4);

-- Додавання запитань для категорії "Здоров'я та екранний час"
INSERT INTO questions (category_id, question_text, points) VALUES
(6, 'Скільки часу рекомендовано проводити за екраном дітям 10-14 років?', 1),
(6, 'Який негативний вплив має тривале використання смартфона на зір?', 1);

-- Варіанти відповідей для запитання 9
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(9, 'Без обмежень', 0, 1),
(9, 'Не більше 1-2 годин на день', 1, 2),
(9, '8 годин на день', 0, 3),
(9, '5 годин на день', 0, 4);

-- Варіанти відповідей для запитання 10
INSERT INTO answers (question_id, answer_text, is_correct, sort_order) VALUES
(10, 'Покращує зір', 0, 1),
(10, 'Не впливає на зір', 0, 2),
(10, 'Може спричинити короткозорість та сухість очей', 1, 3),
(10, 'Лікує далекозорість', 0, 4);
