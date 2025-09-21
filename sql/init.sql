-- Create the urls table
CREATE TABLE IF NOT EXISTS urls (
    id SERIAL PRIMARY KEY,
    original_url VARCHAR(2048) NOT NULL,
    short_code VARCHAR(8) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index on short_code for faster lookups
CREATE INDEX IF NOT EXISTS idx_urls_short_code ON urls(short_code);

-- Create index on created_at for potential future analytics
CREATE INDEX IF NOT EXISTS idx_urls_created_at ON urls(created_at);

-- Insert sample data for testing (optional)
-- INSERT INTO urls (original_url, short_code) VALUES 
-- ('https://www.example.com', 'test1234'),
-- ('https://www.google.com', 'googl123');
