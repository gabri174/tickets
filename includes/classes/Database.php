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
    public function getEventById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
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
    
    // Crear evento
    public function createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null) {
        $stmt = $this->pdo->prepare("INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $maxTickets, $imageUrl]);
    }
    
    // Actualizar evento
    public function updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null) {
        $stmt = $this->pdo->prepare("UPDATE events SET title = ?, description = ?, date_event = ?, location = ?, price = ?, max_tickets = ?, image_url = ? WHERE id = ?");
        return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $id]);
    }
    
    // Eliminar evento (cambiar status)
    public function deleteEvent($id) {
        $stmt = $this->pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Obtener todos los eventos (para admin)
    public function getAllEvents() {
        $stmt = $this->pdo->prepare("SELECT * FROM events ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener todos los tickets (para admin)
    public function getAllTickets() {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id ORDER BY t.purchase_date DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Contar tickets
    public function countTickets() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM tickets");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // Contar eventos
    public function countEvents() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM events WHERE status = 'active'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
}
?>
