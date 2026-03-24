<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

// Verificar ID
if (isset($_GET['ticket_id'])) {
    $param = (int)$_GET['ticket_id'];
    $where = "t.id = ?";
} elseif (isset($_GET['ticket_code'])) {
    $param = $_GET['ticket_code'];
    $where = "t.ticket_code = ?";
} else {
    die('ID o Código de ticket requerido');
}

$db = new Database();
$pdo = $db->getPdo();

// Obtener datos del ticket y evento
$stmt = $pdo->prepare("
    SELECT t.*, e.title as event_title, e.date_event, e.location, e.image_url, tt.name as ticket_type_name 
    FROM tickets t 
    JOIN events e ON t.event_id = e.id 
    LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
    WHERE $where
");
$stmt->execute([$param]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die('Ticket no encontrado');
}

// Configuración del Pass (Apple Wallet)
// IMPORTANTE: Para producción necesitas certificados de Apple Developer
$passTypeIdentifier = 'pass.com.tudominio.eventos'; // Tu ID de pass en Apple Developer
$teamIdentifier = 'TEAMID123'; // Tu Team ID de Apple
$organizationName = 'TicketApp';

// Estructura JSON del pase
$passData = [
    "formatVersion" => 1,
    "passTypeIdentifier" => $passTypeIdentifier,
    "serialNumber" => "TICKET-" . $ticket['id'],
    "teamIdentifier" => $teamIdentifier,
    "organizationName" => $organizationName,
    "description" => "Entrada para " . $ticket['event_title'],
    "logoText" => "TICKETAPP",
    "foregroundColor" => "rgb(255, 255, 255)",
    "backgroundColor" => "rgb(10, 14, 20)", // Color oscuro del tema
    "labelColor" => "rgb(163, 230, 53)", // Color Lime del tema
    "eventTicket" => [
        "primaryFields" => [
            [
                "key" => "event",
                "label" => "EVENTO",
                "value" => $ticket['event_title']
            ]
        ],
        "secondaryFields" => [
            [
                "key" => "location",
                "label" => "UBICACIÓN",
                "value" => $ticket['location']
            ],
            [
                "key" => "date",
                "label" => "FECHA",
                "value" => date('d/m/Y H:i', strtotime($ticket['date_event']))
            ]
        ],
        "auxiliaryFields" => [
            [
                "key" => "attendee",
                "label" => "ASISTENTE",
                "value" => $ticket['attendee_name'] . ' ' . $ticket['attendee_surname']
            ],
            [
                "key" => "type",
                "label" => "TIPO",
                "value" => $ticket['ticket_type_name'] ?? 'General'
            ]
        ],
        "barcode" => [
            "format" => "PKBarcodeFormatQR",
            "message" => $ticket['access_token'] ?? ('TICKET-' . $ticket['id']),
            "messageEncoding" => "iso-8859-1",
            "altText" => $ticket['access_token'] ?? ('ID: ' . $ticket['id'])
        ]
    ]
];

// Crear estructura de archivos temporal
$tempDir = sys_get_temp_dir() . '/' . uniqid('pass_');
mkdir($tempDir);

// 1. Guardar pass.json
file_put_contents($tempDir . '/pass.json', json_encode($passData));

// 2. Copiar imágenes (deberían existir en tu servidor)
// Si no existen, el pase funcionará pero se verá feo. Lo ideal es tener icon.png y icon@2x.png
$assetsPath = '../assets/img/wallet/'; // Asegúrate de crear esta carpeta con icon.png y logo.png
if (file_exists($assetsPath . 'icon.png')) copy($assetsPath . 'icon.png', $tempDir . '/icon.png');
if (file_exists($assetsPath . 'logo.png')) copy($assetsPath . 'logo.png', $tempDir . '/logo.png');

// 3. Generar manifest.json (Hashes SHA1)
$manifest = [];
$files = scandir($tempDir);
foreach ($files as $file) {
    if ($file == '.' || $file == '..') continue;
    $manifest[$file] = sha1_file($tempDir . '/' . $file);
}
file_put_contents($tempDir . '/manifest.json', json_encode($manifest));

// 4. Firmar el manifiesto (ESTO REQUIERE CERTIFICADOS REALES)
// Si no tienes certificados, esta parte fallará o el pase será inválido.
// Descomenta y configura las rutas cuando tengas los certificados .p12 y .pem
/*
$certPath = '../certs/certificate.p12';
$wwdrPath = '../certs/wwdr.pem';
$certPassword = 'tu_contraseña';

if (file_exists($certPath) && file_exists($wwdrPath)) {
    openssl_pkcs7_sign(
        $tempDir . '/manifest.json',
        $tempDir . '/signature',
        'file://' . $certPath,
        array('file://' . $certPath, $certPassword),
        array(),
        PKCS7_BINARY | PKCS7_DETACHED,
        $wwdrPath
    );
} else {
    // Generar firma dummy para pruebas (el iPhone lo rechazará, pero descarga el archivo)
    file_put_contents($tempDir . '/signature', 'dummy_signature');
}
*/
// Firma dummy temporal
file_put_contents($tempDir . '/signature', str_repeat('0', 128));

// 5. Comprimir en .pkpass (ZIP)
$zipFile = sys_get_temp_dir() . '/ticket_' . $ticket['id'] . '.pkpass';
$zip = new ZipArchive();
$zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$filesToZip = scandir($tempDir);
foreach ($filesToZip as $file) {
    if ($file == '.' || $file == '..') continue;
    $zip->addFile($tempDir . '/' . $file, $file);
}
$zip->close();

// 6. Enviar al navegador
header('Pragma: no-cache');
header('Content-Type: application/vnd.apple.pkpass');
header('Content-Length: ' . filesize($zipFile));
header('Content-Disposition: attachment; filename="ticket_' . $ticket['id'] . '.pkpass"');
readfile($zipFile);

// Limpieza
array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);
unlink($zipFile);
?>