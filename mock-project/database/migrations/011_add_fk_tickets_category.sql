ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
