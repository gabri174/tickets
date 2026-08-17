<?php
require_once '../../includes/config/config.php';
$token = trim((string)($_GET['order'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pago cancelado</title></head>
<body style="font-family:system-ui;max-width:680px;margin:80px auto;padding:24px;text-align:center">
<h1>Pago cancelado</h1>
<p>No se ha realizado ningún cargo.</p>
<p>Puedes volver al evento e intentarlo de nuevo.</p>
</body>
</html>
