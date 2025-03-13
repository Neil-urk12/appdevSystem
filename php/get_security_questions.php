<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_GET['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email is required']);
    exit();
}

try {
    $email = $conn->real_escape_string($_GET['email']);
    
    $sql = "SELECT security_questions FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $questions = json_decode($user['security_questions'], true);
        echo json_encode($questions);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Email not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
