<?php
session_start();
// Página de éxito de compra

// Verificar si hay datos de compra
if (!isset($_SESSION['purchase_success'])) {
    header('Location: index.php');
    exit();
}

$purchase = $_SESSION['purchase_success'];
$emailError = $_SESSION['email_error'] ?? null;
$debugMode = isset($_GET['debug']);

if ($debugMode) {
    $_SESSION['debug_email'] = true;
}

// Limpiar sesión después de mostrar
if (!$debugMode) {
    unset($_SESSION['purchase_success']);
    unset($_SESSION['email_error']);
    unset($_SESSION['smtp_log']);
    unset($_SESSION['debug_email']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Compra Exitosa! - Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-gray-light: #d9d9d9;
            --color-gray-dark: #363c40;
            --color-gray-medium: #babebf;
            --color-gray-muted: #848b8c;
            --color-black: #202426;
        }
        
        body { background-color: var(--color-gray-light); }
        .bg-primary { background-color: var(--color-gray-dark); }
        .text-primary { color: var(--color-gray-dark); }
        .btn-primary { background-color: var(--color-gray-dark); }
        .btn-primary:hover { background-color: var(--color-black); }
        
        .success-animation {
            animation: checkmark 0.5s ease-in-out;
        }
        
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="bg-primary text-white shadow-lg">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                    <h1 class="text-2xl font-bold">Tickets</h1>
                </div>
                <a href="index.php" class="hover:text-gray-300 transition">
                    <i class="fas fa-home mr-2"></i>Inicio
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-12">
        <div class="max-w-3xl mx-auto">
            <!-- Success Message -->
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <div class="success-animation mb-6">
                    <div class="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-check text-white text-4xl"></i>
                    </div>
                </div>
                
                <h2 class="text-3xl font-bold mb-4 text-primary">¡Compra Exitosa!</h2>
                <p class="text-lg text-gray-600 mb-8">
                    Tus tickets para <strong><?php echo htmlspecialchars($purchase['event_title']); ?></strong> 
                    han sido generados y enviados a tu email.
                </p>

                <?php if ($emailError): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-8 text-left" role="alert">
                    <p class="font-bold"><i class="fas fa-exclamation-circle mr-2"></i>Atención</p>
                    <p><?php echo htmlspecialchars($emailError); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Purchase Details -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                    <h3 class="text-xl font-semibold mb-4 text-primary">Detalles de la Compra</h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Evento:</span>
                            <span class="font-semibold"><?php echo htmlspecialchars($purchase['event_title']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tickets comprados:</span>
                            <span class="font-semibold"><?php echo count($purchase['tickets']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total pagado:</span>
                            <span class="font-semibold text-xl"><?php echo '$' . number_format($purchase['total_price'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email de envío:</span>
                            <span class="font-semibold"><?php echo htmlspecialchars($purchase['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Ticket Codes & QRs -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8 text-left">
                    <h3 class="text-xl font-semibold mb-4 text-primary italic"><i class="fas fa-qrcode mr-2"></i>Tus Tickets de Acceso</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Presenta estos códigos QR en la entrada del evento. Puedes compartirlos individualmente por WhatsApp.
                    </p>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($purchase['tickets'] as $ticket): ?>
                            <div class="bg-white border rounded-xl p-4 shadow-sm flex flex-col md:flex-row items-center gap-6">
                                <!-- QR Visual -->
                                <div class="bg-gray-50 p-3 rounded-lg border">
                                    <?php 
                                    $qrWebPath = SITE_URL . '/qrcodes/' . basename($ticket['qr_path']);
                                    ?>
                                    <img src="<?php echo $qrWebPath; ?>" alt="QR" class="w-32 h-32">
                                </div>
                                
                                <div class="flex-1 text-center md:text-left">
                                    <div class="text-xs text-gray-500 uppercase font-bold mb-1">Código de Acceso</div>
                                    <div class="text-xl font-mono font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($ticket['code']); ?></div>
                                    
                                    <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                                        <a href="ticket.php?code=<?php echo $ticket['code']; ?>" target="_blank" class="text-xs bg-gray-100 px-3 py-2 rounded-full hover:bg-gray-200 transition">
                                            <i class="fas fa-external-link-alt mr-1"></i>Ver Ticket
                                        </a>
                                        <button onclick="shareIndividualTicket('<?php echo $ticket['code']; ?>', '<?php echo htmlspecialchars($purchase['event_title']); ?>')" class="text-xs bg-green-100 text-green-700 px-3 py-2 rounded-full hover:bg-green-200 transition">
                                            <i class="fab fa-whatsapp mr-1"></i>Enviar este ticket
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="index.php" class="btn-primary text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition text-center">
                        <i class="fas fa-arrow-left mr-2"></i>Volver a Eventos
                    </a>
                    
                    <button onclick="shareOnWhatsApp()" class="bg-green-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition text-center">
                        <i class="fab fa-whatsapp mr-2"></i>Compartir por WhatsApp
                    </button>
                </div>
                
                <!-- Share Section -->
                <div class="mt-8 p-4 bg-gray-100 rounded-lg">
                    <p class="text-sm text-gray-600 mb-2">Comparte tu experiencia:</p>
                    <div class="flex justify-center space-x-4">
                        <a href="#" onclick="shareOnFacebook(); return false;" class="text-blue-600 hover:text-blue-800">
                            <i class="fab fa-facebook text-2xl"></i>
                        </a>
                        <a href="#" onclick="shareOnTwitter(); return false;" class="text-blue-400 hover:text-blue-600">
                            <i class="fab fa-twitter text-2xl"></i>
                        </a>
                        <a href="#" onclick="shareOnInstagram(); return false;" class="text-pink-600 hover:text-pink-800">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                    </div>
                </div>
            </div>
            
                <!-- Debug Info (Only if requested) -->
                <?php if ($debugMode): ?>
                <div class="mt-8 p-4 bg-black text-green-400 text-left rounded-lg overflow-x-auto font-mono text-xs">
                    <p class="font-bold mb-2 border-b border-green-800 pb-1">DEBUG INFO:</p>
                    <p>SITE_URL: <?php echo SITE_URL; ?></p>
                    <p>ROOT_PATH: <?php echo ROOT_PATH; ?></p>
                    <p>QR Example absolute: <?php echo $purchase['tickets'][0]['qr_path'] ?? 'N/A'; ?></p>
                    <p>QR Example Web URL: <?php echo $qrWebPath ?? 'N/A'; ?></p>
                    <?php 
                    $absoluteQr = $purchase['tickets'][0]['qr_path'] ?? '';
                    if ($absoluteQr) {
                        echo "<p>File exists on server: " . (file_exists($absoluteQr) ? 'YES' : 'NO') . "</p>";
                        echo "<p>File size: " . (file_exists($absoluteQr) ? filesize($absoluteQr) . " bytes" : 'N/A') . "</p>";
                        echo "<p>Permissions: " . (file_exists($absoluteQr) ? substr(sprintf('%o', fileperms($absoluteQr)), -4) : 'N/A') . "</p>";
                    }
                    ?>
                    <p class="mt-4 font-bold border-b border-green-800">SMTP LOG:</p>
                    <pre class="whitespace-pre-wrap"><?php echo $_SESSION['smtp_log'] ?? 'No log recorded - reload with ?debug=1 to see new attempts'; ?></pre>
                </div>
                <?php endif; ?>
            </div>
    </main>

    <script>
        // Función para compartir por WhatsApp (General)
        function shareOnWhatsApp() {
            const eventTitle = <?php echo json_encode($purchase['event_title']); ?>;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const message = `🎉 ¡Hola! Aquí tienes tus entradas para "${eventTitle}"!\n\n` +
                          `Puedes ver tus tickets aquí:\n<?php echo SITE_URL; ?>/index.php\n\n` +
                          `¡Gracias por tu compra! 🎪`;
            
            const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }

        // Función para compartir ticket INDIVIDUAL
        function shareIndividualTicket(code, eventTitle) {
            const ticketUrl = `<?php echo SITE_URL; ?>/ticket.php?code=${code}`;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const message = `🎫 Aquí tienes tu entrada para "${eventTitle}"\n\n` +
                          `Código: ${code}\n` +
                          `Presenta el QR al llegar:\n${ticketUrl}`;
            
            const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        // Función para compartir en Facebook
        function shareOnFacebook() {
            const url = window.location.href;
            const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            window.open(facebookUrl, '_blank', 'width=600,height=400');
        }
        
        // Función para compartir en Twitter
        function shareOnTwitter() {
            const text = `¡Acabo de comprar tickets para "${<?php echo json_encode($purchase['event_title']); ?>}"! 🎉`;
            const url = window.location.href;
            const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
            window.open(twitterUrl, '_blank', 'width=600,height=400');
        }
        
        // Función para compartir en Instagram (redirige a la app)
        function shareOnInstagram() {
            // Instagram no permite compartir directamente vía web, así que abrimos la app
            window.open('https://www.instagram.com/', '_blank');
        }
        
        // Copiar códigos al portapapeles
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar notificación temporal
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = '¡Copiado al portapapeles!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 2000);
            });
        }
    </script>
</body>
</html>
