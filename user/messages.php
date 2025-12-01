<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('student');
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    try {
        if ($_POST['action'] === 'send_message') {
            $recipient_id = intval($_POST['recipient_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (empty($recipient_id) || empty($message)) {
                $response['message'] = 'Recipient and message are required';
                sendJSONResponse($response);
                exit;
            }
            
            // Verify recipient exists and is in same school
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND approved = 1");
            $stmt->execute([$recipient_id, $user['school_id']]);
            if (!$stmt->fetch()) {
                $response['message'] = 'Invalid recipient';
                sendJSONResponse($response);
                exit;
            }
            
            // Insert message
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $recipient_id, $subject, $message]);
            
            $response['success'] = true;
            $response['message'] = 'Message sent successfully';
        }
        elseif ($_POST['action'] === 'mark_read') {
            $message_id = intval($_POST['message_id'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE messages 
                SET is_read = TRUE, read_at = NOW() 
                WHERE id = ? AND recipient_id = ?
            ");
            $stmt->execute([$message_id, $user['id']]);
            
            $response['success'] = true;
            $response['message'] = 'Message marked as read';
        }
        elseif ($_POST['action'] === 'delete_message') {
            $message_id = intval($_POST['message_id'] ?? 0);
            
            // Soft delete - mark as deleted by current user
            $stmt = $db->prepare("
                UPDATE messages 
                SET deleted_by_sender = IF(sender_id = ?, TRUE, deleted_by_sender),
                    deleted_by_recipient = IF(recipient_id = ?, TRUE, deleted_by_recipient)
                WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
            ");
            $stmt->execute([$user['id'], $user['id'], $message_id, $user['id'], $user['id']]);
            
            $response['success'] = true;
            $response['message'] = 'Message deleted';
        }
        elseif ($_POST['action'] === 'get_conversation') {
            $other_user_id = intval($_POST['user_id'] ?? 0);
            
            $stmt = $db->prepare("
                SELECT m.*, 
                       sender.name as sender_name, sender.profile_photo as sender_photo,
                       recipient.name as recipient_name, recipient.profile_photo as recipient_photo
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users recipient ON m.recipient_id = recipient.id
                WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
                  AND NOT (m.deleted_by_sender = TRUE AND m.sender_id = ?)
                  AND NOT (m.deleted_by_recipient = TRUE AND m.recipient_id = ?)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$user['id'], $other_user_id, $other_user_id, $user['id'], $user['id'], $user['id']]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $stmt = $db->prepare("
                UPDATE messages 
                SET is_read = TRUE, read_at = NOW() 
                WHERE sender_id = ? AND recipient_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$other_user_id, $user['id']]);
            
            $response['success'] = true;
            $response['messages'] = $messages;
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("Message error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get inbox view parameter
$view = $_GET['view'] ?? 'inbox';

// Get conversations (grouped messages)
$conversations = [];
$unread_count = 0;

if ($db) {
    try {
        // Get unique conversations with last message
        $stmt = $db->prepare("
            SELECT 
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id 
                    ELSE m.sender_id 
                END as other_user_id,
                u.name as other_user_name,
                u.profile_photo as other_user_photo,
                u.current_occupation,
                MAX(m.created_at) as last_message_time,
                (SELECT message FROM messages m2 
                 WHERE ((m2.sender_id = ? AND m2.recipient_id = other_user_id) 
                     OR (m2.sender_id = other_user_id AND m2.recipient_id = ?))
                 ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m3 
                 WHERE m3.sender_id = other_user_id AND m3.recipient_id = ? 
                   AND m3.is_read = FALSE AND NOT m3.deleted_by_recipient) as unread_messages
            FROM messages m
            JOIN users u ON (
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id = u.id
                    ELSE m.sender_id = u.id
                END
            )
            WHERE (m.sender_id = ? OR m.recipient_id = ?)
              AND NOT (m.deleted_by_sender = TRUE AND m.sender_id = ?)
              AND NOT (m.deleted_by_recipient = TRUE AND m.recipient_id = ?)
            GROUP BY other_user_id, u.name, u.profile_photo, u.current_occupation
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([
            $user['id'], $user['id'], $user['id'], $user['id'],
            $user['id'], $user['id'], $user['id'], $user['id'], $user['id']
        ]);
        $conversations = $stmt->fetchAll();
        
        // Get total unread count
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM messages 
            WHERE recipient_id = ? AND is_read = FALSE AND NOT deleted_by_recipient
        ");
        $stmt->execute([$user['id']]);
        $unread_count = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Error fetching conversations: " . $e->getMessage());
    }
}

// Get all users in school for compose
$school_users = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT id, name, profile_photo, current_occupation, year_group
            FROM users 
            WHERE school_id = ? AND approved = 1 AND id != ?
            ORDER BY name ASC
        ");
        $stmt->execute([$user['school_id'], $user['id']]);
        $school_users = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo htmlspecialchars($school['name']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .conversation-list {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }
        
        .conversation-item {
            cursor: pointer;
            transition: background-color 0.2s;
            border-left: 3px solid transparent;
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            background-color: #e7f3ff;
            border-left-color: #0d6efd;
        }
        
        .conversation-item.unread {
            background-color: #fff3cd;
        }
        
        .message-thread {
            max-height: calc(100vh - 350px);
            overflow-y: auto;
        }
        
        .message-bubble {
            max-width: 70%;
            margin-bottom: 1rem;
        }
        
        .message-bubble.sent {
            margin-left: auto;
        }
        
        .message-bubble.received {
            margin-right: auto;
        }
        
        .message-bubble .content {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
        }
        
        .message-bubble.sent .content {
            background-color: #0d6efd;
            color: white;
        }
        
        .message-bubble.received .content {
            background-color: #e9ecef;
            color: #212529;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($school['name']); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="directory.php">
                            <i class="bi bi-people"></i> Alumni Directory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="posts.php">
                            <i class="bi bi-megaphone"></i> Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="opportunities.php">
                            <i class="bi bi-briefcase"></i> Opportunities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="messages.php">
                            <i class="bi bi-chat-dots"></i> Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                           id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle me-2" 
                                     style="width: 30px; height: 30px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i> Edit Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid my-4">
        <div class="row">
            <!-- Sidebar - Conversations List -->
            <div class="col-lg-4 col-md-5 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-chat-dots"></i> Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                            <i class="bi bi-plus-circle"></i> New
                        </button>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="conversation-list">
                            <?php if (empty($conversations)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">No messages yet</p>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#composeModal">
                                        <i class="bi bi-pencil"></i> Start a conversation
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <div class="conversation-item p-3 border-bottom <?php echo $conv['unread_messages'] > 0 ? 'unread' : ''; ?>"
                                         data-user-id="<?php echo $conv['other_user_id']; ?>"
                                         onclick="loadConversation(<?php echo $conv['other_user_id']; ?>, '<?php echo htmlspecialchars($conv['other_user_name'], ENT_QUOTES); ?>')">
                                        <div class="d-flex align-items-start">
                                            <?php if ($conv['other_user_photo']): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($conv['other_user_photo']); ?>" 
                                                     alt="Profile" class="rounded-circle me-3" 
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person text-white fs-4"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex-grow-1 min-width-0">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <strong class="text-truncate">
                                                        <?php echo htmlspecialchars($conv['other_user_name']); ?>
                                                    </strong>
                                                    <?php if ($conv['unread_messages'] > 0): ?>
                                                        <span class="badge bg-danger ms-2"><?php echo $conv['unread_messages']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($conv['current_occupation']): ?>
                                                    <small class="text-muted d-block">
                                                        <?php echo htmlspecialchars($conv['current_occupation']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                
                                                <p class="text-muted small mb-0 text-truncate">
                                                    <?php echo htmlspecialchars(truncateText($conv['last_message'], 50)); ?>
                                                </p>
                                                
                                                <small class="text-muted">
                                                    <?php echo timeAgo($conv['last_message_time']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main - Message Thread -->
            <div class="col-lg-8 col-md-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header" id="conversationHeader">
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-chat-text" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Select a conversation or start a new message</p>
                        </div>
                    </div>
                    
                    <div class="card-body d-none" id="messageContainer">
                        <div class="message-thread" id="messageThread">
                            <!-- Messages will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="card-footer d-none" id="replyContainer">
                        <form id="replyForm">
                            <div class="input-group">
                                <input type="hidden" id="replyRecipientId" name="recipient_id">
                                <textarea class="form-control" id="replyMessage" name="message" 
                                          rows="2" placeholder="Type your message..." required></textarea>
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-send"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="composeModalLabel">
                        <i class="bi bi-pencil-square"></i> New Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="composeForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="recipientSelect" class="form-label">To:</label>
                            <select class="form-select" id="recipientSelect" name="recipient_id" required>
                                <option value="">Select recipient...</option>
                                <?php foreach ($school_users as $school_user): ?>
                                    <option value="<?php echo $school_user['id']; ?>">
                                        <?php echo htmlspecialchars($school_user['name']); ?>
                                        <?php if ($school_user['current_occupation']): ?>
                                            - <?php echo htmlspecialchars($school_user['current_occupation']); ?>
                                        <?php endif; ?>
                                        <?php if ($school_user['year_group']): ?>
                                            (Class of <?php echo htmlspecialchars($school_user['year_group']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="messageSubject" class="form-label">Subject: (Optional)</label>
                            <input type="text" class="form-control" id="messageSubject" name="subject" 
                                   placeholder="Enter subject...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="messageBody" class="form-label">Message:</label>
                            <textarea class="form-control" id="messageBody" name="message" 
                                      rows="5" placeholder="Type your message..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentConversationUser = null;
        let messageRefreshInterval = null;

        // Compose form submission
        document.getElementById('composeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'send_message');
            
            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('composeModal')).hide();
                    this.reset();
                    
                    // Load the conversation
                    const recipientId = formData.get('recipient_id');
                    const recipientName = document.querySelector(`#recipientSelect option[value="${recipientId}"]`).text;
                    loadConversation(recipientId, recipientName);
                    
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while sending the message.');
            });
        });

        // Reply form submission
        document.getElementById('replyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'send_message');
            formData.append('subject', ''); // Empty subject for replies
            
            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('replyMessage').value = '';
                    loadConversation(currentConversationUser.id, currentConversationUser.name);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while sending the message.');
            });
        });

        function loadConversation(userId, userName) {
            currentConversationUser = { id: userId, name: userName };
            
            // Update active conversation in list
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-user-id="${userId}"]`)?.classList.add('active');
            
            // Update header
            document.getElementById('conversationHeader').innerHTML = `
                <div class="d-flex align-items-center">
                    <h5 class="mb-0"><i class="bi bi-chat-dots"></i> ${userName}</h5>
                </div>
            `;
            
            // Show containers
            document.getElementById('messageContainer').classList.remove('d-none');
            document.getElementById('replyContainer').classList.remove('d-none');
            document.getElementById('replyRecipientId').value = userId;
            
            // Load messages
            const formData = new FormData();
            formData.append('action', 'get_conversation');
            formData.append('user_id', userId);
            
            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMessages(data.messages);
                    
                    // Start auto-refresh
                    if (messageRefreshInterval) {
                        clearInterval(messageRefreshInterval);
                    }
                    messageRefreshInterval = setInterval(() => {
                        refreshConversation(userId);
                    }, 5000); // Refresh every 5 seconds
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Failed to load conversation.');
            });
        }

        function refreshConversation(userId) {
            if (currentConversationUser && currentConversationUser.id === userId) {
                const formData = new FormData();
                formData.append('action', 'get_conversation');
                formData.append('user_id', userId);
                
                fetch('messages.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages);
                    }
                })
                .catch(error => console.error('Refresh error:', error));
            }
        }

        function displayMessages(messages) {
            const thread = document.getElementById('messageThread');
            const currentUserId = <?php echo $user['id']; ?>;
            
            thread.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == currentUserId;
                const bubbleClass = isSent ? 'sent' : 'received';
                
                return `
                    <div class="message-bubble ${bubbleClass}">
                        ${msg.subject && !isSent ? `<div class="mb-1"><strong>${msg.subject}</strong></div>` : ''}
                        <div class="content">
                            ${msg.message.replace(/\n/g, '<br>')}
                        </div>
                        <div class="message-time ${isSent ? 'text-end' : ''}">
                            ${timeAgo(msg.created_at)}
                            ${msg.is_read && isSent ? '<i class="bi bi-check-all text-primary"></i>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            // Scroll to bottom
            thread.scrollTop = thread.scrollHeight;
        }

        function timeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            
            return date.toLocaleDateString();
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Clean up interval on page unload
        window.addEventListener('beforeunload', () => {
            if (messageRefreshInterval) {
                clearInterval(messageRefreshInterval);
            }
        });
    </script>
</body>
</html>