<?php

class Database
{
    private $apiUrl;
    private $apiToken;
    public $lastError = null;
    private $lastInsertId = null;

    public function __construct()
    {
        $url = defined('D1_API_URL') ? D1_API_URL : '';
        $this->apiUrl = rtrim((string) $url, '/');
        $this->apiToken = defined('D1_API_TOKEN') ? (string) D1_API_TOKEN : '';
    }

    // ============================================================
    // HELPERS
    // ============================================================
    private function normalizeId($id)
    {
        $id = (int) $id;
        return $id > 0 ? $id : null;
    }

    private function normalizePositiveInt($value, $default = 1)
    {
        $value = (int) $value;
        return $value > 0 ? $value : (int) $default;
    }

    private function normalizeEmail($email)
    {
        return mb_strtolower(trim((string) $email));
    }

    private function normalizePhone($phone)
    {
        return preg_replace('/[^0-9+]/', '', (string) $phone);
    }

    private function normalizeString($value, $maxLength = null)
    {
        $value = trim((string) $value);
        if ($maxLength !== null) {
            $value = mb_substr($value, 0, (int) $maxLength);
        }
        return $value;
    }

    private function callD1($sql, $params = [], $method = 'all')
    {
        $this->lastError = null;

        if (empty($this->apiUrl) || empty($this->apiToken)) {
            $this->lastError = 'Configuración D1 incompleta';
            error_log('D1 Error: Configuración inválida');
            return null;
        }

        $allowedMethods = ['all', 'first', 'run'];
        if (!in_array($method, $allowedMethods, true)) {
            $this->lastError = 'Método D1 inválido';
            error_log('D1 Error: Método inválido');
            return null;
        }

        $endpoint = $this->apiUrl . '/api/query';
        $payload = json_encode([
            'sql'    => (string) $sql,
            'params' => is_array($params) ? array_values($params) : [],
            'method' => $method,
        ]);

        if ($payload === false) {
            $this->lastError = 'No se pudo serializar la petición';
            return null;
        }

        $ch = curl_init($endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken,
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = 'Error de conexión con D1';
            if (function_exists('qLog')) {
                qLog('[DATABASE ERROR] Error de conexión con D1');
            }
            error_log('D1 Proxy Error: ' . $curlError);
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = is_array($data) ? ($data['message'] ?? 'Sin mensaje') : 'Respuesta inválida';
            $this->lastError = $errorMsg;

            if (function_exists('qLog')) {
                qLog('[DATABASE ERROR] HTTP ' . $httpCode . ': ' . $errorMsg . ' | SQL: ' . mb_substr((string) $sql, 0, 120));
            }

            error_log('D1 Proxy Error: HTTP ' . $httpCode . ' - ' . $errorMsg);
            return null;
        }

        if (!is_array($data) || !isset($data['success']) || !$data['success']) {
            $msg = is_array($data) ? ($data['message'] ?? ($data['error'] ?? 'Fallo desconocido')) : 'Respuesta JSON inválida';
            $this->lastError = $msg;

            if (function_exists('qLog')) {
                qLog('[DATABASE ERROR] SQL: ' . mb_substr((string) $sql, 0, 120) . ' | Error: ' . $msg);
            }

            error_log('D1 API Error: ' . $msg . ' | SQL: ' . mb_substr((string) $sql, 0, 50));
            return null;
        }

        if ($method === 'run' && isset($data['data']['meta']['last_row_id'])) {
            $this->lastInsertId = $data['data']['meta']['last_row_id'];
        }

        return $data['data'] ?? null;
    }

    private function query($sql, $params = [], $method = 'all')
    {
        $res = $this->callD1($sql, $params, $method);

        if ($res === null) {
            return ($method === 'all') ? [] : null;
        }

        if ($method === 'all') {
            return $res['results'] ?? [];
        }

        if ($method === 'first') {
            return $res['results'][0] ?? $res;
        }

        return $res;
    }

    private function run($sql, $params = [])
    {
        $res = $this->callD1($sql, $params, 'run');
        return $res !== null;
    }

    private function runWithChanges($sql, $params = [])
    {
        $res = $this->callD1($sql, $params, 'run');
        if ($res === null) {
            return 0;
        }
        return (int) ($res['meta']['changes'] ?? 0);
    }

    private function changedRows($runResult)
    {
        return (int) ($runResult['meta']['changes'] ?? 0);
    }

    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    public function lastInsertId()
    {
        return $this->lastInsertId;
    }

    // ============================================================
    // EVENTOS
    // ============================================================
    public function getActiveEvents($category = null)
    {
        if ($category && $category !== 'todos') {
            return $this->query(
                "SELECT * FROM events WHERE status = 'active' AND category = ? ORDER BY date_event ASC",
                [$this->normalizeString($category, 100)]
            );
        }

        return $this->query("SELECT * FROM events WHERE status = 'active' ORDER BY date_event ASC");
    }

    public function getActiveEventsByOrganizer($adminId)
    {
        $adminId = $this->normalizeId($adminId);
        if ($adminId === null) {
            return [];
        }

        return $this->query(
            "SELECT * FROM events WHERE status = 'active' AND admin_id = ? ORDER BY date_event ASC",
            [$adminId]
        );
    }

    public function getEventById($id, $adminId = null)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return null;
        }

        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return null;
            }

            return $this->query(
                "SELECT * FROM events WHERE id = ? AND admin_id = ?",
                [$id, $adminId],
                'first'
            );
        }

        return $this->query("SELECT * FROM events WHERE id = ?", [$id], 'first');
    }

    public function createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = 1, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null)
    {
        $adminId = $this->normalizeId($adminId) ?? 1;
        $maxTickets = $this->normalizePositiveInt($maxTickets, 1);
        $price = (float) $price;

        $sql = "INSERT INTO events (title, description, date_event, location, price, max_tickets, available_tickets, image_url, admin_id, category, seo_title, seo_description, seo_keywords)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $res = $this->callD1($sql, [
            $this->normalizeString($title, 255),
            $this->normalizeString($description),
            $this->normalizeString($dateEvent, 50),
            $this->normalizeString($location, 255),
            $price,
            $maxTickets,
            $maxTickets,
            $imageUrl ? $this->normalizeString($imageUrl, 1000) : null,
            $adminId,
            $this->normalizeString($category, 100),
            $seoTitle ? $this->normalizeString($seoTitle, 255) : null,
            $seoDescription ? $this->normalizeString($seoDescription, 500) : null,
            $seoKeywords ? $this->normalizeString($seoKeywords, 500) : null,
        ], 'run');

        return $res !== null;
    }

    public function updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl = null, $adminId = null, $category = 'otros', $seoTitle = null, $seoDescription = null, $seoKeywords = null)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return false;
        }

        $maxTickets = $this->normalizePositiveInt($maxTickets, 1);
        $price = (float) $price;

        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return false;
            }

            $sql = "UPDATE events
                    SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=?
                    WHERE id=? AND admin_id=?";
            $params = [
                $this->normalizeString($title, 255),
                $this->normalizeString($description),
                $this->normalizeString($dateEvent, 50),
                $this->normalizeString($location, 255),
                $price,
                $maxTickets,
                $imageUrl ? $this->normalizeString($imageUrl, 1000) : null,
                $this->normalizeString($category, 100),
                $seoTitle ? $this->normalizeString($seoTitle, 255) : null,
                $seoDescription ? $this->normalizeString($seoDescription, 500) : null,
                $seoKeywords ? $this->normalizeString($seoKeywords, 500) : null,
                $id,
                $adminId,
            ];
        } else {
            $sql = "UPDATE events
                    SET title=?, description=?, date_event=?, location=?, price=?, max_tickets=?, image_url=?, category=?, seo_title=?, seo_description=?, seo_keywords=?
                    WHERE id=?";
            $params = [
                $this->normalizeString($title, 255),
                $this->normalizeString($description),
                $this->normalizeString($dateEvent, 50),
                $this->normalizeString($location, 255),
                $price,
                $maxTickets,
                $imageUrl ? $this->normalizeString($imageUrl, 1000) : null,
                $this->normalizeString($category, 100),
                $seoTitle ? $this->normalizeString($seoTitle, 255) : null,
                $seoDescription ? $this->normalizeString($seoDescription, 500) : null,
                $seoKeywords ? $this->normalizeString($seoKeywords, 500) : null,
                $id,
            ];
        }

        return $this->run($sql, $params);
    }

    public function deleteEvent($id, $adminId = null)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return false;
        }

        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return false;
            }

            return $this->run("UPDATE events SET status = 'inactive' WHERE id = ? AND admin_id = ?", [$id, $adminId]);
        }

        return $this->run("UPDATE events SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public function getAllEvents($adminId = null)
    {
        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return [];
            }

            return $this->query("SELECT * FROM events WHERE admin_id = ? ORDER BY created_at DESC", [$adminId]);
        }

        return $this->query("SELECT * FROM events ORDER BY created_at DESC");
    }

    public function trackVisit($eventId, $sessionId, $ipHash)
    {
        $eventId = $this->normalizeId($eventId);
        if ($eventId === null) {
            return false;
        }

        $sql = "INSERT INTO event_visits (event_id, session_id, ip_hash) VALUES (?, ?, ?)";
        return $this->run($sql, [
            $eventId,
            $this->normalizeString($sessionId, 255),
            $this->normalizeString($ipHash, 255),
        ]);
    }

    // ============================================================
    // TIPOS DE ENTRADA
    // ============================================================
    public function getTicketTypesByEvent($eventId)
    {
        $eventId = $this->normalizeId($eventId);
        if ($eventId === null) {
            return [];
        }

        return $this->query(
            "SELECT * FROM ticket_types WHERE event_id = ? ORDER BY sort_order ASC, id ASC",
            [$eventId]
        );
    }

    public function getTicketTypeById($id)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return null;
        }

        return $this->query("SELECT * FROM ticket_types WHERE id = ?", [$id], 'first');
    }

    public function createTicketType($eventId, $name, $description, $price, $maxTickets, $sortOrder = 0)
    {
        $eventId = $this->normalizeId($eventId);
        if ($eventId === null) {
            return false;
        }

        $maxTickets = $this->normalizePositiveInt($maxTickets, 1);

        $sql = "INSERT INTO ticket_types (event_id, name, description, price, max_tickets, available_tickets, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        return $this->run($sql, [
            $eventId,
            $this->normalizeString($name, 255),
            $this->normalizeString($description),
            (float) $price,
            $maxTickets,
            $maxTickets,
            (int) $sortOrder,
        ]);
    }

    public function updateTicketType($id, $name, $description, $price, $maxTickets, $sortOrder = 0)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return false;
        }

        $current = $this->getTicketTypeById($id);
        if (!$current) {
            return false;
        }

        $newMax = $this->normalizePositiveInt($maxTickets, 1);
        $oldMax = (int) ($current['max_tickets'] ?? 0);
        $oldAvailable = (int) ($current['available_tickets'] ?? 0);
        $sold = max(0, $oldMax - $oldAvailable);
        $newAvailable = max(0, $newMax - $sold);

        $sql = "UPDATE ticket_types
                SET name=?, description=?, price=?, max_tickets=?, available_tickets=?, sort_order=?
                WHERE id=?";

        return $this->run($sql, [
            $this->normalizeString($name, 255),
            $this->normalizeString($description),
            (float) $price,
            $newMax,
            $newAvailable,
            (int) $sortOrder,
            $id,
        ]);
    }

    public function deleteTicketTypesByEvent($eventId)
    {
        $eventId = $this->normalizeId($eventId);
        if ($eventId === null) {
            return false;
        }

        return $this->run("DELETE FROM ticket_types WHERE event_id = ?", [$eventId]);
    }

    public function updateAvailableTicketType($typeId, $quantity = 1)
    {
        $typeId = $this->normalizeId($typeId);
        $quantity = $this->normalizePositiveInt($quantity, 1);

        if ($typeId === null) {
            return false;
        }

        $res = $this->callD1(
            "UPDATE ticket_types
             SET available_tickets = available_tickets - ?
             WHERE id = ? AND available_tickets >= ?",
            [$quantity, $typeId, $quantity],
            'run'
        );

        return $res !== null && $this->changedRows($res) > 0;
    }

    // ============================================================
    // TICKETS
    // ============================================================
    public function createTicket($eventId, $ticketCode, $attendeeName, $attendeeEmail, $attendeePhone, $qrPath, $ticketTypeId = null, $referral = null, $zipCode = null)
    {
        $eventId = $this->normalizeId($eventId);
        $ticketTypeId = $this->normalizeId($ticketTypeId);

        if ($eventId === null) {
            return false;
        }

        $sql = "INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, qr_code_path, referral, zip_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $res = $this->callD1($sql, [
            $eventId,
            $ticketTypeId,
            $this->normalizeString($ticketCode, 64),
            $this->normalizeString($attendeeName, 255),
            $this->normalizeEmail($attendeeEmail),
            $this->normalizePhone($attendeePhone),
            $this->normalizeString($qrPath, 1000),
            $referral !== null ? $this->normalizeString($referral, 255) : null,
            $zipCode !== null ? $this->normalizeString($zipCode, 30) : null,
        ], 'run');

        return $res !== null;
    }

    public function updateAvailableTickets($eventId, $quantity = 1)
    {
        $eventId = $this->normalizeId($eventId);
        $quantity = $this->normalizePositiveInt($quantity, 1);

        if ($eventId === null) {
            return false;
        }

        $res = $this->callD1(
            "UPDATE events
             SET available_tickets = available_tickets - ?
             WHERE id = ? AND available_tickets >= ?",
            [$quantity, $eventId, $quantity],
            'run'
        );

        return $res !== null && $this->changedRows($res) > 0;
    }

    public function getTicketsByEvent($eventId)
    {
        $eventId = $this->normalizeId($eventId);
        if ($eventId === null) {
            return [];
        }

        $sql = "SELECT t.*, e.title as event_title
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                WHERE t.event_id = ?
                ORDER BY t.purchase_date DESC";

        return $this->query($sql, [$eventId]);
    }

    public function getTicketByCode($code)
    {
        $code = $this->normalizeString($code, 64);
        if ($code === '') {
            return null;
        }

        $sql = "SELECT t.*, e.title as event_title, e.date_event, e.location, e.image_url, tt.name as ticket_type_name
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                WHERE t.ticket_code = ?";

        return $this->query($sql, [$code], 'first');
    }

    public function getRecentTicketsByEmail($email, $eventId, $minutes = 60)
    {
        $eventId = $this->normalizeId($eventId);
        $minutes = $this->normalizePositiveInt($minutes, 60);
        $email = $this->normalizeEmail($email);

        if ($eventId === null || $email === '') {
            return [];
        }

        $sql = "SELECT t.*, tt.name as type_name, e.title as event_title
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                WHERE t.attendee_email = ? COLLATE NOCASE
                  AND t.event_id = ?
                  AND t.purchase_date > datetime('now', '-' || ? || ' minutes')
                ORDER BY t.id DESC";

        return $this->query($sql, [$email, $eventId, $minutes]);
    }

    public function getRecentTicketsByPhone($phone, $eventId, $minutes = 60)
    {
        $eventId = $this->normalizeId($eventId);
        $minutes = $this->normalizePositiveInt($minutes, 60);
        $phone = $this->normalizePhone($phone);

        if ($eventId === null || $phone === '') {
            return [];
        }

        $sql = "SELECT t.*, tt.name as type_name, e.title as event_title
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                WHERE t.attendee_phone = ?
                  AND t.event_id = ?
                  AND t.purchase_date > datetime('now', '-' || ? || ' minutes')
                ORDER BY t.id DESC";

        return $this->query($sql, [$phone, $eventId, $minutes]);
    }

    // ============================================================
    // ADMINISTRADORES
    // ============================================================
    public function validateAdmin($login, $password)
    {
        $login = $this->normalizeString($login, 255);
        if ($login === '' || !is_string($password) || $password === '') {
            return false;
        }

        $admin = $this->query(
            "SELECT * FROM admins WHERE username = ? OR email = ?",
            [$login, $this->normalizeEmail($login)],
            'first'
        );

        if (!$admin || empty($admin['password'])) {
            return false;
        }

        if (!password_verify($password, $admin['password'])) {
            return false;
        }

        if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->run("UPDATE admins SET password = ? WHERE id = ?", [$newHash, (int) $admin['id']]);
            $admin['password'] = $newHash;
        }

        return $admin;
    }

    public function getLoginAttempts($login)
    {
        $login = $this->normalizeString($login, 255);
        if ($login === '') {
            return null;
        }

        return $this->query(
            "SELECT login_attempts, last_login_attempt FROM admins WHERE username = ? OR email = ?",
            [$login, $this->normalizeEmail($login)],
            'first'
        );
    }

    public function incrementLoginAttempts($login)
    {
        $login = $this->normalizeString($login, 255);
        if ($login === '') {
            return false;
        }

        $sql = "UPDATE admins
                SET login_attempts = login_attempts + 1,
                    last_login_attempt = datetime('now')
                WHERE username = ? OR email = ?";

        return $this->run($sql, [$login, $this->normalizeEmail($login)]);
    }

    public function resetLoginAttempts($login)
    {
        $login = $this->normalizeString($login, 255);
        if ($login === '') {
            return false;
        }

        $sql = "UPDATE admins
                SET login_attempts = 0,
                    last_login_attempt = NULL
                WHERE username = ? OR email = ?";

        return $this->run($sql, [$login, $this->normalizeEmail($login)]);
    }

    public function registerAdmin($username, $password, $email, $role = 'organizer')
    {
        $username = $this->normalizeString($username, 100);
        $email = $this->normalizeEmail($email);
        $role = in_array($role, ['superadmin', 'admin', 'organizer'], true) ? $role : 'organizer';

        if ($username === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
            return false;
        }

        $existing = $this->query(
            "SELECT id FROM admins WHERE username = ? OR email = ?",
            [$username, $email],
            'first'
        );

        if ($existing) {
            return 'exists';
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)";
        $res = $this->callD1($sql, [$username, $hashedPassword, $email, $role], 'run');

        return $res ? $this->lastInsertId : false;
    }

    public function getAdminById($id)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return null;
        }

        return $this->query("SELECT * FROM admins WHERE id = ?", [$id], 'first');
    }

    public function getAdminByEmail($email)
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        return $this->query("SELECT * FROM admins WHERE email = ?", [$email], 'first');
    }

    public function updateAdminProfile($id, $data)
    {
        $id = $this->normalizeId($id);
        if ($id === null || !is_array($data) || empty($data)) {
            return false;
        }

        $allowedFields = [
            'username',
            'email',
            'full_name',
            'phone',
            'company',
            'avatar',
            'bio',
            'verification_code',
            'verification_code_created_at',
            'is_verified',
            'login_attempts',
            'last_login_attempt',
            'password',
            'role',
        ];

        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedFields, true)) {
                continue;
            }

            switch ($key) {
                case 'email':
                    $value = $this->normalizeEmail($value);
                    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return false;
                    }
                    break;

                case 'username':
                    $value = $this->normalizeString($value, 100);
                    if ($value === '') {
                        return false;
                    }
                    break;

                case 'role':
                    $value = in_array($value, ['superadmin', 'admin', 'organizer'], true) ? $value : 'organizer';
                    break;

                case 'is_verified':
                case 'login_attempts':
                    $value = (int) $value;
                    break;

                case 'last_login_attempt':
                case 'verification_code_created_at':
                    $value = $value ? $this->normalizeString($value, 50) : null;
                    break;

                case 'password':
                    $value = (string) $value;
                    if ($value === '') {
                        return false;
                    }
                    if (password_get_info($value)['algo'] === null) {
                        $value = password_hash($value, PASSWORD_DEFAULT);
                    }
                    break;

                default:
                    $value = $value !== null ? $this->normalizeString($value, 255) : null;
                    break;
            }

            $fields[] = $key . ' = ?';
            $params[] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE admins SET ' . implode(', ', $fields) . ' WHERE id = ?';

        return $this->run($sql, $params);
    }

    public function createPasswordReset($email, $token)
    {
        $email = $this->normalizeEmail($email);
        $token = $this->normalizeString($token, 255);

        if ($email === '' || $token === '') {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $this->callD1("DELETE FROM password_resets WHERE email = ?", [$email], 'run');

        $sql = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, datetime('now'))";
        return $this->run($sql, [$email, $tokenHash]);
    }

    public function getPasswordReset($token)
    {
        $token = $this->normalizeString($token, 255);
        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $sql = "SELECT *
                FROM password_resets
                WHERE token = ?
                  AND created_at > datetime('now', '-1 hour')
                LIMIT 1";

        return $this->query($sql, [$tokenHash], 'first');
    }

    public function deletePasswordReset($email)
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        return $this->run("DELETE FROM password_resets WHERE email = ?", [$email]);
    }

    public function updateAdminPasswordByEmail($email, $password)
    {
        $email = $this->normalizeEmail($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
            return false;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE admins SET password = ? WHERE email = ?";
        return $this->run($sql, [$hashed, $email]);
    }

    public function setAdminVerificationCode($adminId, $code)
    {
        $adminId = $this->normalizeId($adminId);
        $code = $this->normalizeString($code, 64);

        if ($adminId === null || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $sql = "UPDATE admins
                SET verification_code = ?, verification_code_created_at = datetime('now'), is_verified = 0
                WHERE id = ?";

        return $this->run($sql, [$code, $adminId]);
    }

    public function verifyAdmin($adminId, $code)
    {
        $adminId = $this->normalizeId($adminId);
        $code = $this->normalizeString($code, 64);

        if ($adminId === null || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $res = $this->callD1(
            "UPDATE admins
             SET is_verified = 1,
                 verification_code = NULL,
                 verification_code_created_at = NULL
             WHERE id = ?
               AND verification_code = ?
               AND verification_code_created_at > datetime('now', '-15 minutes')
               AND is_verified = 0",
            [$adminId, $code],
            'run'
        );

        return $res !== null && $this->changedRows($res) > 0;
    }

    // ============================================================
    // ESTADÍSTICAS Y TICKETS ADMIN
    // ============================================================
    public function getAllTickets($adminId = null)
    {
        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return [];
            }

            $sql = "SELECT t.*, e.title as event_title
                    FROM tickets t
                    JOIN events e ON t.event_id = e.id
                    WHERE e.admin_id = ?
                    ORDER BY t.purchase_date DESC";

            return $this->query($sql, [$adminId]);
        }

        $sql = "SELECT t.*, e.title as event_title
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                ORDER BY t.purchase_date DESC";

        return $this->query($sql);
    }

    public function countTickets($adminId = null)
    {
        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return 0;
            }

            $sql = "SELECT COUNT(t.id) as total
                    FROM tickets t
                    JOIN events e ON t.event_id = e.id
                    WHERE e.admin_id = ?";

            $res = $this->query($sql, [$adminId], 'first');
        } else {
            $sql = "SELECT COUNT(*) as total FROM tickets";
            $res = $this->query($sql, [], 'first');
        }

        return (int) ($res['total'] ?? 0);
    }

    public function countEvents($adminId = null)
    {
        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return 0;
            }

            $sql = "SELECT COUNT(*) as total FROM events WHERE status = 'active' AND admin_id = ?";
            $res = $this->query($sql, [$adminId], 'first');
        } else {
            $sql = "SELECT COUNT(*) as total FROM events WHERE status = 'active'";
            $res = $this->query($sql, [], 'first');
        }

        return (int) ($res['total'] ?? 0);
    }

    public function updateTicketStatus($id, $status, $adminId = null)
    {
        $id = $this->normalizeId($id);
        $status = $this->normalizeString($status, 50);

        if ($id === null || $status === '') {
            return false;
        }

        $allowedStatuses = ['valid', 'used', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return false;
            }

            $sql = "UPDATE tickets
                    SET status = ?
                    WHERE id = ?
                      AND event_id IN (SELECT id FROM events WHERE admin_id = ?)";

            $res = $this->callD1($sql, [$status, $id, $adminId], 'run');
            return $res !== null && $this->changedRows($res) > 0;
        }

        $res = $this->callD1("UPDATE tickets SET status = ? WHERE id = ?", [$status, $id], 'run');
        return $res !== null && $this->changedRows($res) > 0;
    }

    public function getTicketById($id)
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return null;
        }

        $sql = "SELECT t.*, e.title as event_title, tt.name as type_name
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                WHERE t.id = ?";

        return $this->query($sql, [$id], 'first');
    }

    public function updateTicketData($id, $name, $email, $phone, $adminId = null)
    {
        $id = $this->normalizeId($id);
        $name = $this->normalizeString($name, 255);
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);

        if ($id === null || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($adminId !== null) {
            $adminId = $this->normalizeId($adminId);
            if ($adminId === null) {
                return false;
            }

            $sql = "UPDATE tickets
                    SET attendee_name = ?, attendee_email = ?, attendee_phone = ?
                    WHERE id = ?
                      AND event_id IN (SELECT id FROM events WHERE admin_id = ?)";

            $res = $this->callD1($sql, [$name, $email, $phone, $id, $adminId], 'run');
            return $res !== null && $this->changedRows($res) > 0;
        }

        $res = $this->callD1(
            "UPDATE tickets SET attendee_name = ?, attendee_email = ?, attendee_phone = ? WHERE id = ?",
            [$name, $email, $phone, $id],
            'run'
        );

        return $res !== null && $this->changedRows($res) > 0;
    }
}
