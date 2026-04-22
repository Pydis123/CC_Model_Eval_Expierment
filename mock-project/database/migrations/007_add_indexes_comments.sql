CREATE INDEX idx_comments_ticket ON comments (ticket_id, created_at);
CREATE INDEX idx_comments_author ON comments (author_user_id);
