<?php
session_start();

// Destroy the session
session_unset();
session_destroy();

// Redirect back to client or login
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.html';
header('Location: ' . $redirect);
exit();
?>