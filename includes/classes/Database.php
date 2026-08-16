<?php

class Database
{
    private $apiUrl;
    private $apiToken;
    public $lastError = null;
    private $lastInsertId = null;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) (defined('D1_API_URL') ? D1_API_URL : ''), '/');
        $this->apiToken = (string) (defined('D1_API_TOKEN') ? D1_API_TOKEN : '');
    }

    private function id($value) { $value = (int) $value; return $value > 0 ? $value : null; }
    private function str($value, $max = null) { $value = trim((string) $value); return $max === null ? $value : mb_substr($value, 0, (int) $max); }
    private function email($value) { return mb_strtolower(trim((string) $value)); }
    private function phone($value) { return preg_replace('/[^0-9+]/', '', (string) $value); }
    private function positiveInt($value, $default = 1) { $value = (int) $value; return $value > 0 ? $value : (int) $default; }

    private function callD1($sql, $params = [], $method = 'all')
    {
        $this->lastError = null;
        if ($this->apiUrl === '' || $this->apiToken === '') {
            $this->lastError = 'Configuración D1 incompleta';
            return null;
        }
        if (!in_array($method, ['all', 'first', 'run'], true)) {
            $this->lastError = 'Método D1 inválido';
            return null;
        }
        $payload = json_encode(['sql' => (string) $sql, 'params' => is_array($params) ? array_values($params) : [], 'method' => $method]);
        if ($payload === false) { $this->lastError = 'No se pudo serializar la petición'; return null; }

        $ch = curl_init($this->apiUrl . '/api/query');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $this->apiToken],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = 'Error de conexión con D1';
            if (function_exists('qLog')) qLog('[DATABASE ERROR] Error de conexión con D1: ' . $curlError);
            return null;
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200 || !is_array($data) || empty($data['success'])) {
            $this->lastError = is_array($data) ? ($data['message'] ?? ($data['error'] ?? 'Fallo D1')) : 'Respuesta JSON inválida';
            if (function_exists('qLog')) qLog('[DATABASE ERROR] HTTP ' . $httpCode . ': ' . $this->lastError);
            return null;
        }
        if ($method === 'run' && isset($data['data']['meta']['last_row_id'])) $this->lastInsertId = $data['data']['meta']['last_row_id'];
        return $data['data'] ?? null;
    }

    private function query($sql, $params = [], $method = 'all')
    {
        $res = $this->callD1($sql, $params, $method);
        if ($res === null) return $method === 'all' ? [] : null;
        if ($method === 'all') return $res['results'] ?? [];
        if ($method === 'first') return $res['results'][0] ?? $res;
        return $res;
    }

    private function run($sql, $params = []) { return $this->callD1($sql, $params, 'run') !== null; }
    private function changes($result) { return (int) ($result['meta']['changes'] ?? 0); }
    public function getLastInsertId() { return $this->lastInsertId; }
    public function lastInsertId() { return $this->lastInsertId; }

    // EVENTOS
    public function getActiveEvents($category = null)
    {
        if ($category && $category !== 'todos') return $this->query("SELECT * FROM events WHERE status='active' AND category=? ORDER BY date_event ASC", [$this->str($category, 100)]);
        return $this->query("SELECT * FROM events WHERE status='active' ORDER BY date_event ASC");
    }
    public function getActiveEventsByOrganizer($adminId)
    {
        $adminId = $this->id($adminId); if ($adminId === null) return [];
        return $this->query("SELECT * FROM events WHERE status='active' AND admin_id=? ORDER BY date_event ASC", [$adminId]);
    }
    public function getEventById($eventId, $adminId = null)
    {
        $eventId = $this->id($eventId); if ($eventId === null) return null;
        if ($adminId !== null) { $adminId = $this->id($adminId); if ($adminId === null) return null; return $this->query("SELECT * FROM events WHERE id=? AND admin_id=?", [$eventId, $adminId], 'first'); }
        return $this->query("SELECT * FROM events WHERE id=?", [$eventId], 'first');
    }
    public function createEvent($title,$description,$dateEvent,$location,$price,$maxTickets,$imageUrl=null,$adminId=1,$category='otros',$seoTitle=null,$seoDescription=null,$seoKeywords=null)
    {
        $maxTickets=$this->positiveInt($maxTickets); $adminId=$this->id($adminId) ?? 1;
        return $this->run("INSERT INTO events (title,description,date_event,location,price,max_tickets,available_tickets,image_url,admin_id,category,seo_title,seo_description,seo_keywords) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [$this->str($title,255),$this->str($description),$this->str($dateEvent,50),$this->str($location,255),(float)$price,$maxTickets,$maxTickets,$imageUrl?$this->str($imageUrl,1000):null,$adminId,$this->str($category,100),$seoTitle?$this->str($seoTitle,255):null,$seoDescription?$this->str($seoDescription,500):null,$seoKeywords?$this->str($seoKeywords,500):null]);
    }
    public function updateEvent($id,$title,$description,$dateEvent,$location,$price,$maxTickets,$imageUrl=null,$adminId=null,$category='otros',$seoTitle=null,$seoDescription=null,$seoKeywords=null)
    {
        $id=$this->id($id); if ($id===null) return false; $maxTickets=$this->positiveInt($maxTickets);
        $sql="UPDATE events SET title=?,description=?,date_event=?,location=?,price=?,max_tickets=?,image_url=?,category=?,seo_title=?,seo_description=?,seo_keywords=? WHERE id=?";
        $params=[$this->str($title,255),$this->str($description),$this->str($dateEvent,50),$this->str($location,255),(float)$price,$maxTickets,$imageUrl?$this->str($imageUrl,1000):null,$this->str($category,100),$seoTitle?$this->str($seoTitle,255):null,$seoDescription?$this->str($seoDescription,500):null,$seoKeywords?$this->str($seoKeywords,500):null,$id];
        if ($adminId!==null) { $adminId=$this->id($adminId); if ($adminId===null) return false; $sql.=' AND admin_id=?'; $params[]=$adminId; }
        return $this->run($sql,$params);
    }
    public function deleteEvent($id,$adminId=null)
    {
        $id=$this->id($id); if($id===null)return false; if($adminId!==null){$adminId=$this->id($adminId);if($adminId===null)return false;return $this->run("UPDATE events SET status='inactive' WHERE id=? AND admin_id=?",[$id,$adminId]);} return $this->run("UPDATE events SET status='inactive' WHERE id=?",[$id]);
    }
    public function getAllEvents($adminId=null)
    {
        if($adminId!==null){$adminId=$this->id($adminId);if($adminId===null)return [];return $this->query("SELECT * FROM events WHERE admin_id=? ORDER BY created_at DESC",[$adminId]);}
        return $this->query("SELECT * FROM events ORDER BY created_at DESC");
    }
    public function trackVisit($eventId,$sessionId,$ipHash){$eventId=$this->id($eventId);if($eventId===null)return false;return $this->run("INSERT INTO event_visits(event_id,session_id,ip_hash) VALUES(?,?,?)",[$eventId,$this->str($sessionId,255),$this->str($ipHash,255)]);}

    // TIPOS DE ENTRADA
    public function getTicketTypesByEvent($eventId){$eventId=$this->id($eventId);if($eventId===null)return [];return $this->query("SELECT * FROM ticket_types WHERE event_id=? ORDER BY sort_order ASC,id ASC",[$eventId]);}
    public function getTicketTypeById($id){$id=$this->id($id);if($id===null)return null;return $this->query("SELECT * FROM ticket_types WHERE id=?",[$id],'first');}
    public function createTicketType($eventId,$name,$description,$price,$maxTickets,$sortOrder=0){$eventId=$this->id($eventId);if($eventId===null)return false;$maxTickets=$this->positiveInt($maxTickets);return $this->run("INSERT INTO ticket_types(event_id,name,description,price,max_tickets,available_tickets,sort_order) VALUES(?,?,?,?,?,?,?)",[$eventId,$this->str($name,255),$this->str($description), (float)$price,$maxTickets,$maxTickets,(int)$sortOrder]);}
    public function updateTicketType($id,$name,$description,$price,$maxTickets,$sortOrder=0)
    {
        $id=$this->id($id);if($id===null)return false;$current=$this->getTicketTypeById($id);if(!$current)return false;$newMax=$this->positiveInt($maxTickets);$oldMax=(int)($current['max_tickets']??0);$oldAvail=(int)($current['available_tickets']??0);$sold=max(0,$oldMax-$oldAvail);$newAvail=max(0,$newMax-$sold);return $this->run("UPDATE ticket_types SET name=?,description=?,price=?,max_tickets=?,available_tickets=?,sort_order=? WHERE id=?",[$this->str($name,255),$this->str($description),(float)$price,$newMax,$newAvail,(int)$sortOrder,$id]);
    }
    public function deleteTicketTypesByEvent($eventId){$eventId=$this->id($eventId);if($eventId===null)return false;return $this->run("DELETE FROM ticket_types WHERE event_id=?",[$eventId]);}
    public function updateAvailableTicketType($typeId,$quantity=1){$typeId=$this->id($typeId);$quantity=$this->positiveInt($quantity);if($typeId===null)return false;$r=$this->callD1("UPDATE ticket_types SET available_tickets=available_tickets-? WHERE id=? AND available_tickets>=?",[$quantity,$typeId,$quantity],'run');return $r!==null&&$this->changes($r)>0;}

    // TICKETS
    public function createTicket($eventId,$ticketCode,$attendeeName,$attendeeEmail,$attendeePhone,$qrPath,$ticketTypeId=null,$referral=null,$zipCode=null)
    {
        $eventId=$this->id($eventId);if($eventId===null)return false;$ticketTypeId=$this->id($ticketTypeId);return $this->run("INSERT INTO tickets(event_id,ticket_type_id,ticket_code,attendee_name,attendee_email,attendee_phone,qr_code_path,referral,zip_code) VALUES(?,?,?,?,?,?,?,?,?)",[$eventId,$ticketTypeId,$this->str($ticketCode,64),$this->str($attendeeName,255),$this->email($attendeeEmail),$this->phone($attendeePhone),$this->str($qrPath,1000),$referral!==null?$this->str($referral,255):null,$zipCode!==null?$this->str($zipCode,30):null]);
    }
    public function updateAvailableTickets($eventId,$quantity=1){$eventId=$this->id($eventId);$quantity=$this->positiveInt($quantity);if($eventId===null)return false;$r=$this->callD1("UPDATE events SET available_tickets=available_tickets-? WHERE id=? AND available_tickets>=?",[$quantity,$eventId,$quantity],'run');return $r!==null&&$this->changes($r)>0;}
    public function getTicketsByEvent($eventId){$eventId=$this->id($eventId);if($eventId===null)return [];return $this->query("SELECT t.*,e.title AS event_title FROM tickets t JOIN events e ON t.event_id=e.id WHERE t.event_id=? ORDER BY t.purchase_date DESC",[$eventId]);}
    public function getTicketByCode($code){$code=$this->str($code,64);if($code==='')return null;return $this->query("SELECT t.*,e.title AS event_title,e.date_event,e.location,e.image_url,tt.name AS ticket_type_name FROM tickets t JOIN events e ON t.event_id=e.id LEFT JOIN ticket_types tt ON t.ticket_type_id=tt.id WHERE t.ticket_code=?",[$code],'first');}
    public function getRecentTicketsByEmail($email,$eventId,$minutes=60){$eventId=$this->id($eventId);$minutes=$this->positiveInt($minutes,60);$email=$this->email($email);if($eventId===null||$email==='')return [];return $this->query("SELECT t.*,tt.name AS type_name,e.title AS event_title FROM tickets t JOIN events e ON t.event_id=e.id LEFT JOIN ticket_types tt ON t.ticket_type_id=tt.id WHERE t.attendee_email=? COLLATE NOCASE AND t.event_id=? AND t.purchase_date>datetime('now','-'||?||' minutes') ORDER BY t.id DESC",[$email,$eventId,$minutes]);}
    public function getRecentTicketsByPhone($phone,$eventId,$minutes=60){$eventId=$this->id($eventId);$minutes=$this->positiveInt($minutes,60);$phone=$this->phone($phone);if($eventId===null||$phone==='')return [];return $this->query("SELECT t.*,tt.name AS type_name,e.title AS event_title FROM tickets t JOIN events e ON t.event_id=e.id LEFT JOIN ticket_types tt ON t.ticket_type_id=tt.id WHERE t.attendee_phone=? AND t.event_id=? AND t.purchase_date>datetime('now','-'||?||' minutes') ORDER BY t.id DESC",[$phone,$eventId,$minutes]);}

    // ADMINISTRADORES
    public function validateAdmin($login,$password)
    {
        $login=$this->str($login,255);if($login===''||!is_string($password)||$password==='')return false;$admin=$this->query("SELECT * FROM admins WHERE username=? OR email=?",[$login,$this->email($login)],'first');if(!$admin||empty($admin['password'])||!password_verify($password,$admin['password']))return false;if(password_needs_rehash($admin['password'],PASSWORD_DEFAULT)){$hash=password_hash($password,PASSWORD_DEFAULT);$this->run("UPDATE admins SET password=? WHERE id=?",[$hash,(int)$admin['id']]);$admin['password']=$hash;}return $admin;
    }
    public function getLoginAttempts($login){$login=$this->str($login,255);if($login==='')return null;return $this->query("SELECT login_attempts,last_login_attempt FROM admins WHERE username=? OR email=?",[$login,$this->email($login)],'first');}
    public function incrementLoginAttempts($login){$login=$this->str($login,255);if($login==='')return false;return $this->run("UPDATE admins SET login_attempts=login_attempts+1,last_login_attempt=datetime('now') WHERE username=? OR email=?",[$login,$this->email($login)]);}
    public function resetLoginAttempts($login){$login=$this->str($login,255);if($login==='')return false;return $this->run("UPDATE admins SET login_attempts=0,last_login_attempt=NULL WHERE username=? OR email=?",[$login,$this->email($login)]);}
    public function registerAdmin($username,$password,$email,$role='organizer')
    {
        $username=$this->str($username,100);$email=$this->email($email);$role=in_array($role,['superadmin','admin','organizer'],true)?$role:'organizer';if($username===''||$email===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($password)||$password==='')return false;if($this->query("SELECT id FROM admins WHERE username=? OR email=?",[$username,$email],'first'))return 'exists';$hash=password_hash($password,PASSWORD_DEFAULT);$r=$this->callD1("INSERT INTO admins(username,password,email,role) VALUES(?,?,?,?)",[$username,$hash,$email,$role],'run');return $r?$this->lastInsertId:false;
    }
    public function getAdminById($id){$id=$this->id($id);if($id===null)return null;return $this->query("SELECT * FROM admins WHERE id=?",[$id],'first');}
    public function getAdminByEmail($email){$email=$this->email($email);if($email==='')return null;return $this->query("SELECT * FROM admins WHERE email=?",[$email],'first');}
    public function updateAdminProfile($id,$data)
    {
        $id=$this->id($id);if($id===null||!is_array($data))return false;$allowed=['username','email','full_name','phone','company','avatar','bio','verification_code','verification_code_created_at','is_verified','login_attempts','last_login_attempt','password','role'];$fields=[];$params=[];foreach($data as $key=>$value){if(!in_array($key,$allowed,true))continue;if($key==='email'){$value=$this->email($value);if(!filter_var($value,FILTER_VALIDATE_EMAIL))return false;}elseif($key==='username'){$value=$this->str($value,100);if($value==='')return false;}elseif($key==='role'){$value=in_array($value,['superadmin','admin','organizer'],true)?$value:'organizer';}elseif(in_array($key,['is_verified','login_attempts'],true)){$value=(int)$value;}elseif($key==='password'){if($value==='')return false;if(password_get_info($value)['algo']===null)$value=password_hash($value,PASSWORD_DEFAULT);}else{$value=$value!==null?$this->str($value,255):null;}$fields[]=$key.'=?';$params[]=$value;}if(!$fields)return false;$params[]=$id;return $this->run('UPDATE admins SET '.implode(',',$fields).' WHERE id=?',$params);
    }
    public function setAdminVerificationCode($adminId,$code){$adminId=$this->id($adminId);$code=$this->str($code,64);if($adminId===null||!preg_match('/^\d{6}$/',$code))return false;return $this->run("UPDATE admins SET verification_code=?,verification_code_created_at=datetime('now'),is_verified=0 WHERE id=?",[$code,$adminId]);}
    public function verifyAdmin($adminId,$code){$adminId=$this->id($adminId);$code=$this->str($code,64);if($adminId===null||!preg_match('/^\d{6}$/',$code))return false;$r=$this->callD1("UPDATE admins SET is_verified=1,verification_code=NULL,verification_code_created_at=NULL WHERE id=? AND verification_code=? AND verification_code_created_at>datetime('now','-15 minutes') AND is_verified=0",[$adminId,$code],'run');return $r!==null&&$this->changes($r)>0;}
    public function createPasswordReset($email,$token){$email=$this->email($email);$token=$this->str($token,255);if($email===''||$token==='')return false;$hash=hash('sha256',$token);$this->run('DELETE FROM password_resets WHERE email=?',[$email]);return $this->run("INSERT INTO password_resets(email,token,created_at) VALUES(?,?,datetime('now'))",[$email,$hash]);}
    public function getPasswordReset($token){$token=$this->str($token,255);if($token==='')return null;return $this->query("SELECT * FROM password_resets WHERE token=? AND created_at>datetime('now','-1 hour') LIMIT 1",[hash('sha256',$token)],'first');}
    public function deletePasswordReset($email){$email=$this->email($email);if($email==='')return false;return $this->run('DELETE FROM password_resets WHERE email=?',[$email]);}
    public function updateAdminPasswordByEmail($email,$password){$email=$this->email($email);if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($password)||$password==='')return false;return $this->run('UPDATE admins SET password=? WHERE email=?',[password_hash($password,PASSWORD_DEFAULT),$email]);}

    // ADMIN / TICKETS
    public function getAllTickets($adminId=null){if($adminId!==null){$adminId=$this->id($adminId);if($adminId===null)return [];return $this->query("SELECT t.*,e.title AS event_title FROM tickets t JOIN events e ON t.event_id=e.id WHERE e.admin_id=? ORDER BY t.purchase_date DESC",[$adminId]);}return $this->query("SELECT t.*,e.title AS event_title FROM tickets t JOIN events e ON t.event_id=e.id ORDER BY t.purchase_date DESC");}
    public function countTickets($adminId=null){$r=$adminId!==null?$this->query("SELECT COUNT(t.id) AS total FROM tickets t JOIN events e ON t.event_id=e.id WHERE e.admin_id=?",[$this->id($adminId)],'first'):$this->query('SELECT COUNT(*) AS total FROM tickets',[],'first');return (int)($r['total']??0);}
    public function countEvents($adminId=null){$r=$adminId!==null?$this->query("SELECT COUNT(*) AS total FROM events WHERE status='active' AND admin_id=?",[$this->id($adminId)],'first'):$this->query("SELECT COUNT(*) AS total FROM events WHERE status='active'",[],'first');return (int)($r['total']??0);}
    public function updateTicketStatus($id,$status,$adminId=null){$id=$this->id($id);$status=$this->str($status,50);if($id===null||!in_array($status,['valid','used','cancelled'],true))return false;$sql='UPDATE tickets SET status=? WHERE id=?';$p=[$status,$id];if($adminId!==null){$sql.=' AND event_id IN (SELECT id FROM events WHERE admin_id=?)';$p[]=$this->id($adminId);} $r=$this->callD1($sql,$p,'run');return $r!==null&&$this->changes($r)>0;}
    public function getTicketById($id){$id=$this->id($id);if($id===null)return null;return $this->query("SELECT t.*,e.title AS event_title,tt.name AS type_name FROM tickets t JOIN events e ON t.event_id=e.id LEFT JOIN ticket_types tt ON t.ticket_type_id=tt.id WHERE t.id=?",[$id],'first');}
    public function updateTicketData($id,$name,$email,$phone,$adminId=null){$id=$this->id($id);$email=$this->email($email);if($id===null||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))return false;$sql='UPDATE tickets SET attendee_name=?,attendee_email=?,attendee_phone=? WHERE id=?';$p=[$this->str($name,255),$email,$this->phone($phone),$id];if($adminId!==null){$sql.=' AND event_id IN (SELECT id FROM events WHERE admin_id=?)';$p[]=$this->id($adminId);} $r=$this->callD1($sql,$p,'run');return $r!==null&&$this->changes($r)>0;}

    // IDEMPOTENCIA DE COMPRA
    public function createPurchaseRequest($requestToken,$eventId,$email=null)
    {
        $requestToken=$this->str($requestToken,100);$eventId=$this->id($eventId);$email=$email!==null?$this->email($email):null;if($requestToken===''||$eventId===null)return false;
        $r=$this->callD1("INSERT INTO purchase_requests(request_token,event_id,email,status) VALUES(?,?,?,'processing')",[$requestToken,$eventId,$email],'run');
        return $r!==null;
    }
    public function purchaseRequestExists($requestToken){$requestToken=$this->str($requestToken,100);if($requestToken==='')return false;return (bool)$this->query('SELECT id FROM purchase_requests WHERE request_token=? LIMIT 1',[$requestToken],'first');}
    public function completePurchaseRequest($requestToken,$status='completed',$paymentIntentId=null){$requestToken=$this->str($requestToken,100);if($requestToken==='')return false;$allowed=['processing','completed','failed','paid'];if(!in_array($status,$allowed,true))$status='completed';$r=$this->callD1('UPDATE purchase_requests SET status=?,payment_intent_id=? WHERE request_token=?',[$status,$paymentIntentId!==null?$this->str($paymentIntentId,255):null,$requestToken],'run');return $r!==null&&$this->changes($r)>0;}
}
