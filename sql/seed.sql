-- Default admin user (username: admin, password: admin123)
-- IMPORTANT: change this password immediately after the first login.

INSERT INTO users (username, password_hash, name, role, active)
VALUES ('admin', '$2y$12$cd4XwHhRz/eetYyhngUaUeiqdNOQo97qz0Mwqzux418z996o2NdoC', 'Administrator', 'admin', 1);
