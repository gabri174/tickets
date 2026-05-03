<?php
// =================================================================
// SEGURIDAD - Funciones de protección
// =================================================================

// Prevenir ejecución directa
if (!defined('APP_ENV') && !isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Headers de seguridad (solo si no se han enviado headers)
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'; img-src 'self' https: data: blob:; font-src 'self' https: data:; script-src 'self' https: 'unsafe-inline' 'unsafe-eval'; connect-src 'self' https://tickets-api.crtv-technologies.workers.dev https://static.cloudflareinsights.com https://cloudflareinsights.com;");
}

// =================================================================
// RATE LIMITING - Prevenir ataques de fuerza bruta
// =================================================================
function checkRateLimit($action, $maxAttempts = 5, $windowSeconds = 300) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $windowSeconds];
    }

    // Resetear si pasó la ventana de tiempo
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $windowSeconds];
    }

    $_SESSION[$key]['count']++;

    if ($_SESSION[$key]['count'] > $maxAttempts) {
        return false; // Bloqueado
    }

    return true; // Permitido
}

function getRateLimitRemaining($action, $maxAttempts = 5) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        return $maxAttempts;
    }

    return max(0, $maxAttempts - $_SESSION[$key]['count']);
}

// =================================================================
// FUNCIONES AUXILIARES DEL SISTEMA
// =================================================================

// Generar código único para ticket (más seguro)
function generateTicketCode() {
    // Usar random_bytes para mayor seguridad
    return 'TCK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . random_int(1000, 9999);
}

// Formatear fecha
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Formatear moneda
function formatCurrency($amount) {
    return '$' . number_format($amount, 2, '.', ',');
}

// Limpiar y sanitizar input
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    // Usamos ENT_QUOTES para mayor seguridad en XSS
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// CSRF Protection
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !isset($token) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generar QR Code
function generateQRCode($data, $filename) {
    $filepath = QRCODES_PATH . '/' . $filename . '.png';
    
    // Asegurar que el directorio existe
    if (!file_exists(QRCODES_PATH)) {
        if (!mkdir(QRCODES_PATH, 0777, true)) {
            throw new Exception("No se pudo crear el directorio de QRs. Verifica los permisos de carpeta.");
        }
    }
    
    if (!is_writable(QRCODES_PATH)) {
        throw new Exception("El directorio de QRs no tiene permisos de escritura.");
    }

    // Intentar cargar la librería de códigos 2D de TCPDF si no está cargada
    if (!class_exists('TCPDF2DBarcode')) {
        $tcpdfBarcodesPath = ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
        if (file_exists($tcpdfBarcodesPath)) {
            require_once $tcpdfBarcodesPath;
        }
    }

    // Intentar usar TCPDF2DBarcode (más robusto contra conflictos de nombres)
    if (class_exists('TCPDF2DBarcode')) {
        $barcodeobj = new TCPDF2DBarcode($data, 'QRCODE,M');
        $pngData = $barcodeobj->getBarcodePngData();
        if ($pngData) {
            file_put_contents($filepath, $pngData);
        } else {
            throw new Exception("Error al generar los datos del QR con TCPDF.");
        }
    } else {
        // Fallback a PHPQRCode si TCPDF2DBarcode no está disponible
        if (!class_exists('QRcode')) {
            $paths = [
                ROOT_PATH . '/vendor/phpqrcode/phpqrcode/qrlib.php',
                ROOT_PATH . '/vendor/phpqrcode/qrlib.php'
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }
        
        // Solo llamamos a QRcode::png si sabemos que no es el de TCPDF (que no tiene png)
        if (class_exists('QRcode') && method_exists('QRcode', 'png')) {
            QRcode::png($data, $filepath, QR_ECLEVEL_M, 10);
        } else {
            throw new Exception("Error: Conflicto de librerías QR detectado. Por favor, asegúrate de que el servidor ha sido actualizado correctamente.");
        }
    }
    
    if (!file_exists($filepath)) {
        throw new Exception("Error interno: No se pudo generar el archivo de imagen QR.");
    }
    
    return $filepath;
}

// Enviar email con ticket
function sendTicketEmail($to, $subject, $body, $attachment = null) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $base = ROOT_PATH . '/vendor/phpmailer/phpmailer/src/';
        if (file_exists($base . 'PHPMailer.php')) {
            require_once $base . 'PHPMailer.php';
            require_once $base . 'SMTP.php';
            require_once $base . 'Exception.php';
        } else {
            // Reintento con ruta manual antigua si existe
            $oldPath = ROOT_PATH . '/vendor/phpmailer/';
            if (file_exists($oldPath . 'PHPMailer.php')) {
                require_once $oldPath . 'PHPMailer.php';
                require_once $oldPath . 'SMTP.php';
                require_once $oldPath . 'Exception.php';
            }
        }
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false; // O lanzar excepción si prefieres, pero aquí el código original retornaba false
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    
    try {
        $mail->isSMTP();
        
        // Activar debug si hay sesión de debug
        if (isset($_SESSION['debug_email'])) {
            $mail->SMTPDebug = 2; // Client messages & server responses
            $mail->Debugoutput = function($str, $level) {
                if (!isset($_SESSION['smtp_log'])) $_SESSION['smtp_log'] = "";
                $_SESSION['smtp_log'] .= $str . "\n";
            };
        }
        
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_PORT == 465) ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 5; // 5 segundos máximo para conectar (rápido fallback si el puerto está bloqueado)

        // Opciones adicionales para evitar errores de certificados en algunos hostings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        if ($attachment) {
            $mail->addAttachment($attachment);
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if ($mail->send()) {
            return true;
        }
        if (function_exists('qLog')) qLog("[WARNING] SMTP falló: " . $mail->ErrorInfo . ". Intentando mail() nativo como fallback...");
        return fallbackMail($to, $subject, $body, $attachment);
    } catch (Exception $e) {
        if (function_exists('qLog')) qLog("[WARNING] Excepción en SMTP: " . $e->getMessage() . ". Intentando mail() nativo...");
        return fallbackMail($to, $subject, $body, $attachment);
    }
}

/**
 * Fallback usando la función mail() de PHP para cuando el puerto SMTP está bloqueado
 */
function fallbackMail($to, $subject, $body, $attachment = null) {
    if (function_exists('qLog')) qLog("[TRACE] Entrando en fallbackMail para $to");
    $from = SMTP_FROM_EMAIL;
    $headers = "From: " . SMTP_FROM_NAME . " <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    if ($attachment && file_exists($attachment)) {
        $boundary = md5(time());
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        $filename = basename($attachment);
        $content = chunk_split(base64_encode(file_get_contents($attachment)));
        
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $message .= $content . "\r\n\r\n";
        $message .= "--$boundary--";
        
        $result = mail($to, $subject, $message, $headers);
    } else {
        $headers .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $result = mail($to, $subject, $body, $headers);
    }
    
    if (function_exists('qLog')) qLog("[TRACE] Resultado de mail() nativo: " . ($result ? "ÉXITO" : "FALLO"));
    return $result;
}

function sendResetPasswordEmail($email, $token) {
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/public/admin/forgot-password.php', '', $_SERVER['PHP_SELF']));
    }
    $resetLink = SITE_URL . "/admin/reset-password.php?token=" . $token;
    $subject = "Recuperación de contraseña - " . SITE_NAME;
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Recuperación de contraseña</h2>
            <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para continuar:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background: #DAFB71; color: black; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold;'>Restablecer Contraseña</a>
            </div>
            <p>Si no has solicitado esto, puedes ignorar este correo.</p>
            <p>Este enlace expirará en 1 hora.</p>
        </div>
    ";
    return sendTicketEmail($email, $subject, $body);
}

function sendVerificationCodeEmail($email, $code) {
    $subject = "Verifica tu cuenta - " . SITE_NAME;
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Bienvenido a " . SITE_NAME . "</h2>
            <p>Para completar tu registro, introduce el siguiente código de verificación:</p>
            <div style='text-align: center; margin: 30px 0; background: #f4f4f4; padding: 20px; border-radius: 10px;'>
                <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px;'>$code</span>
            </div>
            <p>Si no has intentado registrarte, por favor ignora este correo.</p>
        </div>
    ";
    return sendTicketEmail($email, $subject, $body);
}

// Generar enlace de WhatsApp
function generateWhatsAppLink($phone, $message) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return "https://wa.me/" . $phone . "?text=" . urlencode($message);
}

// Verificar sesión de administrador
function checkAdminSession() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function generateEmailBody($event, $tickets, $name, $totalPrice) {
    $eventDate = date('d/m/Y', strtotime($event['date_event']));
    $eventTime = date('H:i', strtotime($event['date_event']));
    
    $ticketsHtml = '';
    foreach ($tickets as $t) {
        $ticketsHtml .= "
        <div style='background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 15px; margin-bottom: 10px;'>
            <div style='display: flex; justify-content: space-between; align-items: center;'>
                <div>
                    <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Asistente</p>
                    <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>" . htmlspecialchars($t['name']) . "</p>
                </div>
                <div style='text-align: right;'>
                    <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Tipo</p>
                    <p style='margin: 5px 0 0 0; color: #DAFB71; font-weight: bold;'>" . htmlspecialchars($t['type_name'] ?: 'General') . "</p>
                </div>
            </div>
            <div style='margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.1);'>
                <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Código de Ticket</p>
                <p style='margin: 5px 0 0 0; color: #fff; font-family: monospace; font-size: 14px;'>" . htmlspecialchars($t['code']) . "</p>
            </div>
        </div>";
    }

    $body = "
    <div style='background-color: #0A0E14; color: #ffffff; font-family: sans-serif; padding: 40px 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 30px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4);'>
            <div style='background: #DAFB71; padding: 40px 20px; text-align: center;'>
                <h1 style='color: #000; margin: 0; font-size: 28px; font-weight: 900; letter-spacing: -1px;'>¡TUS TICKETS ESTÁN AQUÍ!</h1>
                <p style='color: #000; opacity: 0.7; margin: 10px 0 0 0; font-weight: bold;'>Prepárate para una experiencia inolvidable</p>
            </div>
            
            <div style='padding: 30px;'>
                <p style='color: #888; margin: 0 0 20px 0;'>Hola <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p style='color: #ccc; line-height: 1.6; margin-bottom: 30px;'>Tu compra para <strong>" . htmlspecialchars($event['title']) . "</strong> ha sido confirmada. Aquí tienes los detalles de tus entradas:</p>
                
                <div style='background: rgba(0,0,0,0.2); border-radius: 20px; padding: 20px; margin-bottom: 30px;'>
                    <div style='margin-bottom: 15px;'>
                        <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Evento</p>
                        <p style='margin: 5px 0 0 0; color: #fff; font-size: 18px; font-weight: bold;'>" . htmlspecialchars($event['title']) . "</p>
                    </div>
                    <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>
                        <div>
                            <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Fecha y Hora</p>
                            <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>$eventDate - $eventTime</p>
                        </div>
                        <div>
                            <p style='margin: 0; color: #888; font-size: 10px; text-transform: uppercase; font-weight: bold;'>Lugar</p>
                            <p style='margin: 5px 0 0 0; color: #fff; font-weight: bold;'>" . htmlspecialchars($event['location']) . "</p>
                        </div>
                    </div>
                </div>

                $ticketsHtml

                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;'>
                    <p style='color: #DAFB71; font-weight: bold; margin-bottom: 10px;'>Total Pagado: $totalPrice €</p>
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

// Paginación
function paginate($total, $page, $limit = 10) {
    $total_pages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    
    return [
        'offset' => $offset,
        'limit' => $limit,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1
    ];
}

// Generar PDF de tickets
function generateTicketPDF($event, $tickets, $totalPrice) {
    require_once ROOT_PATH . '/includes/classes/TicketPDF.php';
    
    $pdf = new TicketPDF($event, $tickets, $totalPrice);
    $pdfContent = $pdf->generatePDF();
    
    // Guardar PDF temporalmente
    $filename = 'tickets_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = UPLOADS_PATH . '/' . $filename;
    
    if (!file_exists(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0777, true);
    }
    
    file_put_contents($filepath, $pdfContent);
    return $filepath;
}

// Subir imagen
function uploadImage($file, $destination) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $file['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);
    
    if (in_array(strtolower($filetype), $allowed)) {
        $newname = uniqid() . '.' . $filetype;
        $filepath = $destination . '/' . $newname;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $newname;
        }
    }
    
    return false;
}

// Completar la compra (crear tickets, descontar stock, enviar email)
function completePurchase($data, $db) {
    if (function_exists('qLog')) qLog("[TRACE] Entrando en completePurchase [v2.2 - Proteccion Duplicados + Fallback Mail]");
    
    $eventId = $data['event_id'];
    $ticketTypeId = $data['ticket_type_id'];
    $quantity = $data['quantity'];
    $attendees = $data['attendees'];
    $phone = $data['phone'];
    $totalPrice = $data['total_price'];
    
    $event = $db->getEventById($eventId);
    if (!$event) throw new Exception("Evento no encontrado");
    
    $ticketTypeName = '';
    if ($ticketTypeId) {
        $tt = $db->getTicketTypeById($ticketTypeId);
        $ticketTypeName = $tt['name'] ?? '';
    }

    // No usamos transacciones PDO con D1 Proxy en este momento
    // El sistema funcionará con commits individuales automáticos por el Worker
    
    try {
        $primary_email = cleanInput($attendees[0]['email']);
        $primary_name = cleanInput($attendees[0]['name']) . ' ' . cleanInput($attendees[0]['surname']);
        $tickets = [];

        // LOG: Cantidad de asistentes y quantity esperada
        if (function_exists('qLog')) {
            qLog("[INFO] completePurchase: quantity=$quantity, attendees_count=" . count($attendees));
            foreach ($attendees as $idx => $att) {
                qLog("[INFO] Attendee #" . ($idx+1) . ": " . cleanInput($att['name']) . " " . cleanInput($att['surname']) . " <" . cleanInput($att['email']) . ">");
            }
        }

        // --- IDEMPOTENCY CHECK ---
        // Buscar tickets existentes por teléfono (más fiable que email cuando hay múltiples asistentes)
        $existingTickets = $db->getRecentTicketsByPhone($phone, $eventId, 10);
        if (function_exists('qLog')) qLog("[TRACE] Check Idempotencia: " . count($existingTickets) . " tickets encontrados para teléfono $phone");

        if (count($existingTickets) >= $quantity) {
            if (function_exists('qLog')) qLog("[TRACE] Idempotencia activa: Ya existen tickets recientes (" . count($existingTickets) . "). Saltando inserción DB.");
            foreach ($existingTickets as $et) {
                $tickets[] = [
                    'code' => $et['ticket_code'],
                    'qr_path' => $et['qr_code_path'],
                    'name' => $et['attendee_name'],
                    'email' => $et['attendee_email'],
                    'type_name' => $et['type_name']
                ];
            }
        } else {

        foreach ($attendees as $attendee) {
            $a_name = cleanInput($attendee['name']) . ' ' . cleanInput($attendee['surname']);
            $a_email = cleanInput($attendee['email']);
            
            $ticketCode = generateTicketCode();
            $qrFilename = $ticketCode;
            $qrData = SITE_URL . "/ticket.php?code=" . $ticketCode;
            if (function_exists('qLog')) qLog("[TRACE] Generando QR para: " . $ticketCode);
            $qrPath = generateQRCode($qrData, $qrFilename);
            if (function_exists('qLog')) qLog("[TRACE] QR generado OK.");
            
            $referral = $_SESSION['referral'] ?? null;
            $zipCode = $data['zip_code'] ?? null;

            if (function_exists('qLog')) qLog("[TRACE] Creando ticket en DB MySQL (D1)...");
            $success = $db->createTicket($eventId, $ticketCode, $a_name, $a_email, $phone, $qrPath, $ticketTypeId, $referral, $zipCode);
            
            if (!$success) {
                if (function_exists('qLog')) qLog("[ERROR] Fallo al crear ticket para: " . $ticketCode);
                throw new Exception("Error al emitir el ticket. Por favor contacte con soporte.");
            }
            
            if (function_exists('qLog')) qLog("[TRACE] Ticket creado OK.");
            
            $tickets[] = [
                'code' => $ticketCode,
                'qr_path' => $qrPath,
                'name' => $a_name,
                'email' => $a_email,
                'type_name' => $ticketTypeName
            ];
        }
        
        $db->updateAvailableTickets($eventId, $quantity);
        if ($ticketTypeId) {
            $db->updateAvailableTicketType($ticketTypeId, $quantity);
        }
        } // Fin del else de idempotencia
        
        // Commit automático en D1
        
        // Enviar email
        $subject = "Tus tickets para " . $event['title'];
        $emailBody = generateEmailBody($event, $tickets, $primary_name, $totalPrice);
        $pdfPath = generateTicketPDF($event, $tickets, $totalPrice);
        if (function_exists('qLog')) qLog("[TRACE] PDF generado: " . basename($pdfPath));
        
        // Enviar email - SMTP con fallback a mail() nativo
        $emailSent = false;
        $emailError = null;

        try {
            if (function_exists('qLog')) qLog("[TRACE] Iniciando envío de email a " . $primary_email);
            $emailSent = sendTicketEmail($primary_email, $subject, $emailBody, $pdfPath);
            if ($emailSent) {
                if (function_exists('qLog')) qLog("[TRACE] Email enviado OK.");
            } else {
                $emailError = "El envío de email falló (SMTP y mail() nativo)";
                if (function_exists('qLog')) qLog("[WARNING] No se pudo enviar el email a " . $primary_email);
            }
        } catch (Exception $mailEx) {
            $emailError = "Error al enviar el correo: " . $mailEx->getMessage();
            if (function_exists('qLog')) qLog("[ERROR] Excepción en envío de email: " . $emailError);
        }

        // Guardar error en sesión para mostrar al usuario (solo si hay sesión activa)
        if ($emailError && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['email_error'] = $emailError;
        }

        // NOTA: No lanzamos excepción aquí porque el ticket YA FUE CREADO en la DB
        // El error de email es no-critical: el usuario puede recargar o contactar soporte
        
        return [
            'event_id' => $eventId,
            'event_title' => $event['title'],
            'tickets' => $tickets,
            'total_price' => $totalPrice,
            'email' => $primary_email,
            'phone' => $phone
        ];
    } catch (Throwable $e) {
        // No hay rollback en este modo simple de D1 Proxy
        throw $e;
    }
}

/**
 * Función asíncrona para sincronizar tickets con D1 (Zero-Downtime Migration)
 * Usa cURL rápido con timeout bajo para no bloquear al usuario si el Edge demora.
 */
function syncTicketToD1Async($ticketData) {
    $url = D1_SYNC_URL;
    $ch = curl_init($url);
    
    $payload = json_encode([
        'action' => 'insert_ticket',
        'ticket' => $ticketData
    ]);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . D1_SYNC_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Timeout ultra rápido (1.5 segundos) para que la caída del endpoint D1 no afecte a la venta MySQL principal
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500); 
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    
    curl_exec($ch);
    curl_close($ch);
}
?>
