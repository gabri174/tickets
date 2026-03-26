<?php
/**
 * load_test.php - Prueba de carga masiva para la arquitectura SRE
 * 
 * Este script simula N peticiones concurrentes a buy.php para probar:
 * 1. Redis Cache 
 * 2. Semáforo de Inventario (Atomic Lock)
 * 3. Cola QStash
 * 
 * Uso: php load_test.php [concurrency] [event_id]
 */

$concurrency = isset($argv[1]) ? (int)$argv[1] : 50;
$eventId     = isset($argv[2]) ? (int)$argv[2] : 8; // Evento de prueba en tu base de datos

$url = "https://ensupresencia.eu/buy.php?id={$eventId}";

echo "🚀 Iniciando prueba de carga SRE...\n";
echo "URL destino: {$url}\n";
echo "Concurrencia: {$concurrency} peticiones paralelas\n";
echo "--------------------------------------------------------\n";

$mh = curl_multi_init();
$chArray = [];

// Payload de ejemplo para comprar 1 ticket
$postData = http_build_query([
    'quantity' => 1,
    'ticket_type_id' => 1, // Asumiendo ID 1, buy.php lo validará
    'phone' => '123456789',
    'zip_code' => '28001',
    'attendees' => [
        [
            'name' => 'Load',
            'surname' => 'Tester',
            'email' => 'test@example.com'
        ]
    ]
]);

// Crear las peticiones concurrentes
for ($i = 0; $i < $concurrency; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // Para no esperar todo el HTML
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // No seguir a success.php
    
    curl_multi_add_handle($mh, $ch);
    $chArray[$i] = $ch;
}

$startTime = microtime(true);

// Ejecutar todas las peticiones en paralelo
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$endTime = microtime(true);

// Analizar resultados
$results = [
    '200_OK_success' => 0,
    '302_Redirect' => 0,
    '400_Bad_Request' => 0,
    '500_Fatal_Error' => 0,
    'timeout' => 0,
    'locked_out' => 0 // Sin stock
];

foreach ($chArray as $i => $ch) {
    $info = curl_getinfo($ch);
    $code = $info['http_code'];
    $response = curl_multi_getcontent($ch);
    
    if ($code == 302) {
        $results['302_Redirect']++; // Significa que la compra pasó a success.php (éxito)
    } elseif ($code == 200) {
        // Puede ser que la página cargó con errores de validación o inventario
        if (strpos($response, 'Sin stock disponible') !== false || strpos($response, 'Sin entradas') !== false) {
            $results['locked_out']++;
        } else {
            $results['200_OK_success']++;
        }
    } elseif ($code == 500) {
        $results['500_Fatal_Error']++;
    } elseif ($code == 0) {
        $results['timeout']++;
    } else {
        $results["Otros ($code)"] = ($results["Otros ($code)"] ?? 0) + 1;
    }
    
    curl_multi_remove_handle($mh, $ch);
}

curl_multi_close($mh);

$totalTime = round($endTime - $startTime, 2);

echo "\n📊 RESULTADOS DE LA PRUEBA\n";
echo "Tiempo total: {$totalTime} segundos\n";
echo "Req/sec aprox: " . round($concurrency / $totalTime, 2) . "\n\n";

foreach ($results as $status => $count) {
    if ($count > 0 || in_array($status, ['302_Redirect', 'locked_out', '500_Fatal_Error'])) {
        echo str_pad($status, 20) . ": {$count}\n";
    }
}

echo "\nInterpretación:\n";
echo "- '302_Redirect': Compras exitosas (petición de pago o success). Si enviaste más de tu stock, esto debió detenerse rápido.\n";
echo "- 'locked_out': Peticiones que chocaron con el Semáforo de Inventario (el stock protege tu BD).\n";
echo "- '500_Fatal_Error': Si es 0, significa que MySQL aguantó gracias a Redis/QStash.\n";
echo "--------------------------------------------------------\n";
