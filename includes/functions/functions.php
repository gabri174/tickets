<?php
// Funciones auxiliares del sistema

// Generar código único para ticket
function generateTicketCode() {
    return 'TCK-' . strtoupper(uniqid()) . '-' . rand(1000, 9999);
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
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generar QR Code
function generateQRCode($data, $filename) {
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
    
    if (!class_exists('QRcode')) {
        throw new Exception("Librería QRcode no encontrada. Por favor ejecuta 'composer install'");
    }
    
    $filepath = QRCODES_PATH . '/' . $filename . '.png';
    
    if (!file_exists(QRCODES_PATH)) {
        if (!mkdir(QRCODES_PATH, 0777, true)) {
            throw new Exception("No se pudo crear el directorio de QRs. Verifica los permisos de carpeta.");
        }
    }
    
    if (!is_writable(QRCODES_PATH)) {
        throw new Exception("El directorio de QRs no tiene permisos de escritura.");
    }
    
    QRcode::png($data, $filepath, QR_ECLEVEL_M, 10);
    
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
        throw new Exception($mail->ErrorInfo);
    } catch (Exception $e) {
        throw new Exception($e->getMessage());
    }
}

// Generar enlace de WhatsApp
function generateWhatsAppLink($phone, $message) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return "https://wa.me/" . $phone . "?text=" . urlencode($message);
}

// Verificar sesión de administrador
function checkAdminSession() {
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
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
function generateTicketPDF($event, $tickets, $name, $totalPrice) {
    require_once ROOT_PATH . '/includes/classes/TicketPDF.php';
    
    $pdf = new TicketPDF($event, $tickets, $name, $totalPrice);
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
?>
