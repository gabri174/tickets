<?php
class Database {
    private $apiUrl;
    private $apiToken;
    private $lastInsertId = null;
    
    public function __construct() {
        // Limpiamos la URL para evitar barras dobles al final
        $url = defined('D1_API_URL') ? D1_API_URL : '';
        $this->apiUrl = rtrim($url, '/');
        $this->apiToken = defined('D1_API_TOKEN') ? D1_API_TOKEN : '';
    }

    /**
     * Método de diagnóstico público — SOLO para debug_d1.php
     */
    public function testConnection() {
        return [
            'url'   => $this->apiUrl,
            'token' => !empty($this->apiToken) ? '✅ SÍ (' . strlen($this->apiToken) . ' chars)' : '❌ NO',
            'result' => $this->callD1('SELECT 1 as test', [], 'first'),
        ];
    }

    /**
     * Puente público para ejecutar consultas personalizadas
     */
    public function query($sql, $params = [], $method = 'all') {
        $res = $this->callD1($sql, $params, $method);
        if ($res === null) {
            return ($method === 'all') ? [] : null;
        }
        if ($method === 'all') {
            return $res['results'] ?? [];
        }
        return $res;
    }

    public function listTables() {
        $res = $this->callD1("SELECT name FROM sqlite_master WHERE type='table'", [], 'all');
        if (!$res || !isset($res['results'])) return null;
        return $res['results'];
    }

    public function countAdmins() {
        $result = $this->callD1("SELECT COUNT(*) as total FROM admins", [], 'all');
        if (!$result || !isset($result['results'][0])) return null;
        return $result['results'][0];
    }

    /**
     * Realiza una llamada al Proxy de Cloudflare D1
     * NOTA: Los errores nunca revelan el token API
     */
    private function callD1($sql, $params = [], $method = 'all') {
        if (empty($this->apiUrl)) {
            error_log("D1 Error: Configuración inválida (URL vacía)");
            return null;
        }

        $endpoint = $this->apiUrl . '/api/query';
        $ch = curl_init($endpoint);
        $payload = json_encode([
            'sql' => $sql,
            'params' => $params,
            'method' => $method
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        if ($err) {
            // Nunca loggear la URL completa ni el error crudo que podría contener datos sensibles
            error_log("D1 Proxy Error: Error de conexión");
            curl_close($ch);
            return null;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            error_log("D1 Proxy Error: HTTP " . $httpCode . " - " . ($data['message'] ?? 'Sin mensaje'));
            return null;
        }

        if (!$data || !$data['success']) {
            $msg = $data['message'] ?? 'Fallo desconocido';
            error_log("D1 API Error: " . $msg);
            return null;
        }

        // Si es una operación de escritura (run), guardamos el ID insertado
        if ($method === 'run' && isset($data['data']['meta']['last_row_id'])) {
            $this->lastInsertId = $data['data']['meta']['last_row_id'];
        }

        return $data['data'];
    }

    // ─────────────────────────────────────────────
    // EVENTOS
    // ─────────────────────────────────────────────

    public function getActiveEvents($category = null) {
        if ($category && $category !== 'todos') {
            return $this->query("SELECT * FROM events WHERE status = 'active' AND category = ? ORDER BY date_event ASC", [$category]);
        } else {
            return $this->query("SELECT * FROM events WHERE status = 'active' ORDER BY date_event ASC");
        }
    }

    public function getActiveEventsByOrganizer($adminId) {
        return $this->query("SELECT * FROM events WHERE status = 'active' AND admin_id = ? ORDER BY date_event ASC", [$adminId]);
    }

    public function getEventById($id, $adminId = null) {
        if ($adminId) {
            $res = $this->callD1("SELECT * FROM events WHERE id = ? AND status = 'active' AND admin_id = ?", [$id, $adminId], 'first');
        } else {
            $res = $this->callD1("SELECT * FROM events WHERE id = ?", [$id], 'first');
        }
        return $res;
    }

    public function createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = 1, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null) {
        $sql = "INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url, admin_id, category, seo_title, seo_description, seo_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $res = $this->callD1($sql, [$title, $description, $dateEvent, $location, $price, $maxTickets, $maxTickets, $imageUrl, $adminId, $category, $seoTitle, $seoDescription, $seoKeywords], 'run');
        return $res !== null;
    }

    public function getLastInsertId() {
        return $this->lastInsertId;
    }

    public function updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = null, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null) {
        if ($adminId) {
            $sql = "UPDATE events SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=? WHERE id=? AND admin_id=?";
            $params = [$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $category, $seoTitle, $seoDescription, $seoKeywords, $id, $adminId];
        } else {
            $sql = "UPDATE events SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=? WHERE id=?";
            $params = [$title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $category, $seoTitle, $seoDescription, $seoKeywords, $id];
        }
        return $this->callD1($sql, $params, 'run') !== null;
    }

    public function deleteEvent($id, $adminId = null) {
        if ($adminId) {
            $sql = "UPDATE events SET status = 'inactive' WHERE id = ? AND admin_id = ?";
            $params = [$id, $adminId];
        } else {
            $sql = "UPDATE events SET status = 'inactive' WHERE id = ?";
            $params = [$id];
        }
        return $this->callD1($sql, $params, 'run') !== null;
    }

    public function getAllEvents($adminId = null) {
        if ($adminId) {
            return $this->query("SELECT * FROM events WHERE admin_id = ? ORDER BY created_at DESC", [$adminId]);
        } else {
            return $this->query("SELECT * FROM events ORDER BY created_at DESC");
        }
    }

    public function trackVisit($eventId, $sessionId, $ipHash) {
        $sql = "INSERT INTO event_visits (event_id, session_id, ip_hash) VALUES (?, ?, ?)";
        return $this->callD1($sql, [$eventId, $sessionId, $ipHash], 'run') !== null;
    }

    // ─────────────────────────────────────────────
    // TIPOS DE ENTRADA (ticket_types)
    // ─────────────────────────────────────────────

    public function getTicketTypesByEvent($eventId) {
        return $this->query("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY sort_order ASC, id ASC", [$eventId]);
    }

    public function getTicketTypeById($id) {
        return $this->callD1("SELECT * FROM ticket_types WHERE id = ?", [$id], 'first');
    }

    public function createTicketType($eventId, $name, $description, $price, $maxTickets, $sortOrder = 0) {
        $sql = "INSERT INTO ticket_types (event_id, name, description, price, max_tickets, available_tickets, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
        return $this->callD1($sql, [$eventId, $name, $description, $price, $maxTickets, $maxTickets, $sortOrder], 'run') !== null;
    }

    public function updateTicketType($id, $name, $description, $price, $maxTickets, $sortOrder = 0) {
        $sql = "UPDATE ticket_types SET name=?, description=?, price=?, max_tickets=?, sort_order=? WHERE id=?";
        return $this->callD1($sql, [$name, $description, $price, $maxTickets, $sortOrder, $id], 'run') !== null;
    }

    public function deleteTicketTypesByEvent($eventId) {
        return $this->callD1("DELETE FROM ticket_types WHERE event_id = ?", [$eventId], 'run') !== null;
    }

    public function updateAvailableTicketType($typeId, $quantity = 1) {
        $sql = "UPDATE ticket_types SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?";
        return $this->callD1($sql, [$quantity, $typeId, $quantity], 'run') !== null;
    }

    // ─────────────────────────────────────────────
    // TICKETS
    // ─────────────────────────────────────────────

    public function createTicket($eventId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath, $ticketTypeId = null, $referral = null, $zipCode = null) {
        $sql = "INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path, referral, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $res = $this->callD1($sql, [$eventId, $ticketTypeId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath, $referral, $zipCode], 'run');
        return $res !== null;
    }

    public function updateAvailableTickets($eventId, $quantity = 1) {
        $sql = "UPDATE events SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?";
        return $this->callD1($sql, [$quantity, $eventId, $quantity], 'run') !== null;
    }

    public function getTicketsByEvent($eventId) {
        $sql = "SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id WHERE t.event_id = ? ORDER BY t.purchase_date DESC";
        return $this->query($sql, [$eventId]);
    }

    public function getTicketByCode($code) {
        $sql = "SELECT t.*, e.title as event_title, e.date_event, e.location, e.image_url, tt.name as ticket_type_name FROM tickets t JOIN events e ON t.event_id = e.id LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id WHERE t.ticket_code = ?";
        return $this->callD1($sql, [$code], 'first');
    }

    public function getRecentTicketsByEmail($email, $eventId, $minutes = 30) {
        // Aumentamos el margen a 30 minutos para evitar problemas de sincronización de reloj
        $sql = "SELECT t.*, tt.name as type_name FROM tickets t 
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id 
                WHERE t.attendee_email = ? AND t.event_id = ? 
                AND t.purchase_date > datetime('now', '-' || ? || ' minutes') 
                ORDER BY t.id DESC";
        return $this->query($sql, [$email, $eventId, $minutes]);
    }

    public function getRecentTicketsByPhone($phone, $eventId, $minutes = 30) {
        $sql = "SELECT t.*, tt.name as type_name FROM tickets t 
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id 
                WHERE t.attendee_phone = ? AND t.event_id = ? 
                AND t.purchase_date > datetime('now', '-' || ? || ' minutes') 
                ORDER BY t.id DESC";
        return $this->query($sql, [$phone, $eventId, $minutes]);
    }

    // ─────────────────────────────────────────────
    // ADMINISTRADORES
    // ─────────────────────────────────────────────

    public function validateAdmin($login, $password) {
        $admin = $this->callD1("SELECT * FROM admins WHERE username = ? OR email = ?", [$login, $login], 'first');
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }

    public function getLoginAttempts($login) {
        return $this->callD1("SELECT login_attempts, last_login_attempt FROM admins WHERE username = ? OR email = ?", [$login, $login], 'first');
    }

    public function incrementLoginAttempts($login) {
        // SQlite use datetime('now')
        $sql = "UPDATE admins SET login_attempts = login_attempts + 1, last_login_attempt = datetime('now') WHERE username = ? OR email = ?";
        return $this->callD1($sql, [$login, $login], 'run') !== null;
    }

    public function resetLoginAttempts($login) {
        $sql = "UPDATE admins SET login_attempts = 0, last_login_attempt = NULL WHERE username = ? OR email = ?";
        return $this->callD1($sql, [$login, $login], 'run') !== null;
    }

    public function registerAdmin($username, $password, $email, $role = 'organizer') {
        $existing = $this->callD1("SELECT id FROM admins WHERE username = ? OR email = ?", [$username, $email], 'first');
        if ($existing) {
            return "exists";
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)";
        $res = $this->callD1($sql, [$username, $hashedPassword, $email, $role], 'run');
        return $res ? $this->lastInsertId : false;
    }

    // ─────────────────────────────────────────────
    // ESTADÍSTICAS
    // ─────────────────────────────────────────────

    public function getAllTickets($adminId = null) {
        if ($adminId) {
            $sql = "SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id WHERE e.admin_id = ? ORDER BY t.purchase_date DESC";
            return $this->query($sql, [$adminId]);
        } else {
            $sql = "SELECT t.*, e.title as event_title FROM tickets t JOIN events e ON t.event_id = e.id ORDER BY t.purchase_date DESC";
            return $this->query($sql);
        }
    }

    public function countTickets($adminId = null) {
        if ($adminId) {
            $sql = "SELECT COUNT(t.id) as total FROM tickets t JOIN events e ON t.event_id = e.id WHERE e.admin_id = ?";
            $res = $this->query($sql, [$adminId], 'first');
        } else {
            $sql = "SELECT COUNT(*) as total FROM tickets";
            $res = $this->query($sql, [], 'first');
        }
        return $res['total'] ?? 0;
    }

    public function countEvents($adminId = null) {
        if ($adminId) {
            $sql = "SELECT COUNT(*) as total FROM events WHERE status = 'active' AND admin_id = ?";
            $res = $this->query($sql, [$adminId], 'first');
        } else {
            $sql = "SELECT COUNT(*) as total FROM events WHERE status = 'active'";
            $res = $this->query($sql, [], 'first');
        }
        return $res['total'] ?? 0;
    }

    public function updateTicketStatus($id, $status, $adminId = null) {
        if ($adminId) {
            // SQLite JOIN syntax in UPDATE is different or not supported directly like MySQL
            // We use a subquery for admin_id validation
            $sql = "UPDATE tickets SET status = ? WHERE id = ? AND event_id IN (SELECT id FROM events WHERE admin_id = ?)";
            return $this->callD1($sql, [$status, $id, $adminId], 'run') !== null;
        } else {
            $sql = "UPDATE tickets SET status = ? WHERE id = ?";
            return $this->callD1($sql, [$status, $id], 'run') !== null;
        }
    }

    public function getTicketById($id) {
        $sql = "SELECT t.*, e.title as event_title, tt.name as type_name 
                FROM tickets t 
                JOIN events e ON t.event_id = e.id 
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id 
                WHERE t.id = ?";
        return $this->callD1($sql, [$id], 'first');
    }

    public function updateTicketData($id, $name, $email, $phone, $adminId = null) {
        if ($adminId) {
            $sql = "UPDATE tickets SET attendee_name = ?, attendee_email = ?, attendee_phone = ? 
                    WHERE id = ? AND event_id IN (SELECT id FROM events WHERE admin_id = ?)";
            return $this->callD1($sql, [$name, $email, $phone, $id, $adminId], 'run') !== null;
        } else {
            $sql = "UPDATE tickets SET attendee_name = ?, attendee_email = ?, attendee_phone = ? WHERE id = ?";
            return $this->callD1($sql, [$name, $email, $phone, $id], 'run') !== null;
        }
    }

    public function getAdminById($id) {
        return $this->callD1("SELECT * FROM admins WHERE id = ?", [$id], 'first');
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
        return $this->callD1($sql, $params, 'run') !== null;
    }

    public function createPasswordReset($email, $token) {
        $this->callD1("DELETE FROM password_resets WHERE email = ?", [$email], 'run');
        $sql = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
        return $this->callD1($sql, [$email, $token], 'run') !== null;
    }

    public function getPasswordReset($token) {
        // SQLite adaptation for DATE_SUB
        $sql = "SELECT * FROM password_resets WHERE token = ? AND created_at > datetime('now', '-1 hour')";
        return $this->callD1($sql, [$token], 'first');
    }

    public function deletePasswordReset($email) {
        return $this->callD1("DELETE FROM password_resets WHERE email = ?", [$email], 'run') !== null;
    }

    public function updateAdminPasswordByEmail($email, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE admins SET password = ? WHERE email = ?";
        return $this->callD1($sql, [$hashed, $email], 'run') !== null;
    }

    public function setAdminVerificationCode($adminId, $code) {
        $sql = "UPDATE admins SET verification_code = ?, is_verified = 0 WHERE id = ?";
        return $this->callD1($sql, [$code, $adminId], 'run') !== null;
    }

    public function verifyAdmin($adminId, $code) {
        $sql = "UPDATE admins SET is_verified = 1, verification_code = NULL WHERE id = ? AND verification_code = ?";
        $res = $this->callD1($sql, [$adminId, $code], 'run');
        // En D1 'run' devuelve meta con changes
        return ($res['meta']['changes'] ?? 0) > 0;
    }

    public function getAdminByEmail($email) {
        return $this->callD1("SELECT * FROM admins WHERE email = ?", [$email], 'first');
    }

    public function getPdo() {
        return $this; // Devolvemos el mismo objeto para compatibilidad de interfaz
    }

    /**
     * Compatibilidad con prepare() - Retorna el mismo objeto
     */
    private $pendingSql;
    public function prepare($sql) {
        $this->pendingSql = $sql;
        return $this;
    }

    /**
     * Compatibilidad con execute()
     */
    private $lastResult;
    public function execute($params = []) {
        // Al ejecutar, realizamos la llamada real a D1
        $method = stripos($this->pendingSql, 'SELECT') === 0 ? 'all' : 'run';
        $result = $this->callD1($this->pendingSql, $params, $method);

        // Normalizar estructura: callD1 con 'all' devuelve ['results' => [...], 'meta' => [...]]
        // Pero 'first' o 'run' pueden devolver datos directos
        if ($method === 'all' && $result !== null) {
            // Asegurar que tenga estructura results
            if (!isset($result['results'])) {
                $this->lastResult = ['results' => [$result], 'meta' => []];
            } else {
                $this->lastResult = $result;
            }
        } else {
            $this->lastResult = $result;
        }

        return $this->lastResult !== null;
    }

    /**
     * Compatibilidad con fetchAll()
     */
    public function fetchAll() {
        return $this->lastResult['results'] ?? [];
    }

    /**
     * Compatibilidad con fetch()
     */
    public function fetch() {
        // En D1 'all' devuelve results como lista. fetch() en PDO devuelve el primer elemento
        if (isset($this->lastResult['results'][0])) {
            return $this->lastResult['results'][0];
        }
        // Si usamos method 'first' en callD1 directamente
        return $this->lastResult; 
    }

    /**
     * Compatibilidad con fetchColumn()
     */
    public function fetchColumn($column = 0) {
        $row = $this->fetch();
        if ($row && is_array($row)) {
            $values = array_values($row);
            return $values[$column] ?? null;
        }
    }
}
