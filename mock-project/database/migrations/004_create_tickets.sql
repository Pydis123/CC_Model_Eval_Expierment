CREATE TABLE tickets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('new', 'open', 'pending', 'resolved', 'closed') NOT NULL DEFAULT 'new',
    assignee_user_id INT UNSIGNED NULL,
    requester_user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
