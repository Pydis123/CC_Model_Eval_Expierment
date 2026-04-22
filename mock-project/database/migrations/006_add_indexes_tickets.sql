CREATE INDEX idx_tickets_status ON tickets (status);
CREATE INDEX idx_tickets_assignee ON tickets (assignee_user_id);
CREATE INDEX idx_tickets_requester ON tickets (requester_user_id);
CREATE INDEX idx_tickets_category ON tickets (category_id);
CREATE INDEX idx_tickets_created_at ON tickets (created_at);
