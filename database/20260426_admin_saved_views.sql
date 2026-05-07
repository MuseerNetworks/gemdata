CREATE TABLE IF NOT EXISTS admin_saved_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    page_key VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    filters_json LONGTEXT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admin_saved_views_owner (admin_id, page_key),
    CONSTRAINT fk_admin_saved_views_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
