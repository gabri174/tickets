-- Base de datos para el sistema de Tickets
CREATE DATABASE IF NOT EXISTS `tickets_system`;
USE `tickets_system`;

-- Tabla de administradores
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'organizer') NOT NULL DEFAULT 'organizer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de eventos con soporte para categorías y SEO
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date_event DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_tickets INT NOT NULL DEFAULT 100,
    available_tickets INT NOT NULL DEFAULT 100,
    image_url VARCHAR(500),
    status ENUM('active', 'inactive', 'cancelled') DEFAULT 'active',
    category VARCHAR(50) DEFAULT 'otros',
    seo_title VARCHAR(255) DEFAULT NULL,
    seo_description TEXT DEFAULT NULL,
    seo_keywords VARCHAR(500) DEFAULT NULL,
    admin_id INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT
);

-- Tabla de tipos de entrada por evento
CREATE TABLE IF NOT EXISTS ticket_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_tickets INT NOT NULL DEFAULT 0,
    available_tickets INT NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Tabla de tickets con soporte para tipos de entrada
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    ticket_type_id INT DEFAULT NULL,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    attendee_name VARCHAR(255) NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    attendee_phone VARCHAR(20),
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('valid', 'used', 'cancelled') DEFAULT 'valid',
    qr_code_path VARCHAR(500),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE SET NULL
);

-- Insertar administrador por defecto (password: admin123)
INSERT INTO admins (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@tickets.com', 'superadmin')
ON DUPLICATE KEY UPDATE username=username;

-- Insertar eventos de ejemplo
INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url, category) VALUES 
('Retiro Espiritual 2024', 'Un fin de semana de meditación y crecimiento personal en las montañas.', '2024-12-15 09:00:00', 'Centro de Retiro Montaña Verde', 150.00, 50, 50, 'assets/images/retiro.jpg', 'otros'),
('Concierto de Jazz', 'Noche mágica con los mejores músicos de jazz de la región.', '2024-12-20 20:00:00', 'Teatro Municipal', 85.00, 200, 200, 'assets/images/concierto.jpg', 'conciertos'),
('Campamento de Verano', 'Aventuras y actividades al aire libre para toda la familia.', '2024-12-25 10:00:00', 'Parque Nacional La Cumbre', 120.00, 30, 30, 'assets/images/campamento.jpg', 'festivales')
ON DUPLICATE KEY UPDATE title=title;
