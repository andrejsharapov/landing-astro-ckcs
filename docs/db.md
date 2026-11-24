# Database

Инструкция по развёртыванию таблиц в MySQL / MariaDB (кодировка `utf8mb4`).

## 1. Таблица заявок

Хранит лиды пользователей с формы заказа на лендинге.

```sql
CREATE TABLE IF NOT EXISTS `ckcs_leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'Имя клиента',
  `contact` VARCHAR(150) NOT NULL COMMENT 'Телефон или ник в Telegram',
  `figure` VARCHAR(150) DEFAULT NULL COMMENT 'Выбранная фигурка и цена на момент заказа',
  `comment` TEXT DEFAULT NULL COMMENT 'Пожелания к заказу',
  `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP-адрес для защиты от спама и флуда',
  `created_at` DATETIME NOT NULL COMMENT 'Дата и время создания заявки',
  PRIMARY KEY (`id`),
  KEY `idx_ip_created` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. Таблица каталога фигурок

Позволяет управлять ассортиментом, ценами и порядком вывода карточек без пересборки фронтенда.

```sql
CREATE TABLE IF NOT EXISTS `ckcs_figures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL COMMENT 'Уникальный символьный код (например, ckcs-classic)',
  `name` VARCHAR(120) NOT NULL COMMENT 'Название фигурки',
  `size` VARCHAR(40) NOT NULL COMMENT 'Высота или габариты (например, 10 см)',
  `price` INT UNSIGNED NOT NULL COMMENT 'Цена в рублях (числом для сортировки и расчётов)',
  `price_prefix` VARCHAR(16) DEFAULT '' COMMENT 'Префикс цены (например, "от " для кастомных заказов)',
  `tag` VARCHAR(50) DEFAULT NULL COMMENT 'Бейдж на карточке (Хит продаж, Новинка, Лимитка)',
  `color` VARCHAR(20) DEFAULT '#FFE5D9' COMMENT 'Фоновый цвет карточки в HEX',
  `image_url` VARCHAR(255) DEFAULT NULL COMMENT 'Путь к фото/рендеру (NULL — используется заглушка)',
  `description` TEXT NOT NULL COMMENT 'Краткое описание фигурки',
  `sort_order` INT NOT NULL DEFAULT 100 COMMENT 'Порядок сортировки (меньше — выше)',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 — показывать на сайте, 0 — скрыто',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
ALTER TABLE `ckcs_figures`
  CHANGE COLUMN `is_active` `visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 — показывать на сайте, 0 — скрыть',
  DROP INDEX `idx_active_sort`,
  ADD INDEX `idx_visible_sort` (`visible`, `sort_order`);
```

## 3. Начальное наполнение каталога (Seed)

```sql
INSERT INTO `ckcs_figures` (`slug`, `name`, `size`, `price`, `price_prefix`, `tag`, `color`, `description`, `sort_order`) VALUES
('ckcs-classic', 'ККЧС Классический', '10 см', 1890, '', 'Хит продаж', '#FFE5D9', 'Тот самый уютный ККЧС с пухлыми щёчками. Идеально встаёт на рабочий стол у монитора.', 10),
('ckcs-sleepy', 'ККЧС Сонный в колпаке', '12 см', 2290, '', 'Новинка', '#E5E0FF', 'Милая версия в вязаном колпачке с закрытыми глазками. Помогает не стрессовать на созвонах.', 20),
('ckcs-gamer', 'ККЧС с геймпадом', '11 см', 2490, '', 'Лимитка', '#DCFCE7', 'Держит миниатюрный джойстик. Отличный подарок тиммейту или коллекционеру.', 30),
('ckcs-custom', 'ККЧС по вашему эскизу', '10–20 см', 2990, 'от ', 'Под заказ', '#FEF3C7', 'Добавим аксессуары, покрасим в любимые цвета или сделаем парную фигурку.', 40);
```
