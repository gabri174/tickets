<?php
class Database {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Obtener todos los eventos activos
    public function getActiveEvents() {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE status = 'active' ORDER BY date_event ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener evento por ID
    public function getEventById($id, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active' AND admin_id = ?");
            $stmt->execute([$id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
            $stmt->execute([$id]);
        }
        return $stmt->fetch();
    }
    
    // Crear ticket
    public function createTicket($eventId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath) {
        $stmt = $this->pdo->prepare("INSERT INTO tickets (event_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$eventId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath]);
    }
    
    // Actualizar tickets disponibles
    public function updateAvailableTickets($eventId, $quantity = 1) {
        $stmt = $this->pdo->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?");
        return $stmt->execute([$quantity, $eventId, $quantity]);
    }
    
    // Obtener tickets por evento
    public function getTicketsByEvent($eventId) {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id WHERE t.event_id = ? ORDER BY t.purchase_date DESC");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }
    
    // Obtener ticket por código
    public function getTicketByCode($code) {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title, e.date_event, e.location FROM tickets t JOIN events e ON t.event_id = e.id WHERE t.ticket_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    // Validar administrador
    public function validateAdmin($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }
    
    // Registrar administrador (organizador)
    public function registerAdmin($username, $password, $email, $role = 'organizer') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)");
        try {
            return $stmt->execute([$username, $hashedPassword, $email, $role]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Crear evento
    public function createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = 1) {
        $stmt = $this->pdo->prepare("INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $maxTickets, $imageUrl, $adminId]);
    }
    
    // Actualizar evento
    public function updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE events SET title = ?, description = ?, date_event = ?, location = ?, price = ?, max_tickets = ?, image_url = ? WHERE id = ? AND admin_id = ?");
            return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE events SET title = ?, description = ?, date_event = ?, location = ?, price = ?, max_tickets = ?, image_url = ? WHERE id = ?");
            return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $id]);
        }
    }
    
    // Eliminar evento (cambiar status)
    public function deleteEvent($id, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ? AND admin_id = ?");
            return $stmt->execute([$id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ?");
            return $stmt->execute([$id]);
        }
    }
    
    // Obtener todos los eventos (para admin)
    public function getAllEvents($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE admin_id = ? ORDER BY created_at DESC");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM events ORDER BY created_at DESC");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    // Obtener todos los tickets (para admin)
    public function getAllTickets($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id WHERE e.admin_id = ? ORDER BY t.purchase_date DESC");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id ORDER BY t.purchase_date DESC");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    // Contar tickets
    public function countTickets($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(t.id) as total FROM tickets t JOIN events e ON t.event_id = e.id WHERE e.admin_id = ?");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM tickets");
            $stmt->execute();
        }
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // Contar eventos
    public function countEvents($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM events WHERE status = 'active' AND admin_id = ?");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM events WHERE status = 'active'");
            $stmt->execute();
        }
        $result = $stmt->fetch();
        return $result['total'];
    }

    // Actualizar estado de ticket
    public function updateTicketStatus($id, $status, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE tickets t JOIN events e ON t.event_id = e.id SET t.status = ? WHERE t.id = ? AND e.admin_id = ?");
            return $stmt->execute([$status, $id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        }
    }

    public function getPdo() {
        return $this->pdo;
    }
}
?>
