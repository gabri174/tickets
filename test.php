<?php
require_once 'includes/config/config.php';
try {
    $stmt = $pdo->prepare("INSERT INTO admins (username, password, email, role) VALUES ('testuser', 'pass', 'test@test.com', 'organizer')");
    $stmt->execute();
    echo "Success";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
