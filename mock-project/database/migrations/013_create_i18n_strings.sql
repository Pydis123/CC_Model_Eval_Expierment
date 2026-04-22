CREATE TABLE i18n_strings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    locale CHAR(2) NOT NULL,
    key_name VARCHAR(200) NOT NULL,
    value TEXT NOT NULL,
    UNIQUE KEY uniq_locale_key (locale, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
