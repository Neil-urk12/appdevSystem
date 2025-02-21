<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

if (isset($_POST["logout"])) {
    session_destroy();
    header("Location: ../authpage.php");
    exit();
}
?>
