ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_requester
    FOREIGN KEY (requester_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
