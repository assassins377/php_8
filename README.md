# Secure Blog - Многопользовательский Блог на PHP

Безопасный, масштабируемый и SEO-оптимизированный блог с системой рейтингов и модерации.

## Требования

- PHP 8.4+
- MySQL 8.0+
- Composer
- Node.js (опционально, для сборки ассетов)

## Установка

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd secure-blog
```

### 2. Установка зависимостей

```bash
composer install
```

### 3. Настройка окружения

```bash
cp .env.example .env
```

Отредактируйте `.env` файл, указав ваши параметры:

- Параметры подключения к базе данных
- SMTP настройки для почты
- OAuth ключи для Google/GitHub
- Секретные ключи

### 4. Создание базы данных

```bash
mysql -u root -p -e "CREATE DATABASE secure_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p secure_blog < database/schema.sql
```

### 5. Настройка прав доступа

```bash
chmod -R 755 storage/
chmod -R 755 public/assets/
```

### 6. Запуск сервера

```bash
php -S localhost:8080 -t public
```

Откройте http://localhost:8080 в браузере.

## Структура проекта

```
/project-root
  /public          # Публичная директория (точка входа)
    /assets        # CSS, JS файлы
    index.php      # Точка входа приложения
  /src             # Исходный код PHP
    /Config        # Конфигурация
    /Controllers   # Контроллеры
    /Middleware    # Middleware компоненты
    /Models        # Модели данных
    /Services      # Бизнес-логика
    /Validators    # Валидаторы
  /templates       # Twig шаблоны
    /layouts       # Основные макеты
    /partials      # Части шаблонов
    /pages         # Страницы
    /errors        # Страницы ошибок
  /storage         # Хранилище файлов
    /uploads       # Загруженные файлы
    /logs          # Логи приложения
    /cache         # Кэш
    /sessions      # Сессии
  /database        # SQL файлы
  .env             # Переменные окружения
  composer.json    # Зависимости PHP
```

## Безопасность

### Реализованные меры безопасности:

1. **HTTP Security Headers**
   - HSTS
   - X-Content-Type-Options
   - X-Frame-Options
   - X-XSS-Protection
   - Content-Security-Policy

2. **Защита сессий**
   - HttpOnly, Secure, SameSite cookies
   - Регенерация session ID после логина
   - Скользящий таймаут

3. **Безопасная загрузка файлов**
   - Проверка MIME-типов через finfo
   - Генерация случайных имен файлов
   - Удаление EXIF данных
   - Хранение вне публичной директории

4. **Защита от атак**
   - SQL Injection: PDO Prepared Statements
   - XSS: htmlspecialchars + HTMLPurifier
   - CSRF: Токены для всех форм
   - Brute Force: Лимит попыток входа
   - Rate Limiting: Ограничение запросов

5. **Аутентификация**
   - BCrypt хеширование (cost 12+)
   - 2FA для администраторов и модераторов
   - OAuth (Google/GitHub)

## Роли пользователей

| Роль | Права |
|------|-------|
| Гость | Чтение, поиск, комментарии (премодерация), оценка |
| Автор | Создание/редактирование своих постов |
| Модератор | Модерация комментариев, бан пользователей |
| Админ | Полный доступ, настройки сайта |

## Технологический стек

- **Backend:** PHP 8.4, Slim 4
- **Database:** MySQL 8.0+, PDO
- **Frontend:** Bootstrap 5.3, Vanilla JS
- **Templates:** Twig
- **Images:** Intervention Image
- **Auth:** Session-based + OAuth + 2FA

## Лицензия

MIT License
