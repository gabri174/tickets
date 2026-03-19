<?php
class Database {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────
    // EVENTOS
    // ─────────────────────────────────────────────

    public function getActiveEvents($category = null) {
        if ($category && $category !== 'todos') {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE status = 'active' AND category = ? ORDER BY date_event ASC");
            $stmt->execute([$category]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE status = 'active' ORDER BY date_event ASC");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function getEventById($id, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active' AND admin_id = ?");
            $stmt->execute([$id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$id]);
        }
        return $stmt->fetch();
    }

    public function createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = 1, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null) {
        $stmt = $this->pdo->prepare("INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url, admin_id, category, seo_title, seo_description, seo_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $maxTickets, $imageUrl, $adminId, $category, $seoTitle, $seoDescription, $seoKeywords]);
    }

    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = null, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE events SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=? WHERE id=? AND admin_id=?");
            return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $category, $seoTitle, $seoDescription, $seoKeywords, $id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE events SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=? WHERE id=?");
            return $stmt->execute([$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $category, $seoTitle, $seoDescription, $seoKeywords, $id]);
        }
    }

    public function deleteEvent($id, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ? AND admin_id = ?");
            return $stmt->execute([$id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ?");
            return $stmt->execute([$id]);
        }
    }

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

    // ─────────────────────────────────────────────
    // TIPOS DE ENTRADA (ticket_types)
    // ─────────────────────────────────────────────

    public function getTicketTypesByEvent($eventId) {
        $stmt = $this->pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public function getTicketTypeById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM ticket_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createTicketType($eventId, $name, $description, $price, $maxTickets, $sortOrder = 0) {
        $stmt = $this->pdo->prepare("INSERT INTO ticket_types (event_id, name, description, price, max_tickets, available_tickets, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$eventId, $name, $description, $price, $maxTickets, $maxTickets, $sortOrder]);
    }

    public function updateTicketType($id, $name, $description, $price, $maxTickets, $sortOrder = 0) {
        $stmt = $this->pdo->prepare("UPDATE ticket_types SET name=?, description=?, price=?, max_tickets=?, sort_order=? WHERE id=?");
        return $stmt->execute([$name, $description, $price, $maxTickets, $sortOrder, $id]);
    }

    public function deleteTicketTypesByEvent($eventId) {
        $stmt = $this->pdo->prepare("DELETE FROM ticket_types WHERE event_id = ?");
        return $stmt->execute([$eventId]);
    }

    public function updateAvailableTicketType($typeId, $quantity = 1) {
        $stmt = $this->pdo->prepare("UPDATE ticket_types SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?");
        return $stmt->execute([$quantity, $typeId, $quantity]);
    }

    // ─────────────────────────────────────────────
    // TICKETS
    // ─────────────────────────────────────────────

    public function createTicket($eventId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath, $ticketTypeId = null) {
        $stmt = $this->pdo->prepare("INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$eventId, $ticketTypeId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath]);
    }

    public function updateAvailableTickets($eventId, $quantity = 1) {
        $stmt = $this->pdo->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?");
        return $stmt->execute([$quantity, $eventId, $quantity]);
    }

    public function getTicketsByEvent($eventId) {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id WHERE t.event_id = ? ORDER BY t.purchase_date DESC");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public function getTicketByCode($code) {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title, e.date_event, e.location, e.image_url, tt.name as ticket_type_name FROM tickets t JOIN events e ON t.event_id = e.id LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id WHERE t.ticket_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    // ─────────────────────────────────────────────
    // ADMINISTRADORES
    // ─────────────────────────────────────────────

    public function validateAdmin($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }

    public function registerAdmin($username, $password, $email, $role = 'organizer') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)");
        try {
            if ($stmt->execute([$username, $hashedPassword, $email, $role])) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────
    // ESTADÍSTICAS
    // ─────────────────────────────────────────────

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

    public function countTickets($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(t.id) as total FROM tickets t JOIN events e ON t.event_id = e.id WHERE e.admin_id = ?");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM tickets");
            $stmt->execute();
        }
        return $stmt->fetch()['total'];
    }

    public function countEvents($adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM events WHERE status = 'active' AND admin_id = ?");
            $stmt->execute([$adminId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM events WHERE status = 'active'");
            $stmt->execute();
        }
        return $stmt->fetch()['total'];
    }

    public function updateTicketStatus($id, $status, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE tickets t JOIN events e ON t.event_id = e.id SET t.status = ? WHERE t.id = ? AND e.admin_id = ?");
            return $stmt->execute([$status, $id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        }
    }

    public function getTicketById($id) {
        $stmt = $this->pdo->prepare("SELECT t.*, e.title as event_title, tt.name as type_name 
                                    FROM tickets t 
                                    JOIN events e ON t.event_id = e.id 
                                    LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id 
                                    WHERE t.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateTicketData($id, $name, $email, $phone, $adminId = null) {
        if ($adminId) {
            $stmt = $this->pdo->prepare("UPDATE tickets t 
                                        JOIN events e ON t.event_id = e.id 
                                        SET t.attendee_name = ?, t.attendee_email = ?, t.attendee_phone = ? 
                                        WHERE t.id = ? AND e.admin_id = ?");
            return $stmt->execute([$name, $email, $phone, $id, $adminId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE tickets SET attendee_name = ?, attendee_email = ?, attendee_phone = ? WHERE id = ?");
            return $stmt->execute([$name, $email, $phone, $id]);
        }
    }

    public function getAdminById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAdminProfile($id, $data) {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        $sql = "UPDATE admins SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function createPasswordReset($email, $token) {
        // Eliminar tokens previos
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        $stmt = $this->pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        return $stmt->execute([$email, $token]);
    }

    public function getPasswordReset($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deletePasswordReset($email) {
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        return $stmt->execute([$email]);
    }

    public function updateAdminPasswordByEmail($email, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
        return $stmt->execute([$hashed, $email]);
    }

    public function setAdminVerificationCode($adminId, $code) {
        $stmt = $this->pdo->prepare("UPDATE admins SET verification_code = ?, is_verified = 0 WHERE id = ?");
        return $stmt->execute([$code, $adminId]);
    }

    public function verifyAdmin($adminId, $code) {
        $stmt = $this->pdo->prepare("UPDATE admins SET is_verified = 1, verification_code = NULL WHERE id = ? AND verification_code = ?");
        $stmt->execute([$adminId, $code]);
        return $stmt->rowCount() > 0;
    }

    public function getAdminByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPdo() {
        return $this->pdo;
    }
}
?>
