ALTER TABLE comments
    ADD CONSTRAINT fk_comments_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE comments
    ADD CONSTRAINT fk_comments_author
    FOREIGN KEY (author_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
