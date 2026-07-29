<?php
// =================================================================
// SEGURIDAD - Funciones de protección (v3.0)
// =================================================================

// Prevenir ejecución directa
if (!defined('APP_ENV') && !isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Headers de seguridad solo si no se han enviado ya.
// Idealmente la CSP principal debe vivir en .htaccess para no duplicarla.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (!defined('APP_ENV') || APP_ENV !== 'production') {
        header("X-Robots-Tag: noindex, nofollow", false);
    }
}

// =================================================================
// HELPERS INTERNOS
// =================================================================
if (!function_exists('clientIp')) {
    function clientIp() {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';
    }
}

if (!function_exists('normalizePhone')) {
    function normalizePhone($phone) {
        return preg_replace('/[^0-9+]/', '', (string) $phone);
    }
}

if (!function_exists('safeString')) {
    function safeString($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// =================================================================
// RATE LIMITING - básico por sesión/IP
// =================================================================
function checkRateLimit($action, $maxAttempts = 5, $windowSeconds = 300) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    $action = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $action);
    $ip = clientIp();
    $key = 'rate_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + (int) $windowSeconds];
    }

    if (time() > (int) $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + (int) $windowSeconds];
    }

    $_SESSION[$key]['count']++;

    return $_SESSION[$key]['count'] <= (int) $maxAttempts;
}

function getRateLimitRemaining($action, $maxAttempts = 5) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return (int) $maxAttempts;
    }

    $action = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $action);
    $ip = clientIp();
    $key = 'rate_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key]['count'])) {
        return (int) $maxAttempts;
    }

    return max(0, (int) $maxAttempts - (int) $_SESSION[$key]['count']);
}

// =================================================================
// FUNCIONES AUXILIARES DEL SISTEMA
// =================================================================
function generateTicketCode() {
    return 'TCK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . random_int(1000, 9999);
}

function formatDate($date, $format = 'd/m/Y H:i') {
    $timestamp = strtotime((string) $date);
    if ($timestamp === false) {
        return '';
    }
    return date($format, $timestamp);
}

function formatCurrency($amount, $currency = 'EUR') {
    $amount = (float) $amount;

    if (class_exists('NumberFormatter')) {
        $locale = 'es_ES';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, $currency);
        if ($formatted !== false) {
            return $formatted;
        }
    }

    return number_format($amount, 2, ',', '.') . ' €';
}

function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }

    $data = trim((string) $data);
    $data = stripslashes($data);

    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// =================================================================
// CSRF
// =================================================================
function generate_csrf_token($form = 'default') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (empty($_SESSION['csrf_tokens'][$form])) {
        $_SESSION['csrf_tokens'][$form] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$form];
}

function verify_csrf_token($token, $form = 'default', $rotate = false) {
    if (
        session_status() !== PHP_SESSION_ACTIVE ||
        !is_string($token) ||
        $token === '' ||
        empty($_SESSION['csrf_tokens'][$form]) ||
        !hash_equals($_SESSION['csrf_tokens'][$form], $token)
    ) {
        return false;
    }

    if ($rotate) {
        unset($_SESSION['csrf_tokens'][$form]);
    }

    return true;
}

function csrf_field($form = 'default') {
    $token = generate_csrf_token($form);
    return '<input type="hidden" name="csrf_token" value="' . safeString($token) . '">';
}

function require_valid_csrf($form = 'default', $field = 'csrf_token', $rotate = false) {
    $token = $_POST[$field] ?? '';
    if (!verify_csrf_token($token, $form, $rotate)) {
        http_response_code(419);
        exit('Token CSRF inválido o ausente.');
    }
}

// =================================================================
// VALIDACIÓN
// =================================================================
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// =================================================================
// QR CODE
// =================================================================
function generateQRCode($data, $filename) {
    $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $filename);
    if ($safeFilename === '') {
        throw new Exception('Nombre de fichero QR inválido.');
    }

    $filepath = QRCODES_PATH . '/' . $safeFilename . '.png';

    if (!file_exists(QRCODES_PATH)) {
        if (!mkdir(QRCODES_PATH, 0755, true) && !is_dir(QRCODES_PATH)) {
            throw new Exception('No se pudo crear el directorio de QRs.');
        }
    }

    if (!is_writable(QRCODES_PATH)) {
        throw new Exception('El directorio de QRs no tiene permisos de escritura.');
    }

    $success = false;

    if (!class_exists('TCPDF2DBarcode')) {
        $tcpdfBarcodesPath = ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
        if (file_exists($tcpdfBarcodesPath)) {
            require_once $tcpdfBarcodesPath;
        }
    }

    if (class_exists('TCPDF2DBarcode')) {
        try {
            $barcodeobj = new TCPDF2DBarcode($data, 'QRCODE,M');
            $pngData = $barcodeobj->getBarcodePngData();
            if ($pngData !== false && @file_put_contents($filepath, $pngData, LOCK_EX) !== false) {
                $success = true;
            }
        } catch (Throwable $e) {
            if (function_exists('qLog')) qLog('[WARNING] Error con TCPDF2DBarcode: ' . $e->getMessage());
        }
    }

    if (!$success) {
        if (!class_exists('QRcode')) {
            $paths = [
                ROOT_PATH . '/vendor/phpqrcode/phpqrcode/qrlib.php',
                ROOT_PATH . '/vendor/phpqrcode/qrlib.php',
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }

        if (class_exists('QRcode') && method_exists('QRcode', 'png')) {
            try {
                @QRcode::png($data, $filepath, QR_ECLEVEL_M, 10);
                if (file_exists($filepath) && filesize($filepath) > 0) {
                    $success = true;
                }
            } catch (Throwable $e) {
                if (function_exists('qLog')) qLog('[WARNING] Error con QRcode local: ' . $e->getMessage());
            }
        }
    }

    if (!$success || !file_exists($filepath)) {
        return 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($data) . '&choe=UTF-8';
    }

    @chmod($filepath, 0644);

    return 'qrcodes/' . $safeFilename . '.png';
}

// =================================================================
// EMAIL
// =================================================================
function sendTicketEmail($to, $subject, $body, $attachment = null) {
    if (!validateEmail($to)) {
        if (function_exists('qLog')) qLog('[ERROR] Email inválido en sendTicketEmail: ' . $to);
        return false;
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $base = ROOT_PATH . '/vendor/phpmailer/phpmailer/src/';
        if (file_exists($base . 'PHPMailer.php')) {
            require_once $base . 'PHPMailer.php';
            require_once $base . 'SMTP.php';
            require_once $base . 'Exception.php';
        } else {
            $oldPath = ROOT_PATH . '/vendor/phpmailer/';
            if (file_exists($oldPath . 'PHPMailer.php')) {
                require_once $oldPath . 'PHPMailer.php';
                require_once $oldPath . 'SMTP.php';
                require_once $oldPath . 'Exception.php';
            }
        }
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        if (function_exists('qLog')) qLog('[ERROR] PHPMailer no está disponible.');
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();

        if (isset($_SESSION['debug_email']) && APP_ENV !== 'production') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str) {
                if (!isset($_SESSION['smtp_log'])) {
                    $_SESSION['smtp_log'] = '';
                }
                $_SESSION['smtp_log'] .= $str . PHP_EOL;
            };
        }

        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = ((int) SMTP_PORT === 465)
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) SMTP_PORT;
        $mail->Timeout = 8;

        // TLS seguro: no desactivar verify_peer en producción.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        if ($attachment && is_string($attachment) && file_exists($attachment)) {
            $mail->addAttachment($attachment);
        }

        $mail->isHTML(true);
        $mail->Subject = (string) $subject;
        $mail->Body = (string) $body;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $body)));

        if ($mail->send()) {
            return true;
        }

        if (function_exists('qLog')) qLog('[WARNING] SMTP falló: ' . $mail->ErrorInfo . '. Intentando fallback mail().');
        return fallbackMail($to, $subject, $body, $attachment);
    } catch (Throwable $e) {
        if (function_exists('qLog')) qLog('[WARNING] Excepción SMTP: ' . $e->getMessage() . '. Intentando fallback mail().');
        return fallbackMail($to, $subject, $body, $attachment);
    }
}

function fallbackMail($to, $subject, $body, $attachment = null) {
    if (!validateEmail($to)) {
        return false;
    }

    if (function_exists('qLog')) qLog('[TRACE] Entrando en fallbackMail para ' . $to);

    $from = SMTP_FROM_EMAIL;
    $safeFromName = str_replace(["\r", "\n"], '', SMTP_FROM_NAME);
    $safeSubject = str_replace(["\r", "\n"], '', (string) $subject);

    $headers = 'From: ' . $safeFromName . ' <' . $from . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if ($attachment && file_exists($attachment) && is_readable($attachment)) {
        $boundary = 'b1_' . bin2hex(random_bytes(12));
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

        $filename = basename($attachment);
        $content = chunk_split(base64_encode((string) file_get_contents($attachment)));

        $message = '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: application/pdf; name="' . $filename . '"' . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
        $message .= $content . "\r\n";
        $message .= '--' . $boundary . '--';

        $result = mail($to, $safeSubject, $message, $headers);
    } else {
        $headers .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $result = mail($to, $safeSubject, $body, $headers);
    }

    if (function_exists('qLog')) qLog('[TRACE] Resultado de mail() nativo: ' . ($result ? 'ÉXITO' : 'FALLO'));
    return $result;
}

function sendResetPasswordEmail($email, $token) {
    $resetLink = rtrim(SITE_URL, '/') . '/admin/reset-password.php?token=' . urlencode((string) $token);
    $subject = 'Recuperación de contraseña - ' . SITE_NAME;
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Recuperación de contraseña</h2>
            <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para continuar:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . safeString($resetLink) . "' style='background: #DAFB71; color: black; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold;'>Restablecer contraseña</a>
            </div>
            <p>Si no has solicitado esto, puedes ignorar este correo.</p>
            <p>Este enlace expirará en 1 hora.</p>
        </div>
    ";

    return sendTicketEmail($email, $subject, $body);
}

function sendVerificationCodeEmail($email, $code) {
    $subject = 'Verifica tu cuenta - ' . SITE_NAME;
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Bienvenido a " . safeString(SITE_NAME) . "</h2>
            <p>Para completar tu registro, introduce el siguiente código de verificación:</p>
            <div style='text-align: center; margin: 30px 0; background: #f4f4f4; padding: 20px; border-radius: 10px;'>
                <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px;'>" . safeString($code) . "</span>
            </div>
            <p>Si no has intentado registrarte, por favor ignora este correo.</p>
        </div>
    ";

    return sendTicketEmail($email, $subject, $body);
}

// =================================================================
// UTILIDADES
// =================================================================
function generateWhatsAppLink($phone, $message) {
    $phone = preg_replace('/[^0-9]/', '', (string) $phone);
    return 'https://wa.me/' . $phone . '?text=' . urlencode((string) $message);
}

function checkAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function generateEmailBody($event, $tickets, $name, $totalPrice) {
    $eventDate = formatDate($event['date_event'] ?? '', 'd/m/Y');
    $eventTime = formatDate($event['date_event'] ?? '', 'H:i');

    $ticketsHtml = '';
    foreach ($tickets as $t) {
        $ticketsHtml .= "
        <div style='background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 15px; margin-bottom: 10px;'>
            <div style='display: flex; justify-content: space-between; align-items: center; gap: 16px;'>
                <div>
                    <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Asistente</p>
                    <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>" . safeString($t['name'] ?? '') . "</p>
                </div>
                <div style='text-align: right;'>
                    <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Tipo</p>
                    <p style='margin: 5px 0 0 0; color: #DAFB71; font-weight: bold;'>" . safeString($t['type_name'] ?? 'General') . "</p>
                </div>
            </div>
            <div style='margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.1);'>
                <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Código de ticket</p>
                <p style='margin: 5px 0 0 0; color: #fff; font-family: monospace; font-size: 14px;'>" . safeString($t['code'] ?? '') . "</p>
            </div>
        </div>";
    }

    $body = "
    <div style='background-color: #0A0E14; color: #ffffff; font-family: sans-serif; padding: 40px 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 30px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4);'>
            <div style='background: #DAFB71; padding: 40px 20px; text-align: center;'>
                <h1 style='color: #000; margin: 0; font-size: 28px; font-weight: 900; letter-spacing: -1px;'>¡Tus tickets están aquí!</h1>
                <p style='color: #000; opacity: 0.7; margin: 10px 0 0 0; font-weight: bold;'>Prepárate para una experiencia inolvidable</p>
            </div>

            <div style='padding: 30px;'>
                <p style='color: #888; margin: 0 0 20px 0;'>Hola <strong>" . safeString($name) . "</strong>,</p>
                <p style='color: #ccc; line-height: 1.6; margin-bottom: 30px;'>Tu compra para <strong>" . safeString($event['title'] ?? '') . "</strong> ha sido confirmada. Aquí tienes los detalles de tus entradas:</p>

                <div style='background: rgba(0,0,0,0.2); border-radius: 20px; padding: 20px; margin-bottom: 30px;'>
                    <div style='margin-bottom: 15px;'>
                        <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Evento</p>
                        <p style='margin: 5px 0 0 0; color: #fff; font-size: 18px; font-weight: bold;'>" . safeString($event['title'] ?? '') . "</p>
                    </div>
                    <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>
                        <div>
                            <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Fecha y hora</p>
                            <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>" . safeString(trim($eventDate . ' ' . $eventTime)) . "</p>
                        </div>
                        <div>
                            <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Lugar</p>
                            <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>" . safeString($event['location'] ?? '') . "</p>
                        </div>
                    </div>
                </div>

                {$ticketsHtml}

                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;'>
                    <p style='color: #DAFB71; font-weight: bold; margin-bottom: 10px;'>Total pagado: " . safeString(formatCurrency($totalPrice)) . "</p>
                    <p style='color: #555; font-size: 12px;'>Adjuntamos un PDF con tus entradas y códigos QR para el acceso.</p>
                </div>
            </div>

            <div style='background: rgba(255,255,255,0.01); padding: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05);'>
                <p style='color: #444; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; margin: 0;'>
                    Created by <span style='color: #DAFB71;'>Creative Technologies</span> by Gabriel Guerra
                </p>
            </div>
        </div>
    </div>";

    return $body;
}

function paginate($total, $page, $limit = 10) {
    $total = max(0, (int) $total);
    $page = max(1, (int) $page);
    $limit = max(1, (int) $limit);
    $totalPages = max(1, (int) ceil($total / $limit));
    $offset = ($page - 1) * $limit;

    return [
        'offset' => $offset,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1,
    ];
}

function generateTicketPDF($event, $tickets, $totalPrice) {
    require_once ROOT_PATH . '/includes/classes/TicketPDF.php';

    $pdf = new TicketPDF($event, $tickets, $totalPrice);
    $pdfContent = $pdf->generatePDF();

    $filename = 'tickets_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = UPLOADS_PATH . '/' . $filename;

    if (!file_exists(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0755, true);
    }

    file_put_contents($filepath, $pdfContent, LOCK_EX);
    @chmod($filepath, 0644);

    return $filepath;
}

function uploadImage($file, $destination) {
    if (!isset($file['tmp_name'], $file['name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMime[$mime])) {
        return false;
    }

    $newname = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
    $filepath = rtrim($destination, '/\\') . '/' . $newname;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        @chmod($filepath, 0644);
        return $newname;
    }

    return false;
}

// =================================================================
// COMPRA
// =================================================================
function completePurchase($data, $db) {
    if (function_exists('qLog')) {
        qLog('[TRACE] Entrando en completePurchase [v3.0]');
    }

    $eventId      = (int) ($data['event_id'] ?? 0);
    $ticketTypeId = !empty($data['ticket_type_id']) ? (int) $data['ticket_type_id'] : null;
    $quantity     = max(1, (int) ($data['quantity'] ?? 1));
    $attendees    = is_array($data['attendees'] ?? null) ? $data['attendees'] : [];
    $phone        = cleanInput($data['phone'] ?? '');
    $zipCode      = cleanInput($data['zip_code'] ?? '');
    $totalPrice   = (float) ($data['total_price'] ?? 0);

    if ($eventId <= 0) {
        throw new Exception('Evento inválido.');
    }

    if (count($attendees) !== $quantity) {
        throw new Exception('La cantidad de asistentes no coincide con la cantidad de tickets.');
    }

    $event = $db->getEventById($eventId);
    if (!$event) {
        throw new Exception('Evento no encontrado');
    }

    $ticketTypeName = '';
    if ($ticketTypeId) {
        $tt = $db->getTicketTypeById($ticketTypeId);
        $ticketTypeName = $tt['name'] ?? '';
    }

    try {
        $primaryEmail = cleanInput($attendees[0]['email'] ?? '');
        $primaryName  = trim(cleanInput($attendees[0]['name'] ?? '') . ' ' . cleanInput($attendees[0]['surname'] ?? ''));

        if (!validateEmail($primaryEmail)) {
            throw new Exception('Email principal inválido.');
        }

        $tickets = [];

        if (function_exists('qLog')) {
            qLog('[INFO] completePurchase: quantity=' . $quantity . ', attendees_count=' . count($attendees));
        }

        $existingTickets = $db->getRecentTicketsByPhone($phone, $eventId, 10);
        $isDuplicate = false;

        if (count($existingTickets) >= $quantity) {
            foreach ($existingTickets as $et) {
                if (
                    isset($et['attendee_email']) &&
                    strcasecmp((string) $et['attendee_email'], (string) $primaryEmail) === 0
                ) {
                    $isDuplicate = true;
                    break;
                }
            }
        }

        if ($isDuplicate) {
            if (function_exists('qLog')) {
                qLog('[TRACE] Idempotencia activa: compra ya registrada recientemente.');
            }

            foreach ($existingTickets as $et) {
                if (
                    isset($et['attendee_email']) &&
                    strcasecmp((string) $et['attendee_email'], (string) $primaryEmail) === 0
                ) {
                    $tickets[] = [
                        'code'      => $et['ticket_code'] ?? '',
                        'qr_path'   => $et['qr_code_path'] ?? '',
                        'name'      => $et['attendee_name'] ?? '',
                        'email'     => $et['attendee_email'] ?? '',
                        'type_name' => $et['type_name'] ?? $ticketTypeName,
                    ];
                }
            }
        } else {
            foreach ($attendees as $attendee) {
                $name = cleanInput($attendee['name'] ?? '');
                $surname = cleanInput($attendee['surname'] ?? '');
                $email = cleanInput($attendee['email'] ?? '');

                if ($name === '' || $surname === '' || !validateEmail($email)) {
                    throw new Exception('Datos de asistente inválidos.');
                }

                $fullName = trim($name . ' ' . $surname);
                $ticketCode = generateTicketCode();
                $qrData = rtrim(SITE_URL, '/') . '/ticket.php?code=' . urlencode($ticketCode);

                if (function_exists('qLog')) qLog('[TRACE] Generando QR para: ' . $ticketCode);
                $qrPath = generateQRCode($qrData, $ticketCode);

                $referral = isset($_SESSION['referral']) ? cleanInput($_SESSION['referral']) : null;

                if (function_exists('qLog')) qLog('[TRACE] Creando ticket en D1...');
                $success = $db->createTicket(
                    $eventId,
                    $ticketCode,
                    $fullName,
                    $email,
                    $phone,
                    $qrPath,
                    $ticketTypeId,
                    $referral,
                    $zipCode
                );

                if (!$success) {
                    $dbError = 'Fallo INSERT ticket: ' . $ticketCode . ' | Event: ' . $eventId . ' | Error: ' . ($db->lastError ?? 'Unknown');
                    if (function_exists('qLog')) qLog('[ERROR] ' . $dbError);
                    throw new Exception('Error al emitir el ticket. Por favor, contacta con soporte.');
                }

                $tickets[] = [
                    'code'      => $ticketCode,
                    'qr_path'   => $qrPath,
                    'name'      => $fullName,
                    'email'     => $email,
                    'type_name' => $ticketTypeName ?: 'General',
                ];
            }

            if (!$db->updateAvailableTickets($eventId, $quantity) && function_exists('qLog')) {
                qLog('[ERROR] No se pudo descontar el stock general del evento ' . $eventId);
            }

            if ($ticketTypeId && !$db->updateAvailableTicketType($ticketTypeId, $quantity) && function_exists('qLog')) {
                qLog('[ERROR] No se pudo descontar el stock del tipo de ticket ' . $ticketTypeId);
            }
        }

        $subject = 'Tus tickets para ' . ($event['title'] ?? SITE_NAME);
        $emailBody = generateEmailBody($event, $tickets, $primaryName, $totalPrice);

        $pdfPath = null;
        try {
            $pdfPath = generateTicketPDF($event, $tickets, $totalPrice);
            if (function_exists('qLog')) qLog('[TRACE] PDF generado: ' . basename($pdfPath));
        } catch (Throwable $pdfEx) {
            if (function_exists('qLog')) qLog('[WARNING] Error generando PDF: ' . $pdfEx->getMessage());
        }

        $emailSent = sendTicketEmail($primaryEmail, $subject, $emailBody, $pdfPath);
        if (!$emailSent && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['email_error'] = 'No se pudo enviar el correo con las entradas, aunque la compra sí quedó registrada.';
        }

        return [
            'event_id'    => $eventId,
            'event_title' => $event['title'] ?? '',
            'tickets'     => $tickets,
            'total_price' => $totalPrice,
            'email'       => $primaryEmail,
            'phone'       => $phone,
        ];
    } catch (Throwable $e) {
        throw $e;
    }
}

/**
 * Sincronización asíncrona con D1.
 */
function syncTicketToD1Async($ticketData) {
    if (!defined('D1_SYNC_URL') || !defined('D1_SYNC_TOKEN') || D1_SYNC_URL === '' || D1_SYNC_TOKEN === '') {
        return false;
    }

    $ch = curl_init(D1_SYNC_URL);

    $payload = json_encode([
        'action' => 'insert_ticket',
        'ticket' => $ticketData,
    ]);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . D1_SYNC_TOKEN,
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

    $response = curl_exec($ch);
    $ok = ($response !== false);
    curl_close($ch);

    return $ok;
}
?>
