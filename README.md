# 🎮 Квест-гра: Мінна безпека та Кібербезпека

Інтерактивний веб-додаток для проведення квест-ігор у школі для учнів 5-9 класів на теми **"Мінна безпека"** та **"Негативний вплив інтернету та смартфонів"**.

## 📋 Можливості

- **Ігровий дашборд** — дві команди, бали в реальному часі, категорії запитань
- **Адмін-панель** — управління класами, предметами, темами, категоріями, запитаннями
- **4 варіанти відповідей** — підсвічування зеленим (правильно) / червоним (неправильно)
- **Імпорт запитань** — через JSON API або файл
- **Парсери** — PHP та Python для збору запитань з веб-сайтів
- **Темна/світла тема** — перемикання одним кліком
- **Адаптивний дизайн** — mobile-first, працює на телефонах, планшетах, ПК

## 🛠️ Технічний стек

| Компонент | Технологія |
|-----------|-----------|
| Веб-сервер | Nginx |
| Backend | PHP 8.2 (PHP-FPM) |
| Frontend | JavaScript (Vanilla), CSS3 |
| База даних | MySQL 8.0 |
| Адмін БД | phpMyAdmin |
| Деплой | Docker Compose |
| Парсери | PHP, Python 3 |

## 🚀 Покрокова інструкція з розгортання

### Передумови

- [Docker](https://docs.docker.com/get-docker/) та [Docker Compose](https://docs.docker.com/compose/install/)
- Git

### Крок 1: Клонування репозиторію

```bash
git clone https://github.com/plamagnum/quest_game_save.git
cd quest_game_save
```

### Крок 2: Запуск через Docker Compose

```bash
docker-compose up -d --build
```

### Крок 3: Відкрити у браузері

| Сервіс | URL |
|--------|-----|
| 🎮 Квест-гра | [http://localhost:8080](http://localhost:8080) |
| 🗄️ phpMyAdmin | [http://localhost:8081](http://localhost:8081) |

### Крок 4: Вхід адміністратора

- **Логін:** `admin`
- **Пароль:** `admin123`

> ⚠️ Змініть пароль після першого входу!

## 📖 Як користуватися

### Для адміністратора:

1. **Увійдіть** як адмін (кнопка "🔑 Увійти")
2. **Створіть гру** — оберіть тему, введіть назви двох команд
3. **Натисніть на категорію** — з'явиться випадкове запитання
4. **Учні відповідають** — правильна відповідь підсвічується зеленим, неправильна — червоним
5. **Бали нараховуються** автоматично при правильній відповіді
6. **Завершіть гру** — з'явиться переможець

### Для учнів:

1. Відкрийте сторінку гри у браузері
2. Оберіть свою команду
3. Відповідайте на запитання, які обирає адмін
4. Стежте за рахунком на дашборді

### Адмін-панель (⚙️):

- **Класи** — додавання/редагування класів (5-9)
- **Предмети** — управління предметами
- **Теми** — створення тем для квестів
- **Категорії** — групування запитань за категоріями
- **Запитання** — додавання запитань з 4 варіантами відповідей
- **Імпорт** — завантаження запитань з JSON

## 📥 Імпорт запитань через JSON

### Формат JSON:

```json
{
  "questions": [
    {
      "category_id": 1,
      "question_text": "Ваше запитання?",
      "points": 1,
      "answers": [
        { "answer_text": "Правильна відповідь", "is_correct": 1 },
        { "answer_text": "Неправильна відповідь", "is_correct": 0 },
        { "answer_text": "Неправильна відповідь", "is_correct": 0 },
        { "answer_text": "Неправильна відповідь", "is_correct": 0 }
      ]
    }
  ]
}
```

### Через API:

```bash
curl -X POST http://localhost:8080/api/questions/import \
  -H "Content-Type: application/json" \
  -d @parsers/parsed_questions.json
```

## 🔍 Парсери

### PHP парсер:

```bash
cd parsers
php parser_php.php
```

### Python парсер:

```bash
cd parsers
pip install -r requirements.txt
python parser_python.py
```

### Налаштування парсерів:

1. Додайте URL сторінок у масив/список `$urls` / `URLS`
2. Змініть CSS-селектори у `$selectors` / `SELECTORS` відповідно до структури сайту
3. Запустіть парсер — результат буде у `parsed_questions.json`
4. Імпортуйте файл через адмін-панель або API

## 🗄️ Структура бази даних

```
classes          — Класи (5-9)
subjects         — Предмети
topics           — Теми квестів
categories       — Категорії запитань
questions        — Запитання
answers          — Варіанти відповідей
games            — Ігри
teams            — Команди
team_answers     — Відповіді команд
admins           — Адміністратори
```

## 📁 Структура проекту

```
quest_game_save/
├── docker-compose.yml          # Конфігурація Docker
├── docker/
│   ├── nginx/
│   │   └── default.conf        # Конфігурація Nginx
│   ├── php/
│   │   └── Dockerfile          # PHP-FPM образ
│   └── mysql/
│       └── init.sql            # Ініціалізація БД
├── src/
│   ├── index.html              # Головна сторінка
│   ├── includes/
│   │   └── config.php          # Конфігурація PHP
│   ├── api/
│   │   └── index.php           # API маршрутизатор
│   └── assets/
│       ├── css/
│       │   └── style.css       # Стилі (темна/світла тема)
│       └── js/
│           └── app.js          # Клієнтський JavaScript
├── parsers/
│   ├── parser_php.php          # PHP парсер
│   ├── parser_python.py        # Python парсер
│   └── requirements.txt       # Залежності Python
└── README.md                   # Документація
```

## 🔧 Корисні команди

```bash
# Запуск
docker-compose up -d --build

# Зупинка
docker-compose down

# Перегляд логів
docker-compose logs -f

# Перезапуск PHP
docker-compose restart php

# Підключення до MySQL
docker-compose exec mysql mysql -u quest_user -pquest_password quest_game

# Видалення даних та перезапуск
docker-compose down -v && docker-compose up -d --build
```

## 📝 Ліцензія

MIT License
