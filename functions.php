<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'signup') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password]);
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        echo json_encode(['success' => true, 'user_id' => $user_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} elseif ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['success' => true, 'user_id' => $user['id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'get_contacts') {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ?");
    $stmt->execute([$user_id]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'contacts' => $contacts]);
} elseif ($action === 'get_chats') {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.id, u.username, m.content AS last_message, m.created_at
        FROM chats c
        JOIN users u ON (u.id = c.user1_id OR u.id = c.user2_id) AND u.id != ?
        LEFT JOIN messages m ON m.chat_id = c.id
        WHERE c.user1_id = ? OR c.user2_id = ?
        GROUP BY c.id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'chats' => $chats]);
} elseif ($action === 'get_messages') {
    $chat_id = $_POST['chat_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id || !$chat_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at, m.sender_id, m.status, u.username
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.chat_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$chat_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update message status to 'read'
    $stmt = $pdo->prepare("UPDATE messages SET status = 'read' WHERE chat_id = ? AND sender_id != ? AND status != 'read'");
    $stmt->execute([$chat_id, $user_id]);

    echo json_encode(['success' => true, 'messages' => $messages]);
} elseif ($action === 'send_message') {
    $chat_id = $_POST['chat_id'] ?? 0;
    $content = $_POST['content'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;

    if (!$user_id || !$chat_id || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, content, status) VALUES (?, ?, ?, 'sent')");
    $stmt->execute([$chat_id, $user_id, $content]);
    echo json_encode(['success' => true]);
} elseif ($action === 'start_chat') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $contact_id = $_POST['contact_id'] ?? 0;

    if (!$user_id || !$contact_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // Check if chat already exists
    $stmt = $pdo->prepare("SELECT id FROM chats WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($chat) {
        echo json_encode(['success' => true, 'chat_id' => $chat['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $contact_id]);
        $chat_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'chat_id' => $chat_id]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
