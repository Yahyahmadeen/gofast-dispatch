-- GOFAST demo role accounts for local development/testing.
-- Password for all four accounts: Password123!
-- Import this file into the gofast_dispatch database after your schema exists.

INSERT INTO users (full_name, email, phone, password_hash, role, status)
VALUES
('GOFAST Administrator', 'admin@gofast.local', '08000000001', '$2y$12$vfuwtvzcYa/p4C5NJGaqfObIIbiCjCEry7lCqx3OI.2oU9sFfH/k6', 'admin', 'active'),
('GOFAST Dispatcher', 'dispatcher@gofast.local', '08000000002', '$2y$12$vfuwtvzcYa/p4C5NJGaqfObIIbiCjCEry7lCqx3OI.2oU9sFfH/k6', 'dispatcher', 'active'),
('GOFAST Rider', 'rider@gofast.local', '08000000003', '$2y$12$vfuwtvzcYa/p4C5NJGaqfObIIbiCjCEry7lCqx3OI.2oU9sFfH/k6', 'rider', 'active'),
('GOFAST Customer', 'customer@gofast.local', '08000000004', '$2y$12$vfuwtvzcYa/p4C5NJGaqfObIIbiCjCEry7lCqx3OI.2oU9sFfH/k6', 'customer', 'active');
