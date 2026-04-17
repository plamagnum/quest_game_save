<?php
/**
 * PHP Парсер запитань для квест-гри
 * 
 * Цей парсер завантажує HTML сторінки з освітніх сайтів,
 * витягує запитання та варіанти відповідей, зберігає у JSON.
 * 
 * ВИКОРИСТАННЯ:
 *   php parser_php.php
 * 
 * НАЛАШТУВАННЯ:
 *   Змінюйте CSS-селектори у масиві $selectors відповідно до структури сайту.
 *   Додавайте нові URL у масив $urls.
 */

// ===================== КОНФІГУРАЦІЯ =====================

// URL сторінок для парсингу
$urls = [
    // Приклади URL - замініть на реальні сайти з тестами
    // 'https://example.com/test-mine-safety',
    // 'https://example.com/test-internet-safety',
];

// CSS-селектори для пошуку елементів на сторінці
// Змінюйте ці селектори відповідно до HTML-структури сайту
$selectors = [
    // Контейнер запитання
    'question_container' => '.question-block',
    
    // Текст запитання всередині контейнера
    'question_text' => '.question-text',
    
    // Контейнер варіантів відповідей
    'answers_container' => '.answers-list',
    
    // Окремий варіант відповіді
    'answer_item' => '.answer-item',
    
    // Текст відповіді
    'answer_text' => '.answer-text',
    
    // Мітка правильної відповіді (клас або атрибут)
    'correct_marker_class' => 'correct',
    'correct_marker_attr' => 'data-correct',
];

// ID категорії за замовчуванням для імпорту
$defaultCategoryId = 1;

// Файл для збереження результатів
$outputFile = __DIR__ . '/parsed_questions.json';

// ===================== ПАРСЕР =====================

/**
 * Головна функція парсингу
 */
function parseQuestions(array $urls, array $selectors, int $categoryId): array {
    $allQuestions = [];
    
    foreach ($urls as $url) {
        echo "📥 Завантаження: {$url}\n";
        
        $html = fetchPage($url);
        if (!$html) {
            echo "❌ Помилка завантаження: {$url}\n";
            continue;
        }
        
        $questions = extractQuestions($html, $selectors, $categoryId);
        $allQuestions = array_merge($allQuestions, $questions);
        
        echo "✅ Знайдено запитань: " . count($questions) . "\n";
        
        // Затримка між запитами для уникнення блокування
        sleep(1);
    }
    
    return $allQuestions;
}

/**
 * Завантаження HTML сторінки
 * 
 * @param string $url URL сторінки
 * @return string|false HTML код або false при помилці
 */
function fetchPage(string $url): string|false {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: uk-UA,uk;q=0.9',
            ],
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    
    $html = @file_get_contents($url, false, $context);
    return $html !== false ? $html : false;
}

/**
 * Витягування запитань з HTML
 * 
 * @param string $html HTML код сторінки
 * @param array $selectors CSS-селектори
 * @param int $categoryId ID категорії
 * @return array масив запитань
 */
function extractQuestions(string $html, array $selectors, int $categoryId): array {
    $questions = [];
    
    // Створення DOM документу
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    
    // Пошук контейнерів запитань
    $questionNodes = querySelectorAll($xpath, $selectors['question_container']);
    
    foreach ($questionNodes as $questionNode) {
        // Витягування тексту запитання
        $textNodes = querySelectorAll($xpath, $selectors['question_text'], $questionNode);
        if (empty($textNodes)) continue;
        
        $questionText = trim($textNodes[0]->textContent);
        if (empty($questionText)) continue;
        
        // Витягування варіантів відповідей
        $answerNodes = querySelectorAll($xpath, $selectors['answer_item'], $questionNode);
        $answers = [];
        
        foreach ($answerNodes as $index => $answerNode) {
            // Текст відповіді
            $answerTextNodes = querySelectorAll($xpath, $selectors['answer_text'], $answerNode);
            $answerText = !empty($answerTextNodes) 
                ? trim($answerTextNodes[0]->textContent) 
                : trim($answerNode->textContent);
            
            if (empty($answerText)) continue;
            
            // Перевірка чи правильна відповідь
            $isCorrect = false;
            
            // Перевірка за класом
            if ($answerNode->getAttribute('class') && 
                strpos($answerNode->getAttribute('class'), $selectors['correct_marker_class']) !== false) {
                $isCorrect = true;
            }
            
            // Перевірка за атрибутом
            if ($answerNode->getAttribute($selectors['correct_marker_attr']) === 'true' ||
                $answerNode->getAttribute($selectors['correct_marker_attr']) === '1') {
                $isCorrect = true;
            }
            
            $answers[] = [
                'answer_text' => $answerText,
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }
        
        // Додавання запитання тільки якщо є мінімум 2 відповіді
        if (count($answers) >= 2) {
            $questions[] = [
                'category_id' => $categoryId,
                'question_text' => $questionText,
                'points' => 1,
                'answers' => $answers,
            ];
        }
    }
    
    return $questions;
}

/**
 * Спрощена реалізація querySelectorAll через XPath
 * Підтримує прості CSS-селектори: .class, #id, tag, tag.class
 * 
 * @param DOMXPath $xpath об'єкт XPath
 * @param string $selector CSS-селектор
 * @param DOMNode|null $context контекстний вузол
 * @return array масив знайдених вузлів
 */
function querySelectorAll(DOMXPath $xpath, string $selector, ?DOMNode $context = null): array {
    $xpathQuery = cssToXpath($selector);
    $prefix = $context ? '.' : '';
    
    $nodes = $xpath->query($prefix . $xpathQuery, $context);
    
    $result = [];
    if ($nodes) {
        foreach ($nodes as $node) {
            $result[] = $node;
        }
    }
    
    return $result;
}

/**
 * Конвертація простого CSS-селектора в XPath
 * 
 * @param string $css CSS-селектор
 * @return string XPath вираз
 */
function cssToXpath(string $css): string {
    $css = trim($css);
    
    // .class
    if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $css, $m)) {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$m[1]} ')]";
    }
    
    // #id
    if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $css, $m)) {
        return "//*[@id='{$m[1]}']";
    }
    
    // tag.class
    if (preg_match('/^([a-zA-Z0-9]+)\.([a-zA-Z0-9_-]+)$/', $css, $m)) {
        return "//{$m[1]}[contains(concat(' ', normalize-space(@class), ' '), ' {$m[2]} ')]";
    }
    
    // tag
    if (preg_match('/^[a-zA-Z0-9]+$/', $css)) {
        return "//{$css}";
    }
    
    // Складніші селектори - повернення як є (може не працювати)
    return "//{$css}";
}

/**
 * Збереження результатів у JSON файл
 * 
 * @param array $questions масив запитань
 * @param string $outputFile шлях до файлу
 */
function saveToJson(array $questions, string $outputFile): void {
    $data = [
        'questions' => $questions,
        'parsed_at' => date('Y-m-d H:i:s'),
        'total' => count($questions),
    ];
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($outputFile, $json);
    
    echo "\n💾 Збережено у: {$outputFile}\n";
    echo "📊 Всього запитань: " . count($questions) . "\n";
}

// ===================== ЗАПУСК =====================

echo "🔄 Парсер запитань для квест-гри (PHP)\n";
echo "========================================\n\n";

if (empty($urls)) {
    echo "⚠️  Додайте URL сторінок у масив \$urls для парсингу.\n\n";
    
    // Демонстраційний режим - створення прикладу JSON
    $demoQuestions = [
        [
            'category_id' => 1,
            'question_text' => 'Що таке протитанкова міна?',
            'points' => 1,
            'answers' => [
                ['answer_text' => 'Вибуховий пристрій проти бронетехніки', 'is_correct' => 1],
                ['answer_text' => 'Підземне сховище', 'is_correct' => 0],
                ['answer_text' => 'Тип військової техніки', 'is_correct' => 0],
                ['answer_text' => 'Захисна споруда', 'is_correct' => 0],
            ],
        ],
        [
            'category_id' => 5,
            'question_text' => 'Що таке двофакторна автентифікація?',
            'points' => 1,
            'answers' => [
                ['answer_text' => 'Два паролі для входу', 'is_correct' => 0],
                ['answer_text' => 'Підтвердження входу через додатковий канал (SMS, додаток)', 'is_correct' => 1],
                ['answer_text' => 'Два комп\'ютери для входу', 'is_correct' => 0],
                ['answer_text' => 'Подвійне шифрування', 'is_correct' => 0],
            ],
        ],
    ];
    
    saveToJson($demoQuestions, $outputFile);
    echo "\n📌 Цей файл можна імпортувати через адмін-панель (вкладка 'Імпорт').\n";
} else {
    $questions = parseQuestions($urls, $selectors, $defaultCategoryId);
    
    if (!empty($questions)) {
        saveToJson($questions, $outputFile);
    } else {
        echo "⚠️  Запитань не знайдено. Перевірте CSS-селектори.\n";
    }
}
