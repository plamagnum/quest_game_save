#!/usr/bin/env python3
"""
Python Парсер запитань для квест-гри

Цей парсер завантажує HTML сторінки з освітніх сайтів,
витягує запитання та варіанти відповідей, зберігає у JSON.

ВИКОРИСТАННЯ:
    pip install requests beautifulsoup4
    python parser_python.py

НАЛАШТУВАННЯ:
    Змінюйте CSS-селектори у словнику SELECTORS відповідно до структури сайту.
    Додавайте нові URL у список URLS.
"""

import json
import time
import os
from datetime import datetime

try:
    import requests
    from bs4 import BeautifulSoup
    HAS_DEPS = True
except ImportError:
    HAS_DEPS = False
    print("⚠️  Встановіть залежності: pip install requests beautifulsoup4")

# ===================== КОНФІГУРАЦІЯ =====================

# URL сторінок для парсингу
URLS = [
    # Приклади URL - замініть на реальні сайти з тестами
    # 'https://example.com/test-mine-safety',
    # 'https://example.com/test-internet-safety',
]

# CSS-селектори для пошуку елементів на сторінці
# Змінюйте ці селектори відповідно до HTML-структури сайту
SELECTORS = {
    # Контейнер запитання
    'question_container': '.question-block',
    
    # Текст запитання всередині контейнера
    'question_text': '.question-text',
    
    # Окремий варіант відповіді
    'answer_item': '.answer-item',
    
    # Текст відповіді (всередині answer_item)
    'answer_text': '.answer-text',
    
    # CSS-клас правильної відповіді
    'correct_class': 'correct',
    
    # Атрибут правильної відповіді
    'correct_attr': 'data-correct',
}

# ID категорії за замовчуванням для імпорту
DEFAULT_CATEGORY_ID = 1

# Файл для збереження результатів
OUTPUT_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'parsed_questions.json')

# Заголовки HTTP запитів
HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'text/html,application/xhtml+xml',
    'Accept-Language': 'uk-UA,uk;q=0.9',
}


# ===================== ПАРСЕР =====================

def fetch_page(url: str) -> str | None:
    """
    Завантаження HTML сторінки
    
    Args:
        url: URL сторінки для завантаження
    
    Returns:
        HTML код сторінки або None при помилці
    """
    try:
        response = requests.get(url, headers=HEADERS, timeout=30, verify=False)
        response.encoding = response.apparent_encoding or 'utf-8'
        response.raise_for_status()
        return response.text
    except requests.RequestException as e:
        print(f"❌ Помилка завантаження {url}: {e}")
        return None


def extract_questions(html: str, selectors: dict, category_id: int) -> list:
    """
    Витягування запитань з HTML коду
    
    Args:
        html: HTML код сторінки
        selectors: словник CSS-селекторів
        category_id: ID категорії для запитань
    
    Returns:
        список запитань у форматі для імпорту
    """
    soup = BeautifulSoup(html, 'html.parser')
    questions = []
    
    # Пошук контейнерів запитань
    question_blocks = soup.select(selectors['question_container'])
    
    for block in question_blocks:
        # Витягування тексту запитання
        question_el = block.select_one(selectors['question_text'])
        if not question_el:
            continue
        
        question_text = question_el.get_text(strip=True)
        if not question_text:
            continue
        
        # Витягування варіантів відповідей
        answer_elements = block.select(selectors['answer_item'])
        answers = []
        
        for answer_el in answer_elements:
            # Текст відповіді
            text_el = answer_el.select_one(selectors['answer_text'])
            answer_text = text_el.get_text(strip=True) if text_el else answer_el.get_text(strip=True)
            
            if not answer_text:
                continue
            
            # Перевірка чи правильна відповідь
            is_correct = False
            
            # Перевірка за CSS-класом
            classes = answer_el.get('class', [])
            if selectors['correct_class'] in classes:
                is_correct = True
            
            # Перевірка за атрибутом
            attr_value = answer_el.get(selectors['correct_attr'], '')
            if attr_value in ('true', '1', 'yes'):
                is_correct = True
            
            answers.append({
                'answer_text': answer_text,
                'is_correct': 1 if is_correct else 0,
            })
        
        # Додавання запитання тільки якщо є мінімум 2 відповіді
        if len(answers) >= 2:
            questions.append({
                'category_id': category_id,
                'question_text': question_text,
                'points': 1,
                'answers': answers,
            })
    
    return questions


def parse_all(urls: list, selectors: dict, category_id: int) -> list:
    """
    Парсинг всіх URL
    
    Args:
        urls: список URL для парсингу
        selectors: CSS-селектори
        category_id: ID категорії
    
    Returns:
        список всіх знайдених запитань
    """
    all_questions = []
    
    for url in urls:
        print(f"📥 Завантаження: {url}")
        
        html = fetch_page(url)
        if not html:
            continue
        
        questions = extract_questions(html, selectors, category_id)
        all_questions.extend(questions)
        
        print(f"✅ Знайдено запитань: {len(questions)}")
        
        # Затримка між запитами
        time.sleep(1)
    
    return all_questions


def save_to_json(questions: list, output_file: str) -> None:
    """
    Збереження запитань у JSON файл
    
    Args:
        questions: список запитань
        output_file: шлях до файлу
    """
    data = {
        'questions': questions,
        'parsed_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'total': len(questions),
    }
    
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    
    print(f"\n💾 Збережено у: {output_file}")
    print(f"📊 Всього запитань: {len(questions)}")


# ===================== ЗАПУСК =====================

def main():
    """Головна функція"""
    print("🔄 Парсер запитань для квест-гри (Python)")
    print("=" * 44)
    print()
    
    if not HAS_DEPS:
        print("❌ Необхідні бібліотеки не встановлені.")
        print("   Виконайте: pip install requests beautifulsoup4")
        return
    
    if not URLS:
        print("⚠️  Додайте URL сторінок у список URLS для парсингу.\n")
        
        # Демонстраційний режим - створення прикладу JSON
        demo_questions = [
            {
                'category_id': 1,
                'question_text': 'Яка глибина залягання протипіхотної міни?',
                'points': 1,
                'answers': [
                    {'answer_text': 'На поверхні або до 5 см у ґрунті', 'is_correct': 1},
                    {'answer_text': '1 метр під землею', 'is_correct': 0},
                    {'answer_text': '50 см під землею', 'is_correct': 0},
                    {'answer_text': '2 метри під землею', 'is_correct': 0},
                ],
            },
            {
                'category_id': 5,
                'question_text': 'Що таке VPN?',
                'points': 1,
                'answers': [
                    {'answer_text': 'Вірус для комп\'ютера', 'is_correct': 0},
                    {'answer_text': 'Віртуальна приватна мережа для захисту з\'єднання', 'is_correct': 1},
                    {'answer_text': 'Програма для прискорення інтернету', 'is_correct': 0},
                    {'answer_text': 'Тип операційної системи', 'is_correct': 0},
                ],
            },
        ]
        
        save_to_json(demo_questions, OUTPUT_FILE)
        print("\n📌 Цей файл можна імпортувати через адмін-панель (вкладка 'Імпорт').")
    else:
        questions = parse_all(URLS, SELECTORS, DEFAULT_CATEGORY_ID)
        
        if questions:
            save_to_json(questions, OUTPUT_FILE)
        else:
            print("⚠️  Запитань не знайдено. Перевірте CSS-селектори.")


if __name__ == '__main__':
    main()
