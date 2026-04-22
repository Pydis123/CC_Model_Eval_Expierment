ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_assignee
    FOREIGN KEY (assignee_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
