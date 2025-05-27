-- Таблица платформ (Stepik, Coursera, и т.д.)
CREATE TABLE platforms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица пользователей
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица курсов
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    platform_id INT NOT NULL,
    external_id VARCHAR(50) NOT NULL, -- ID курса на платформе (например, 3487 для Stepik)
    title VARCHAR(500) NOT NULL,
    description TEXT,
    rating DECIMAL(3,2),
    review_count INT DEFAULT 0,
    price VARCHAR(100),
    url VARCHAR(500) NOT NULL,
    parsed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_per_platform (platform_id, external_id)
);

-- Таблица избранного пользователей
CREATE TABLE user_favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course_favorite (user_id, course_id)
);

-- Таблица плейлистов
CREATE TABLE playlists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица курсов в плейлистах
CREATE TABLE playlist_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    playlist_id INT NOT NULL,
    course_id INT NOT NULL,
    position INT DEFAULT 0, -- для сортировки курсов в плейлисте
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_in_playlist (playlist_id, course_id)
);

-- Индексы для оптимизации
CREATE INDEX idx_courses_platform ON courses(platform_id);
CREATE INDEX idx_courses_rating ON courses(rating);
CREATE INDEX idx_courses_title ON courses(title);
CREATE INDEX idx_user_favorites_user ON user_favorites(user_id);
CREATE INDEX idx_playlists_user ON playlists(user_id);
CREATE INDEX idx_playlists_public ON playlists(is_public);
CREATE INDEX idx_playlist_courses_playlist ON playlist_courses(playlist_id);

-- Вставка начальных данных для платформ
INSERT INTO platforms (name, url) VALUES 
('Stepik', 'https://stepik.org'),
('Coursera', 'https://coursera.org'),
('Udemy', 'https://udemy.com'),
('GeekBrains', 'https://geekbrains.ru'),
('Skillbox', 'https://skillbox.ru');

-- Пример вставки курса из твоего JSON
INSERT INTO courses (platform_id, external_id, title, description, rating, review_count, price, url, parsed_at) 
VALUES (
    1, -- Stepik platform_id
    '3487',
    'Корпоративный фандрайзинг для НКО',
    'Полное название: "Корпоративный фандрайзинг для НКО: как привлечь средства компании на социальные цели".\nКурс о том, как нужно действовать НКО, чтобы получить ресурсную поддержку от бизнес-структур на свои проекты. Ориентирован на практиков некоммерческого сектора',
    4.9,
    85,
    '3000 ₽',
    'https://stepik.org/course/3487/promo',
    '2025-05-20 15:30:22'
);