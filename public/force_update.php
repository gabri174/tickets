<?php
/**
 * SCRIPT DE EMERGENCIA: Limpieza de Git y Forzado de Actualización
 * Sube este archivo a la raíz de tu sitio y visítalo en el navegador.
 */
session_start();
header('Content-Type: text/plain');

echo "=== INICIANDO REPARACIÓN DE DESPLIEGUE (v2.2) ===\n\n";

// 1. Limpiar conflicto en config.php
echo "1. Descartando cambios locales en config.php (los tokens se aplicarán desde GitHub)...\n";
$out1 = [];
exec("git checkout includes/config/config.php 2>&1", $out1);
echo implode("\n", $out1) . "\n\n";

// 2. Ejecutar Pull
echo "2. Bajando actualizaciones de v2.2 (Idempotencia + Fallback Mail)...\n";
$out2 = [];
exec("git pull origin main 2>&1", $out2);
echo implode("\n", $out2) . "\n\n";

// 3. Verificar estado
echo "3. Estado actual de Git:\n";
$out3 = [];
exec("git status 2>&1", $out3);
echo implode("\n", $out3) . "\n\n";

echo "=== PROCESO FINALIZADO ===\n";
echo "Si ves 'Already up to date' o 'Updating...', los cambios ya están activos.\n";
echo "Ya puedes borrar este archivo (force_update.php) y probar una compra.\n";
?>
