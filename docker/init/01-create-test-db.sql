CREATE DATABASE IF NOT EXISTS `ticket_system_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `ticket_system_test`.* TO 'ticket_app'@'%';
FLUSH PRIVILEGES;
