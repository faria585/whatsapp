<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Clone - Chat</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #e5ddd5;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 30%;
            background: #fff;
            border-right: 1px solid #ddd;
            overflow-y: auto;
        }
        .chat-area {
            width: 70%;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            background: #075E54;
            color: #fff;
            padding: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #075E54; /* Solid green background */
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 8px;
            max-width: 70%;
            position: relative;
            word-wrap: break-word;
        }
        .message.sent {
            background: #fff; /* White for sent messages */
            margin-left: auto;
        }
        .message.received {
            background: #000; /* Black for received messages */
            color: #fff;
        }
        .message .time {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }
        .message .status {
            font-size: 10px;
            color: #25D366;
        }
        .chat-input {
            background: #f0f0f0;
            padding: 10px;
            display: flex;
            align-items: center;
        }
        .chat-input input {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 20px;
            margin-right: 10px;
        }
        .chat-input button {
            background: #25D366;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
        }
        .chat-item {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
        }
        .chat-item:hover {
            background: #f0f0f0;
        }
        .contact-list {
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .contact-list select {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
        }
        @media (max-width: 600px) {
            .sidebar {
                width: 100%;
                display: none;
            }
            .chat-area {
                width: 100%;
            }
            .sidebar.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="contact-list">
            <select id="contacts" onchange="startChat()">
                <option value="">Select a contact</option>
            </select>
        </div>
        <div id="chat-list"></div>
    </div>
    <div class="chat-area">
        <div class="chat-header" id="chat-header">Select a chat</div>
        <div class="chat-messages" id="chat-messages"></div>
        <div class="chat-input">
            <input type="text" id="message-input" placeholder="Type a message">
            <button onclick="sendMessage()">➤</button>
        </div>
    </div>
    <script>
        let currentChatId = null;

        function redirectToLogin() {
            window.location.href = 'index.php';
        }

        async function loadContacts() {
            const response = await fetch('functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_contacts'
            });
            const result = await response.json();
            if (result.success) {
                const contacts = result.contacts;
                const select = document.getElementById('contacts');
                contacts.forEach(contact => {
                    const option = document.createElement('option');
                    option.value = contact.id;
                    option.textContent = contact.username;
                    select.appendChild(option);
                });
            }
        }

        async function loadChats() {
            const response = await fetch('functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_chats'
            });
            const result = await response.json();
            if (result.success) {
                const chats = result.chats;
                const chatList = document.getElementById('chat-list');
                chatList.innerHTML = '';
                chats.forEach(chat => {
                    const div = document.createElement('div');
                    div.className = 'chat-item';
                    div.onclick = () => loadMessages(chat.id, chat.username);
                    div.innerHTML = `
                        <strong>${chat.username}</strong>
                        <p>${chat.last_message || ''}</p>
                        <small>${new Date(chat.created_at).toLocaleTimeString()}</small>
                    `;
                    chatList.appendChild(div);
                });
            }
        }

        async function startChat() {
            const contactId = document.getElementById('contacts').value;
            if (!contactId) return;
            const response = await fetch('functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=start_chat&contact_id=${contactId}`
            });
            const result = await response.json();
            if (result.success) {
                loadChats();
                loadMessages(result.chat_id, document.querySelector(`option[value="${contactId}"]`).textContent);
            }
        }

        async function loadMessages(chatId, username) {
            currentChatId = chatId;
            document.getElementById('chat-header').textContent = username;
            const response = await fetch('functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_messages&chat_id=${chatId}`
            });
            const result = await response.json();
            if (result.success) {
                const messages = result.messages;
                const chatMessages = document.getElementById('chat-messages');
                chatMessages.innerHTML = '';
                messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = `message ${msg.sender_id == localStorage.getItem('user_id') ? 'sent' : 'received'}`;
                    div.innerHTML = `
                        <p>${msg.content}</p>
                        <div class="time">${new Date(msg.created_at).toLocaleTimeString()}</div>
                        <div class="status">${msg.status}</div>
                    `;
                    chatMessages.appendChild(div);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        async function sendMessage() {
            if (!currentChatId) return;
            const content = document.getElementById('message-input').value;
            if (!content.trim()) return;
            const response = await fetch('functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_message&chat_id=${currentChatId}&content=${encodeURIComponent(content)}`
            });
            const result = await response.json();
            if (result.success) {
                document.getElementById('message-input').value = '';
                loadMessages(currentChatId, document.getElementById('chat-header').textContent);
                loadChats();
            }
        }

        // Poll for new messages every 2 seconds
        setInterval(() => {
            if (currentChatId) {
                loadMessages(currentChatId, document.getElementById('chat-header').textContent);
            }
            loadChats();
        }, 2000);

        // Initial load
        loadContacts();
        loadChats();
    </script>
</body>
</html>
