<?php
session_start();

// Verificar si hay datos de compra
if (!isset($_SESSION['purchase_success'])) {
    header('Location: index.php');
    exit();
}

$purchase = $_SESSION['purchase_success'];

// Limpiar sesión después de mostrar
unset($_SESSION['purchase_success']);
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
                
                <!-- Ticket Codes -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8 text-left">
                    <h3 class="text-xl font-semibold mb-4 text-primary">Tus Códigos de Ticket</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Guarda estos códigos. Los necesitarás para ingresar al evento.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($purchase['tickets'] as $ticket): ?>
                            <div class="bg-white border border-gray-200 rounded p-3">
                                <code class="text-sm font-mono font-semibold"><?php echo htmlspecialchars($ticket['code']); ?></code>
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
            
            <!-- Important Notice -->
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-semibold text-yellow-800 mb-1">Importante:</h4>
                        <p class="text-sm text-yellow-700">
                            Recuerda llegar al evento con al menos 30 minutos de antelación y presentar 
                            tus códigos de ticket en formato digital o impreso.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Función para compartir por WhatsApp
        function shareOnWhatsApp() {
            const message = `¡Acabo de comprar ${<?php echo count($purchase['tickets']); ?>} tickets para "${<?php echo json_encode($purchase['event_title']); ?>}"! 🎉\n\n` +
                          `Códigos: ${<?php echo json_encode(implode(', ', array_column($purchase['tickets'], 'code'))); ?>}\n\n` +
                          `¡Nos vemos allá! 🎪`;
            
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
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
