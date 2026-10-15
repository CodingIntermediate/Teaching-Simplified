<?php
require_once '../../config.php';
Auth::requireRole('student');

$db = db();
$user = Auth::getCurrentUser();

// Get syllabus ID from URL or get the most recent one
$syllabus_id = $_GET['syllabus_id'] ?? 0;
$syllabus = null;

// If no syllabus_id provided, get the most recent syllabus
if (!$syllabus_id) {
    $latest = $db->selectOne("
        SELECT s.id, sub.name as subject_name, sub.code as subject_code, s.extracted_text
        FROM syllabus s
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.student_id = ? AND s.status = 'completed'
        ORDER BY s.uploaded_at DESC
        LIMIT 1
    ", [$user['id']]);
    
    if ($latest) {
        $syllabus_id = $latest['id'];
        $syllabus = $latest;
    }
} else {
    $syllabus = getSyllabusById($syllabus_id);
    
    if ($syllabus && $syllabus['student_id'] != $user['id']) {
        $syllabus = null;
    }
}

// Get available syllabi for dropdown
$available_syllabi = $db->select("
    SELECT s.id, sub.code, sub.name, sub.id as subject_id
    FROM syllabus s
    JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.student_id = ? AND s.status = 'completed'
    ORDER BY s.uploaded_at DESC
", [$user['id']]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Study Assistant - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .chat-container {
            height: calc(100vh - 150px);
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
            scroll-behavior: smooth;
        }
        .message {
            margin-bottom: 20px;
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        .message.user {
            justify-content: flex-end;
        }
        .message.bot {
            justify-content: flex-start;
        }
        .message-content {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 20px;
            word-wrap: break-word;
            line-height: 1.5;
        }
        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.bot .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            margin-left: 12px;
            flex-shrink: 0;
        }
        .bot-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .user-avatar {
            background: #28a745;
            color: white;
            order: 2;
            margin-left: 12px;
            margin-right: 0;
        }
        .chat-input-area {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
        }
        .chat-input {
            border-radius: 25px;
            padding: 12px 20px;
            border: 1px solid #ddd;
            resize: none;
            font-size: 14px;
        }
        .chat-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.1);
        }
        .send-btn {
            border-radius: 25px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .send-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .typing-indicator {
            display: flex;
            padding: 10px 15px;
            background: white;
            border-radius: 20px;
            width: fit-content;
            gap: 4px;
        }
        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            animation: typingAnimation 1.4s infinite ease-in-out;
        }
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingAnimation {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-8px); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .cursor-blink {
            display: inline-block;
            width: 2px;
            height: 18px;
            background-color: #333;
            margin-left: 2px;
            animation: blink 1s infinite;
            vertical-align: middle;
        }
        .syllabus-info {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
        }
        .no-syllabus-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
        }
        .quick-questions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-question-btn {
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .quick-question-btn:hover:not(:disabled) {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        .stop-btn {
            background: #dc3545 !important;
        }
        .stop-btn:hover {
            background: #c82333 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-robot"></i> AI Study Assistant
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                </span>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-3">
        <div class="row">
            <div class="col-md-12">
                <div class="chat-container">
                    <div class="chat-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-robot fa-2x me-2"></i>
                                <strong>AI Study Assistant</strong>
                                <small class="ms-2 opacity-75">Powered by AI</small>
                            </div>
                            <?php if (!empty($available_syllabi)): ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-book"></i> 
                                    <?php 
                                    if ($syllabus) {
                                        echo htmlspecialchars($syllabus['subject_code']);
                                    } else {
                                        echo "Select Syllabus";
                                    }
                                    ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($available_syllabi as $sub): ?>
                                    <li><a class="dropdown-item" href="?syllabus_id=<?php echo $sub['id']; ?>">
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($sub['code'] . ' - ' . $sub['name']); ?>
                                    </a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if ($syllabus): ?>
                        <div class="syllabus-info">
                            <i class="fas fa-info-circle text-primary"></i>
                            <strong>Context:</strong> I'm using your syllabus for 
                            <strong><?php echo htmlspecialchars($syllabus['subject_code'] . ' - ' . $syllabus['subject_name']); ?></strong> 
                            to provide relevant answers. You can ask me questions about this subject!
                        </div>
                        <?php else: ?>
                        <div class="no-syllabus-warning">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <strong>No syllabus found!</strong> Please upload a syllabus first, then I can help you with specific course content.
                            <div class="mt-2">
                                <a href="upload_syllabus.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-upload"></i> Upload Syllabus
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($syllabus): ?>
                        <div class="quick-questions" id="quickQuestions">
                            <button class="quick-question-btn" onclick="askQuestion('What is the main topic of this course?')">
                                🤔 What is this course about?
                            </button>
                            <button class="quick-question-btn" onclick="askQuestion('Explain the key concepts of this subject')">
                                📚 Explain key concepts
                            </button>
                            <button class="quick-question-btn" onclick="askQuestion('What are the important topics I should focus on?')">
                                🎯 Important topics to focus
                            </button>
                            <button class="quick-question-btn" onclick="askQuestion('Give me a study plan for this course')">
                                📅 Suggest a study plan
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="message bot">
                            <div class="message-avatar bot-avatar">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="message-content">
                                Hello! I'm your AI Study Assistant. I can help you understand your course material, answer questions, and provide study tips. 
                                <?php if ($syllabus): ?>
                                I've analyzed your syllabus for <strong><?php echo htmlspecialchars($syllabus['subject_code']); ?></strong>. Ask me anything about this course!
                                <?php else: ?>
                                Please upload a syllabus first by going to your dashboard and clicking "Upload Syllabus".
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-input-area">
                        <div class="row g-2">
                            <div class="col-10">
                                <textarea class="form-control chat-input" id="messageInput" rows="1" placeholder="Type your question here..." <?php echo !$syllabus ? 'disabled' : ''; ?> onkeypress="handleKeyPress(event)"></textarea>
                            </div>
                            <div class="col-2">
                                <button class="btn btn-primary send-btn w-100" id="sendBtn" onclick="sendMessage()" <?php echo !$syllabus ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </div>
                        <?php if (!$syllabus): ?>
                        <div class="text-muted text-center mt-2 small">
                            <i class="fas fa-info-circle"></i> Please upload a syllabus to start chatting about your course.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let chatHistory = [];
        let isTyping = false;
        let currentStreamInterval = null;
        let syllabusId = <?php echo $syllabus_id ?: 0; ?>;
        let hasSyllabus = <?php echo $syllabus ? 'true' : 'false'; ?>;
        
        // Load chat history from localStorage
        function loadChatHistory() {
            if (!hasSyllabus) return;
            
            const saved = localStorage.getItem('chat_history_' + syllabusId);
            if (saved) {
                try {
                    const history = JSON.parse(saved);
                    if (history.length > 0) {
                        const chatMessages = document.getElementById('chatMessages');
                        const defaultMessages = chatMessages.querySelectorAll('.message');
                        defaultMessages.forEach(msg => {
                            if (msg.querySelector('.message-content')?.innerText.includes("Hello! I'm your AI Study Assistant")) {
                                msg.remove();
                            }
                        });
                        
                        history.forEach(msg => {
                            addMessageToChat(msg.role, msg.content, false);
                        });
                        chatHistory = history;
                        scrollToBottom();
                    }
                } catch(e) {
                    console.error('Error loading history:', e);
                }
            }
        }
        
        // Save chat history
        function saveChatHistory() {
            if (!hasSyllabus) return;
            localStorage.setItem('chat_history_' + syllabusId, JSON.stringify(chatHistory));
        }
        
        // Add message to chat (without typing animation for user messages)
        function addMessageToChat(role, content, save = true) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            
            const avatar = document.createElement('div');
            avatar.className = `message-avatar ${role === 'user' ? 'user-avatar' : 'bot-avatar'}`;
            avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = formatMessage(content);
            
            messageDiv.appendChild(avatar);
            messageDiv.appendChild(contentDiv);
            
            if (role === 'user') {
                messageDiv.style.justifyContent = 'flex-end';
            }
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
            
            if (save) {
                chatHistory.push({ role: role, content: content });
                saveChatHistory();
            }
            
            return contentDiv;
        }
        
        // Add message with typing animation (ChatGPT style)
        async function addTypingMessage(content) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message bot';
            
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar bot-avatar';
            avatar.innerHTML = '<i class="fas fa-robot"></i>';
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = '';
            
            messageDiv.appendChild(avatar);
            messageDiv.appendChild(contentDiv);
            chatMessages.appendChild(messageDiv);
            
            // Type out the message character by character
            let i = 0;
            const chars = content.split('');
            
            return new Promise((resolve) => {
                function typeCharacter() {
                    if (i < chars.length) {
                        contentDiv.innerHTML = formatMessage(content.substring(0, i + 1)) + '<span class="cursor-blink"></span>';
                        i++;
                        scrollToBottom();
                        setTimeout(typeCharacter, 15 + Math.random() * 10);
                    } else {
                        contentDiv.innerHTML = formatMessage(content);
                        resolve();
                    }
                }
                typeCharacter();
            });
        }
        
        // Format message
        function formatMessage(text) {
            if (!text) return '';
            text = text.replace(/\n/g, '<br>');
            text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color: #007bff;">$1</a>');
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
            return text;
        }
        
        // Show typing indicator
        function showTypingIndicator() {
            const chatMessages = document.getElementById('chatMessages');
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message bot';
            typingDiv.id = 'typingIndicator';
            
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar bot-avatar';
            avatar.innerHTML = '<i class="fas fa-robot"></i>';
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = `
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;
            
            typingDiv.appendChild(avatar);
            typingDiv.appendChild(contentDiv);
            chatMessages.appendChild(typingDiv);
            scrollToBottom();
        }
        
        // Remove typing indicator
        function removeTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        // Scroll to bottom smoothly
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Send message to API
        async function sendMessage() {
            if (!hasSyllabus) {
                alert('Please upload a syllabus first to use the chatbot.');
                return;
            }
            
            if (isTyping) {
                return;
            }
            
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Disable input while processing
            isTyping = true;
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';
            
            // Add user message
            addMessageToChat('user', message);
            input.value = '';
            input.style.height = 'auto';
            
            // Show typing indicator
            showTypingIndicator();
            
            try {
                // Prepare context from syllabus
                let contextMessage = message;
                <?php if ($syllabus && !empty($syllabus['extracted_text'])): ?>
                const syllabusContext = `Based on the following syllabus content, answer this question: ${message}\n\nSyllabus content: <?php echo addslashes(substr($syllabus['extracted_text'], 0, 2000)); ?>`;
                contextMessage = syllabusContext;
                <?php endif; ?>
                
                const response = await fetch('chatbot_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message: contextMessage,
                        history: chatHistory.filter(h => h.role !== 'system')
                    })
                });
                
                const data = await response.json();
                removeTypingIndicator();
                
                // Add bot response with typing animation
                if (data.response) {
                    await addTypingMessage(data.response);
                    chatHistory.push({ role: 'bot', content: data.response });
                    saveChatHistory();
                } else {
                    await addTypingMessage('Sorry, I could not generate a response. Please try again.');
                }
                
            } catch (error) {
                console.error('Error:', error);
                removeTypingIndicator();
                await addTypingMessage('Sorry, I encountered an error. Please check your connection and try again.');
            } finally {
                isTyping = false;
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                input.focus();
            }
        }
        
        // Handle Enter key
        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey && hasSyllabus && !isTyping) {
                event.preventDefault();
                sendMessage();
            }
        }
        
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }
        
        // Ask quick question
        function askQuestion(question) {
            if (!hasSyllabus) {
                alert('Please upload a syllabus first to use the chatbot.');
                return;
            }
            if (isTyping) return;
            document.getElementById('messageInput').value = question;
            sendMessage();
        }
        
        // Load chat history on page load
        if (hasSyllabus) {
            loadChatHistory();
        }
        
        // Focus on input on page load
        if (messageInput && hasSyllabus) {
            setTimeout(() => messageInput.focus(), 500);
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>