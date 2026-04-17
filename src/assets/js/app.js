/**
 * Головний JavaScript файл квест-додатку
 * Управління грою, дашбордом та взаємодією з API
 */

// ===================== КОНФІГУРАЦІЯ =====================

const API_BASE = '/api';
let currentGame = null;       // Поточна гра
let selectedTeamId = null;    // Обрана команда
let pollingInterval = null;   // Інтервал опитування стану гри
let isAdmin = false;          // Чи авторизований як адмін

// ===================== ІНІЦІАЛІЗАЦІЯ =====================

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    checkAuth();
});

/**
 * Ініціалізація теми (темна/світла)
 */
function initTheme() {
    const savedTheme = localStorage.getItem('quest-theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeButton(savedTheme);
}

/**
 * Перемикання теми
 */
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('quest-theme', newTheme);
    updateThemeButton(newTheme);
}

/**
 * Оновлення кнопки теми
 */
function updateThemeButton(theme) {
    const btn = document.getElementById('themeToggle');
    if (btn) {
        btn.textContent = theme === 'dark' ? '☀️ Світла' : '🌙 Темна';
    }
}

// ===================== API ЗАПИТИ =====================

/**
 * Універсальна функція для API запитів
 */
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Помилка запиту');
        }

        return result;
    } catch (error) {
        console.error('API помилка:', error);
        showToast(error.message, 'error');
        throw error;
    }
}

// ===================== АВТОРИЗАЦІЯ =====================

/**
 * Перевірка стану авторизації
 */
async function checkAuth() {
    try {
        const result = await apiRequest('/check-auth');
        isAdmin = result.authenticated;
        renderApp();
    } catch {
        isAdmin = false;
        renderApp();
    }
}

/**
 * Вхід адміністратора
 */
async function handleLogin(event) {
    event.preventDefault();
    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;

    try {
        await apiRequest('/login', 'POST', { username, password });
        isAdmin = true;
        showToast('Успішний вхід!', 'success');
        renderApp();
    } catch {
        // Помилка вже показана через showToast
    }
}

/**
 * Вихід
 */
async function handleLogout() {
    await apiRequest('/logout', 'POST');
    isAdmin = false;
    currentGame = null;
    stopPolling();
    renderApp();
}

// ===================== ВІДОБРАЖЕННЯ ДОДАТКУ =====================

/**
 * Головна функція рендерингу
 */
function renderApp() {
    const app = document.getElementById('app');
    const adminControls = document.getElementById('adminControls');

    if (isAdmin) {
        adminControls.innerHTML = `
            <button class="btn btn-sm btn-warning" onclick="showAdminPanel()">⚙️ Адмін</button>
            <button class="btn btn-sm btn-danger" onclick="handleLogout()">🚪 Вийти</button>
        `;
        showDashboard();
    } else {
        adminControls.innerHTML = `
            <button class="btn btn-sm btn-primary" onclick="showLoginForm()">🔑 Увійти</button>
        `;
        showPlayerView();
    }
}

/**
 * Показ форми входу
 */
function showLoginForm() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container fade-in">
            <div class="card login-card">
                <h2 style="text-align: center; margin-bottom: 20px;">🔐 Вхід адміністратора</h2>
                <form onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label for="loginUsername">Логін</label>
                        <input type="text" id="loginUsername" class="form-control" placeholder="Введіть логін" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Пароль</label>
                        <input type="password" id="loginPassword" class="form-control" placeholder="Введіть пароль" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Увійти</button>
                </form>
            </div>
        </div>
    `;
}

/**
 * Показ дашборду адміністратора
 */
async function showDashboard() {
    const app = document.getElementById('app');
    app.innerHTML = '<div class="loader"><div class="spinner"></div></div>';

    try {
        // Перевірка активної гри
        const activeGame = await apiRequest('/games/active');
        if (activeGame && activeGame.id) {
            currentGame = activeGame;
            renderGameDashboard(true);
        } else {
            showCreateGameForm();
        }
    } catch {
        showCreateGameForm();
    }
}

/**
 * Форма створення нової гри
 */
async function showCreateGameForm() {
    const topics = await apiRequest('/topics');
    const classes = await apiRequest('/classes');

    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="container fade-in">
            <div class="card">
                <h2 class="card-title">🎮 Створити нову гру</h2>
                <form onsubmit="createGame(event)">
                    <div class="form-group">
                        <label>Тема гри</label>
                        <select id="gameTopic" class="form-control" required>
                            <option value="">Оберіть тему</option>
                            ${topics.map(t => `<option value="${t.id}">${t.name} (${t.subject_name})</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Клас</label>
                        <select id="gameClass" class="form-control">
                            <option value="">Без класу</option>
                            ${classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Назва команди 1</label>
                        <input type="text" id="team1Name" class="form-control" placeholder="Напр.: Захисники" required>
                    </div>
                    <div class="form-group">
                        <label>Колір команди 1</label>
                        <input type="color" id="team1Color" class="form-control" value="#3498db" style="height: 40px; padding: 4px;">
                    </div>
                    <div class="form-group">
                        <label>Назва команди 2</label>
                        <input type="text" id="team2Name" class="form-control" placeholder="Напр.: Розвідники" required>
                    </div>
                    <div class="form-group">
                        <label>Колір команди 2</label>
                        <input type="color" id="team2Color" class="form-control" value="#e74c3c" style="height: 40px; padding: 4px;">
                    </div>
                    <button type="submit" class="btn btn-success" style="width: 100%;">🚀 Створити гру</button>
                </form>
            </div>
        </div>
    `;
}

/**
 * Створення гри
 */
async function createGame(event) {
    event.preventDefault();

    const data = {
        topic_id: document.getElementById('gameTopic').value,
        class_id: document.getElementById('gameClass').value || null,
        team1_name: document.getElementById('team1Name').value,
        team2_name: document.getElementById('team2Name').value,
        team1_color: document.getElementById('team1Color').value,
        team2_color: document.getElementById('team2Color').value,
    };

    try {
        const result = await apiRequest('/games', 'POST', data);
        // Запуск гри відразу
        await apiRequest(`/games/${result.id}/start`, 'POST');
        currentGame = await apiRequest(`/games/${result.id}`);
        showToast('Гру створено!', 'success');
        renderGameDashboard(true);
    } catch {
        // Помилка показана
    }
}

// ===================== ІГРОВИЙ ДАШБОРД =====================

/**
 * Рендеринг ігрового дашборду
 * @param {boolean} isAdminView - чи показувати адмін-елементи
 */
function renderGameDashboard(isAdminView = false) {
    if (!currentGame) return;

    const app = document.getElementById('app');
    const game = currentGame;
    const teams = game.teams || [];
    const categories = game.categories || [];

    // Перевірка чи гра завершена
    if (game.status === 'finished') {
        renderGameResult();
        return;
    }

    let html = `<div class="container fade-in">`;

    // Дашборд команд
    html += `<div class="teams-dashboard">`;
    teams.forEach((team, index) => {
        html += `
            <div class="team-card" style="background: linear-gradient(135deg, ${team.color}, ${adjustColor(team.color, -30)});">
                <div class="team-name">${escapeHtml(team.name)}</div>
                <div class="team-score" id="score-${team.id}">${team.score}</div>
                <div class="team-score-label">балів</div>
            </div>
        `;
    });
    html += `</div>`;

    // Поточне запитання
    if (game.current_question) {
        html += renderQuestion(game.current_question, isAdminView);
    } else {
        // Категорії (внизу дашборду)
        html += `
            <div class="card">
                <h3 class="card-title">📋 Категорії запитань</h3>
                <div class="categories-grid">
        `;

        categories.forEach(cat => {
            const disabled = cat.remaining_questions <= 0;
            html += `
                <button class="category-btn ${disabled ? 'disabled' : ''}" 
                        style="--category-color: ${cat.color};"
                        ${disabled ? 'disabled' : ''}
                        onclick="${isAdminView ? `selectQuestion(${game.id}, ${cat.id})` : ''}">
                    ${escapeHtml(cat.name)}
                    <span class="remaining">${cat.remaining_questions}/${cat.total_questions}</span>
                </button>
            `;
        });

        html += `</div></div>`;

        // Вибір команди для учнів
        if (!isAdminView) {
            html += `
                <div class="card">
                    <h3 class="card-title">👥 Оберіть свою команду</h3>
                    <div class="team-select-btns">
            `;
            teams.forEach(team => {
                html += `
                    <button class="team-select-btn ${selectedTeamId === team.id ? 'selected' : ''}" 
                            style="background: ${team.color};"
                            data-team-id="${team.id}"
                            onclick="selectTeam(${team.id})">
                        ${escapeHtml(team.name)}
                    </button>
                `;
            });
            html += `</div></div>`;
        }
    }

    // Кнопки адміна
    if (isAdminView) {
        html += `
            <div style="text-align: center; margin-top: 16px;">
                <button class="btn btn-danger" onclick="finishGame(${game.id})">🏁 Завершити гру</button>
                <button class="btn btn-primary" onclick="showCreateGameForm()" style="margin-left: 8px;">➕ Нова гра</button>
            </div>
        `;
    }

    html += `</div>`;
    app.innerHTML = html;

    // Запуск опитування для оновлення стану
    startPolling(game.id, isAdminView);
}

/**
 * Рендеринг запитання
 */
function renderQuestion(question, isAdminView) {
    let html = `
        <div class="question-container fade-in">
            <div class="question-text">❓ ${escapeHtml(question.question_text)}</div>
            <div class="answers-grid">
    `;

    const labels = ['А', 'Б', 'В', 'Г'];
    question.answers.forEach((answer, index) => {
        html += `
            <button class="answer-btn" id="answer-${answer.id}"
                    onclick="submitAnswer(${answer.id})">
                <strong>${labels[index] || index + 1}.</strong>&nbsp; ${escapeHtml(answer.answer_text)}
            </button>
        `;
    });

    html += `</div></div>`;
    return html;
}

/**
 * Рендеринг результатів гри
 */
function renderGameResult() {
    const game = currentGame;
    const teams = game.teams || [];
    const winner = teams.reduce((a, b) => a.score > b.score ? a : b, teams[0]);
    const isDraw = teams.every(t => t.score === teams[0].score);

    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="container fade-in">
            <div class="card game-result">
                <h2>🏆 Гру завершено!</h2>
                <div class="teams-dashboard" style="margin-top: 24px;">
                    ${teams.map(team => `
                        <div class="team-card" style="background: linear-gradient(135deg, ${team.color}, ${adjustColor(team.color, -30)});">
                            <div class="team-name">${escapeHtml(team.name)}</div>
                            <div class="team-score">${team.score}</div>
                            <div class="team-score-label">балів</div>
                        </div>
                    `).join('')}
                </div>
                ${isDraw 
                    ? '<div class="winner" style="color: var(--warning);">🤝 Нічия!</div>'
                    : `<div class="winner-label">Переможець</div>
                       <div class="winner" style="color: ${winner.color};">🎉 ${escapeHtml(winner.name)}</div>`
                }
                ${isAdmin ? `
                    <button class="btn btn-success" onclick="showCreateGameForm()" style="margin-top: 16px;">
                        🎮 Нова гра
                    </button>
                ` : ''}
            </div>
        </div>
    `;
    stopPolling();
}

// ===================== ІГРОВА ЛОГІКА =====================

/**
 * Вибір запитання адміном з категорії
 */
async function selectQuestion(gameId, categoryId) {
    try {
        await apiRequest(`/games/${gameId}/select-question`, 'POST', { category_id: categoryId });
        await refreshGame(gameId, true);
    } catch {
        // Помилка показана
    }
}

/**
 * Вибір команди учнем
 */
function selectTeam(teamId) {
    selectedTeamId = teamId;
    // Оновлення візуального стану кнопок
    document.querySelectorAll('.team-select-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.teamId) === teamId);
    });
    showToast('Команду обрано!', 'success');
}

/**
 * Відповідь на запитання
 */
async function submitAnswer(answerId) {
    // Визначення команди
    let teamId = selectedTeamId;

    // Для адміна - показуємо вибір команди
    if (isAdmin && !teamId && currentGame) {
        const teams = currentGame.teams || [];
        if (teams.length > 0) {
            teamId = teams[0].id; // За замовчуванням перша команда
        }
    }

    if (!teamId) {
        showToast('Спочатку оберіть команду!', 'error');
        return;
    }

    // Блокування кнопок
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.classList.add('disabled');
        btn.onclick = null;
    });

    try {
        const result = await apiRequest(`/games/${currentGame.id}/answer`, 'POST', {
            team_id: teamId,
            answer_id: answerId,
        });

        // Підсвічування відповідей
        const selectedBtn = document.getElementById(`answer-${answerId}`);
        const correctBtn = document.getElementById(`answer-${result.correct_answer_id}`);

        if (result.is_correct) {
            selectedBtn.classList.add('correct');
            showToast('✅ Правильна відповідь! +1 бал', 'success');
        } else {
            selectedBtn.classList.add('incorrect');
            if (correctBtn) correctBtn.classList.add('correct');
            showToast('❌ Неправильна відповідь!', 'error');
        }

        // Оновлення балів
        if (result.teams) {
            result.teams.forEach(team => {
                const scoreEl = document.getElementById(`score-${team.id}`);
                if (scoreEl && scoreEl.textContent !== String(team.score)) {
                    scoreEl.textContent = team.score;
                    scoreEl.classList.add('score-animated');
                    setTimeout(() => scoreEl.classList.remove('score-animated'), 500);
                }
            });
        }

        // Оновлення гри через 2 секунди
        setTimeout(async () => {
            await refreshGame(currentGame.id, isAdmin);
        }, 2000);

    } catch {
        // Розблокування кнопок при помилці
        document.querySelectorAll('.answer-btn').forEach(btn => {
            btn.classList.remove('disabled');
        });
    }
}

/**
 * Завершення гри
 */
async function finishGame(gameId) {
    if (!confirm('Ви впевнені, що хочете завершити гру?')) return;

    try {
        await apiRequest(`/games/${gameId}/finish`, 'POST');
        await refreshGame(gameId, true);
        showToast('Гру завершено!', 'info');
    } catch {
        // Помилка показана
    }
}

/**
 * Оновлення стану гри
 */
async function refreshGame(gameId, isAdminView) {
    try {
        currentGame = await apiRequest(`/games/${gameId}`);
        renderGameDashboard(isAdminView);
    } catch {
        // Помилка показана
    }
}

// ===================== ОПИТУВАННЯ СТАНУ =====================

/**
 * Запуск періодичного опитування стану гри
 */
function startPolling(gameId, isAdminView) {
    stopPolling();
    pollingInterval = setInterval(async () => {
        try {
            const game = await apiRequest(`/games/${gameId}`);
            // Оновлення тільки якщо є зміни
            if (JSON.stringify(game) !== JSON.stringify(currentGame)) {
                currentGame = game;
                renderGameDashboard(isAdminView);
            }
        } catch {
            // Ігнорування помилок опитування
        }
    }, 3000); // Кожні 3 секунди
}

/**
 * Зупинка опитування
 */
function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

// ===================== ПЕРЕГЛЯД УЧНЯ =====================

/**
 * Показ інтерфейсу для учнів
 */
async function showPlayerView() {
    const app = document.getElementById('app');
    app.innerHTML = '<div class="loader"><div class="spinner"></div></div>';

    try {
        const game = await apiRequest('/games/active');
        if (game && game.id) {
            currentGame = game;
            renderGameDashboard(false);
        } else {
            app.innerHTML = `
                <div class="container">
                    <div class="card game-status">
                        <div style="font-size: 4rem;">🎮</div>
                        <h2>Квест-гра</h2>
                        <p>Очікуємо початку гри...</p>
                        <p style="margin-top: 12px; font-size: 0.9rem;">Адміністратор має створити та запустити гру</p>
                        <button class="btn btn-primary" onclick="showPlayerView()" style="margin-top: 16px;">🔄 Оновити</button>
                    </div>
                </div>
            `;
        }
    } catch {
        app.innerHTML = `
            <div class="container">
                <div class="card game-status">
                    <div style="font-size: 4rem;">⏳</div>
                    <h2>Очікуємо початку гри</h2>
                    <button class="btn btn-primary" onclick="showPlayerView()" style="margin-top: 16px;">🔄 Оновити</button>
                </div>
            </div>
        `;
    }
}

// ===================== АДМІН ПАНЕЛЬ =====================

/**
 * Показ адмін-панелі
 */
function showAdminPanel() {
    stopPolling();
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="container fade-in">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h2>⚙️ Панель адміністратора</h2>
                <button class="btn btn-primary" onclick="showDashboard()">🎮 До гри</button>
            </div>
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchAdminTab('classes', this)">📚 Класи</button>
                <button class="nav-tab" onclick="switchAdminTab('subjects', this)">📖 Предмети</button>
                <button class="nav-tab" onclick="switchAdminTab('topics', this)">📝 Теми</button>
                <button class="nav-tab" onclick="switchAdminTab('categories', this)">🏷️ Категорії</button>
                <button class="nav-tab" onclick="switchAdminTab('questions', this)">❓ Запитання</button>
                <button class="nav-tab" onclick="switchAdminTab('import', this)">📥 Імпорт</button>
            </div>
            <div id="adminContent"></div>
        </div>
    `;
    loadAdminClasses();
}

/**
 * Перемикання вкладок адмін-панелі
 */
function switchAdminTab(tab, btn) {
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    switch (tab) {
        case 'classes': loadAdminClasses(); break;
        case 'subjects': loadAdminSubjects(); break;
        case 'topics': loadAdminTopics(); break;
        case 'categories': loadAdminCategories(); break;
        case 'questions': loadAdminQuestions(); break;
        case 'import': showImportForm(); break;
    }
}

// ===================== CRUD КЛАСІВ =====================

async function loadAdminClasses() {
    const content = document.getElementById('adminContent');
    const classes = await apiRequest('/classes');
    
    content.innerHTML = `
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">📚 Управління класами</h3>
                <button class="btn btn-success btn-sm" onclick="showAddClassModal()">➕ Додати</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Назва</th><th>Дії</th></tr></thead>
                    <tbody>
                        ${classes.map(c => `
                            <tr>
                                <td>${c.id}</td>
                                <td>${escapeHtml(c.name)}</td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editClass(${c.id})">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteClass(${c.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function showAddClassModal() {
    showModal('Додати клас', `
        <div class="form-group">
            <label>Назва класу</label>
            <input type="text" id="className" class="form-control" placeholder="Напр.: 5 клас" required>
        </div>
    `, async () => {
        const name = document.getElementById('className').value;
        await apiRequest('/classes', 'POST', { name });
        closeModal();
        loadAdminClasses();
        showToast('Клас додано!', 'success');
    });
}

async function editClass(id) {
    const classes = await apiRequest('/classes');
    const cls = classes.find(c => c.id === id);
    if (!cls) return;
    const currentName = cls.name;
    showModal('Редагувати клас', `
        <div class="form-group">
            <label>Назва класу</label>
            <input type="text" id="className" class="form-control" value="${currentName}">
        </div>
    `, async () => {
        const name = document.getElementById('className').value;
        await apiRequest(`/classes/${id}`, 'PUT', { name });
        closeModal();
        loadAdminClasses();
        showToast('Клас оновлено!', 'success');
    });
}

async function deleteClass(id) {
    if (!confirm('Видалити цей клас?')) return;
    await apiRequest(`/classes/${id}`, 'DELETE');
    loadAdminClasses();
    showToast('Клас видалено!', 'success');
}

// ===================== CRUD ПРЕДМЕТІВ =====================

async function loadAdminSubjects() {
    const content = document.getElementById('adminContent');
    const subjects = await apiRequest('/subjects');
    
    content.innerHTML = `
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">📖 Управління предметами</h3>
                <button class="btn btn-success btn-sm" onclick="showAddSubjectModal()">➕ Додати</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Назва</th><th>Дії</th></tr></thead>
                    <tbody>
                        ${subjects.map(s => `
                            <tr>
                                <td>${s.id}</td>
                                <td>${escapeHtml(s.name)}</td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editSubject(${s.id})">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteSubject(${s.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function showAddSubjectModal() {
    showModal('Додати предмет', `
        <div class="form-group">
            <label>Назва предмету</label>
            <input type="text" id="subjectName" class="form-control" placeholder="Напр.: Інформатика">
        </div>
    `, async () => {
        const name = document.getElementById('subjectName').value;
        await apiRequest('/subjects', 'POST', { name });
        closeModal();
        loadAdminSubjects();
        showToast('Предмет додано!', 'success');
    });
}

async function editSubject(id) {
    const subjects = await apiRequest('/subjects');
    const subj = subjects.find(s => s.id === id);
    if (!subj) return;
    const currentName = subj.name;
    showModal('Редагувати предмет', `
        <div class="form-group">
            <label>Назва предмету</label>
            <input type="text" id="subjectName" class="form-control" value="${currentName}">
        </div>
    `, async () => {
        const name = document.getElementById('subjectName').value;
        await apiRequest(`/subjects/${id}`, 'PUT', { name });
        closeModal();
        loadAdminSubjects();
        showToast('Предмет оновлено!', 'success');
    });
}

async function deleteSubject(id) {
    if (!confirm('Видалити цей предмет?')) return;
    await apiRequest(`/subjects/${id}`, 'DELETE');
    loadAdminSubjects();
    showToast('Предмет видалено!', 'success');
}

// ===================== CRUD ТЕМ =====================

async function loadAdminTopics() {
    const content = document.getElementById('adminContent');
    const [topics, subjects] = await Promise.all([
        apiRequest('/topics'),
        apiRequest('/subjects')
    ]);
    
    content.innerHTML = `
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">📝 Управління темами</h3>
                <button class="btn btn-success btn-sm" onclick="showAddTopicModal()">➕ Додати</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Назва</th><th>Предмет</th><th>Дії</th></tr></thead>
                    <tbody>
                        ${topics.map(t => `
                            <tr>
                                <td>${t.id}</td>
                                <td>${escapeHtml(t.name)}</td>
                                <td>${escapeHtml(t.subject_name)}</td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editTopic(${t.id})">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteTopic(${t.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    // Зберігаємо предмети для модалки
    window._subjects = subjects;
}

async function showAddTopicModal() {
    const subjects = window._subjects || await apiRequest('/subjects');
    showModal('Додати тему', `
        <div class="form-group">
            <label>Предмет</label>
            <select id="topicSubject" class="form-control">
                ${subjects.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Назва теми</label>
            <input type="text" id="topicName" class="form-control" placeholder="Напр.: Мінна безпека">
        </div>
    `, async () => {
        const name = document.getElementById('topicName').value;
        const subjectId = document.getElementById('topicSubject').value;
        await apiRequest('/topics', 'POST', { name, subject_id: subjectId });
        closeModal();
        loadAdminTopics();
        showToast('Тему додано!', 'success');
    });
}

async function editTopic(id) {
    const subjects = window._subjects || await apiRequest('/subjects');
    const topics = await apiRequest('/topics');
    const topic = topics.find(t => t.id === id);
    if (!topic) return;

    showModal('Редагувати тему', `
        <div class="form-group">
            <label>Предмет</label>
            <select id="topicSubject" class="form-control">
                ${subjects.map(s => `<option value="${s.id}" ${s.id == topic.subject_id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Назва теми</label>
            <input type="text" id="topicName" class="form-control" value="${escapeHtml(topic.name)}">
        </div>
    `, async () => {
        const name = document.getElementById('topicName').value;
        const subjectId = document.getElementById('topicSubject').value;
        await apiRequest(`/topics/${id}`, 'PUT', { name, subject_id: subjectId });
        closeModal();
        loadAdminTopics();
        showToast('Тему оновлено!', 'success');
    });
}

async function deleteTopic(id) {
    if (!confirm('Видалити цю тему та всі пов\'язані дані?')) return;
    await apiRequest(`/topics/${id}`, 'DELETE');
    loadAdminTopics();
    showToast('Тему видалено!', 'success');
}

// ===================== CRUD КАТЕГОРІЙ =====================

async function loadAdminCategories() {
    const content = document.getElementById('adminContent');
    const [categories, topics] = await Promise.all([
        apiRequest('/categories'),
        apiRequest('/topics')
    ]);
    
    content.innerHTML = `
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">🏷️ Управління категоріями</h3>
                <button class="btn btn-success btn-sm" onclick="showAddCategoryModal()">➕ Додати</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Назва</th><th>Тема</th><th>Колір</th><th>Дії</th></tr></thead>
                    <tbody>
                        ${categories.map(c => `
                            <tr>
                                <td>${c.id}</td>
                                <td><span class="color-dot" style="background-color: ${c.color};"></span>${escapeHtml(c.name)}</td>
                                <td>${escapeHtml(c.topic_name)}</td>
                                <td><span class="color-dot" style="background-color: ${c.color};"></span>${c.color}</td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editCategory(${c.id})">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteCategory(${c.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    window._topics = topics;
}

async function showAddCategoryModal() {
    const topics = window._topics || await apiRequest('/topics');
    showModal('Додати категорію', `
        <div class="form-group">
            <label>Тема</label>
            <select id="categoryTopic" class="form-control">
                ${topics.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Назва категорії</label>
            <input type="text" id="categoryName" class="form-control" placeholder="Напр.: Загальні знання">
        </div>
        <div class="form-group">
            <label>Колір</label>
            <input type="color" id="categoryColor" class="form-control" value="#3498db" style="height: 40px; padding: 4px;">
        </div>
    `, async () => {
        const name = document.getElementById('categoryName').value;
        const topicId = document.getElementById('categoryTopic').value;
        const color = document.getElementById('categoryColor').value;
        await apiRequest('/categories', 'POST', { name, topic_id: topicId, color });
        closeModal();
        loadAdminCategories();
        showToast('Категорію додано!', 'success');
    });
}

async function editCategory(id) {
    const topics = window._topics || await apiRequest('/topics');
    const categories = await apiRequest('/categories');
    const cat = categories.find(c => c.id === id);
    if (!cat) return;

    showModal('Редагувати категорію', `
        <div class="form-group">
            <label>Назва категорії</label>
            <input type="text" id="categoryName" class="form-control" value="${escapeHtml(cat.name)}">
        </div>
        <div class="form-group">
            <label>Колір</label>
            <input type="color" id="categoryColor" class="form-control" value="${cat.color}" style="height: 40px; padding: 4px;">
        </div>
    `, async () => {
        const name = document.getElementById('categoryName').value;
        const color = document.getElementById('categoryColor').value;
        await apiRequest(`/categories/${id}`, 'PUT', { name, color });
        closeModal();
        loadAdminCategories();
        showToast('Категорію оновлено!', 'success');
    });
}

async function deleteCategory(id) {
    if (!confirm('Видалити цю категорію та всі запитання?')) return;
    await apiRequest(`/categories/${id}`, 'DELETE');
    loadAdminCategories();
    showToast('Категорію видалено!', 'success');
}

// ===================== CRUD ЗАПИТАНЬ =====================

async function loadAdminQuestions() {
    const content = document.getElementById('adminContent');
    const [questions, categories] = await Promise.all([
        apiRequest('/questions'),
        apiRequest('/categories')
    ]);
    
    content.innerHTML = `
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">❓ Управління запитаннями</h3>
                <button class="btn btn-success btn-sm" onclick="showAddQuestionModal()">➕ Додати</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Запитання</th><th>Категорія</th><th>Балів</th><th>Відповідей</th><th>Дії</th></tr></thead>
                    <tbody>
                        ${questions.map(q => `
                            <tr>
                                <td>${q.id}</td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(q.question_text)}</td>
                                <td>${escapeHtml(q.category_name)}</td>
                                <td>${q.points}</td>
                                <td>${(q.answers || []).length}</td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editQuestion(${q.id})">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteQuestion(${q.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    window._categories = categories;
}

async function showAddQuestionModal() {
    const categories = window._categories || await apiRequest('/categories');
    showModal('Додати запитання', getQuestionForm(categories), async () => {
        await saveQuestion();
    });
}

function getQuestionForm(categories, question = null) {
    const answers = question?.answers || [
        { answer_text: '', is_correct: 1 },
        { answer_text: '', is_correct: 0 },
        { answer_text: '', is_correct: 0 },
        { answer_text: '', is_correct: 0 },
    ];

    return `
        <div class="form-group">
            <label>Категорія</label>
            <select id="questionCategory" class="form-control">
                ${categories.map(c => `<option value="${c.id}" ${question && question.category_id == c.id ? 'selected' : ''}>${escapeHtml(c.name)} (${escapeHtml(c.topic_name)})</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Текст запитання</label>
            <textarea id="questionText" class="form-control" placeholder="Введіть текст запитання">${question ? escapeHtml(question.question_text) : ''}</textarea>
        </div>
        <div class="form-group">
            <label>Бали за правильну відповідь</label>
            <input type="number" id="questionPoints" class="form-control" value="${question?.points || 1}" min="1">
        </div>
        <h4 style="margin: 16px 0 8px;">Варіанти відповідей:</h4>
        ${answers.map((a, i) => `
            <div class="form-group" style="display: flex; gap: 8px; align-items: center;">
                <input type="radio" name="correctAnswer" value="${i}" ${a.is_correct ? 'checked' : ''} style="flex-shrink: 0;">
                <input type="text" class="form-control answer-input" value="${escapeHtml(a.answer_text)}" placeholder="Варіант ${i + 1}">
            </div>
        `).join('')}
        <p style="font-size: 0.8rem; color: var(--text-secondary);">⭐ Позначте правильну відповідь</p>
    `;
}

async function saveQuestion(editId = null) {
    const categoryId = document.getElementById('questionCategory').value;
    const questionText = document.getElementById('questionText').value;
    const points = document.getElementById('questionPoints').value;
    const correctIndex = document.querySelector('input[name="correctAnswer"]:checked')?.value;
    const answerInputs = document.querySelectorAll('.answer-input');

    const answers = Array.from(answerInputs).map((input, i) => ({
        answer_text: input.value,
        is_correct: i == correctIndex ? 1 : 0,
    })).filter(a => a.answer_text.trim());

    if (!questionText || answers.length < 2) {
        showToast('Заповніть запитання та мінімум 2 відповіді', 'error');
        return;
    }

    const data = {
        category_id: categoryId,
        question_text: questionText,
        points: parseInt(points),
        answers,
    };

    if (editId) {
        await apiRequest(`/questions/${editId}`, 'PUT', data);
    } else {
        await apiRequest('/questions', 'POST', data);
    }

    closeModal();
    loadAdminQuestions();
    showToast(editId ? 'Запитання оновлено!' : 'Запитання додано!', 'success');
}

async function editQuestion(id) {
    const [question, categories] = await Promise.all([
        apiRequest(`/questions/${id}`),
        apiRequest('/categories')
    ]);

    showModal('Редагувати запитання', getQuestionForm(categories, question), async () => {
        await saveQuestion(id);
    });
}

async function deleteQuestion(id) {
    if (!confirm('Видалити це запитання?')) return;
    await apiRequest(`/questions/${id}`, 'DELETE');
    loadAdminQuestions();
    showToast('Запитання видалено!', 'success');
}

// ===================== ІМПОРТ =====================

function showImportForm() {
    const content = document.getElementById('adminContent');
    content.innerHTML = `
        <div class="card">
            <h3 class="card-title">📥 Імпорт запитань з JSON</h3>
            <p style="margin-bottom: 16px; color: var(--text-secondary);">
                Вставте JSON з запитаннями у форматі нижче. Запитання будуть додані до відповідних категорій.
            </p>
            <div class="form-group">
                <label>JSON дані</label>
                <textarea id="importJson" class="form-control" rows="12" placeholder='{
  "questions": [
    {
      "category_id": 1,
      "question_text": "Ваше запитання?",
      "points": 1,
      "answers": [
        { "answer_text": "Відповідь 1", "is_correct": 1 },
        { "answer_text": "Відповідь 2", "is_correct": 0 },
        { "answer_text": "Відповідь 3", "is_correct": 0 },
        { "answer_text": "Відповідь 4", "is_correct": 0 }
      ]
    }
  ]
}'></textarea>
            </div>
            <button class="btn btn-success" onclick="importQuestions()">📥 Імпортувати</button>
        </div>

        <div class="card" style="margin-top: 16px;">
            <h3 class="card-title">📋 Завантаження з JSON файлу</h3>
            <div class="form-group">
                <input type="file" id="jsonFile" accept=".json" class="form-control" onchange="loadJsonFile(event)">
            </div>
        </div>
    `;
}

async function importQuestions() {
    const jsonText = document.getElementById('importJson').value;
    try {
        const data = JSON.parse(jsonText);
        const result = await apiRequest('/questions/import', 'POST', data);
        showToast(`Імпортовано ${result.imported} запитань!`, 'success');
    } catch (e) {
        showToast('Помилка: невірний формат JSON', 'error');
    }
}

function loadJsonFile(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('importJson').value = e.target.result;
    };
    reader.readAsText(file);
}

// ===================== ДОПОМІЖНІ ФУНКЦІЇ =====================

/**
 * Екранування HTML для запобігання XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

/**
 * Корекція кольору (темніший/світліший)
 */
function adjustColor(hex, amount) {
    hex = hex.replace('#', '');
    const r = Math.max(0, Math.min(255, parseInt(hex.substring(0, 2), 16) + amount));
    const g = Math.max(0, Math.min(255, parseInt(hex.substring(2, 4), 16) + amount));
    const b = Math.max(0, Math.min(255, parseInt(hex.substring(4, 6), 16) + amount));
    return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
}

/**
 * Показ модального вікна
 */
function showModal(title, content, onSave) {
    // Видалення попереднього модального вікна
    const existing = document.querySelector('.modal-overlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
        <div class="modal fade-in">
            <div class="modal-header">
                <h2>${title}</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">${content}</div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="modalSaveBtn">💾 Зберегти</button>
                <button class="btn" onclick="closeModal()" style="background: var(--text-secondary); color: white;">Скасувати</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Обробка кнопки збереження
    document.getElementById('modalSaveBtn').onclick = onSave;

    // Закриття по кліку на overlay
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
}

/**
 * Закриття модального вікна
 */
function closeModal() {
    const overlay = document.querySelector('.modal-overlay');
    if (overlay) overlay.remove();
}

/**
 * Показ сповіщення
 */
function showToast(message, type = 'info') {
    // Видалення попереднього сповіщення
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    // Анімація появи
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Автоматичне приховування
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
