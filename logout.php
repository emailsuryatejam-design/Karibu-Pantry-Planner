<?php
session_start();
$kitchenCode = $_SESSION['user']['kitchen_code'] ?? null;
session_destroy();

if ($kitchenCode) {
    header('Location: /' . $kitchenCode . '/');
} else {
    header('Location: /index.php');
}
exit;
