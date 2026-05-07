-- Optional MySQL hardening for NewAPI M3 gateway data.
-- The application already stores legacy JSON rows in MySQL through app_api_json_store_rows.

ALTER TABLE app_api_json_store_rows
  ADD INDEX idx_store_key_updated_at (store_key, updated_at);

-- Recommended indexes if you later split JSON payloads into typed tables:
-- CREATE INDEX idx_users_created_at ON users(created_at);
-- CREATE INDEX idx_api_keys_user_id ON api_keys(user_id);
-- CREATE INDEX idx_api_keys_key_hash ON api_keys(key_hash);
-- CREATE INDEX idx_wallets_user_id ON wallets(user_id);
-- CREATE INDEX idx_orders_user_created ON orders(user_id, created_at);
