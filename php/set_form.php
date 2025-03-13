<?php
session_start();

if (isset($_POST['form'])) {
    $_SESSION['show_form'] = $_POST['form'];
    echo 'success';
} else {
    http_response_code(400);
    echo 'error: form parameter required';
}
?>
