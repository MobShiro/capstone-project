<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();
$conn = getDbConnection();

// Process message actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // Verify the message belongs to the user
    $checkStmt = $conn->prepare("SELECT * FROM messages WHERE message_id = ? AND receiver_id = ?");
    $checkStmt->bind_param("ii", $messageId, $user['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        $message = $checkResult->fetch_assoc();
        
        if ($action === 'mark_read') {
            // Mark message as read
            $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ?");
            $updateStmt->bind_param("i", $messageId);
            $updateStmt->execute();
            header('Location: inbox.php?success=marked_read');
            exit;
        } elseif ($action === 'mark_unread') {
            // Mark message as unread
            $updateStmt = $conn->prepare("UPDATE messages SET is_read = 0 WHERE message_id = ?");
            $updateStmt->bind_param("i", $messageId);
            $updateStmt->execute();
            header('Location: inbox.php?success=marked_unread');
            exit;
        } elseif ($action === 'delete') {
            // Delete message
            $deleteStmt = $conn->prepare("DELETE FROM messages WHERE message_id = ?");
            $deleteStmt->bind_param("i", $messageId);
            $deleteStmt->execute();
            header('Location: inbox.php?success=deleted');
            exit;
        }
    }
}

// Get success messages
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'sent':
            $successMessage = 'Message sent successfully!';
            break;
        case 'marked_read':
            $successMessage = 'Message marked as read.';
            break;
        case 'marked_unread':
            $successMessage = 'Message marked as unread.';
            break;
        case 'deleted':
            $successMessage = 'Message deleted successfully.';
            break;
    }
}

// Get inbox messages
$inboxStmt = $conn->prepare("SELECT m.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                           u.profile_image as sender_image,
                           u.user_type as sender_type
                           FROM messages m
                           JOIN users u ON m.sender_id = u.user_id
                           WHERE m.receiver_id = ?
                           ORDER BY m.sent_date DESC");
$inboxStmt->bind_param("i", $user['user_id']);
$inboxStmt->execute();
$inboxResult = $inboxStmt->get_result();

$messages = [];
while ($message = $inboxResult->fetch_assoc()) {
    $messages[] = $message;
}

// Get sent messages
$sentStmt = $conn->prepare("SELECT m.*, 
                          CONCAT(u.first_name, ' ', u.last_name) as receiver_name,
                          u.profile_image as receiver_image,
                          u.user_type as receiver_type
                          FROM messages m
                          JOIN users u ON m.receiver_id = u.user_id
                          WHERE m.sender_id = ?
                          ORDER BY m.sent_date DESC");
$sentStmt->bind_param("i", $user['user_id']);
$sentStmt->execute();
$sentResult = $sentStmt->get_result();

$sentMessages = [];
while ($message = $sentResult->fetch_assoc()) {
    $sentMessages[] = $message;
}

// Count unread messages
$unreadCount = 0;
foreach ($messages as $message) {
    if (!$message['is_read']) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Vet Anywhere</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include '../includes/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="content-header">
                    <h1>Messages</h1>
                    <nav class="breadcrumb">
                        <a href="../<?php echo $user['user_type'] === 'pet_owner' ? 'owner_dashboard.php' : 'vet_dashboard.php'; ?>">Dashboard</a> /
                        <span>Messages</span>
                    </nav>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>

                <div class="messages-container">
                    <!-- Messages Sidebar -->
                    <div class="messages-sidebar">
                        <div class="messages-actions">
                            <a href="compose.php" class="btn-primary btn-full">
                                <i class="fas fa-pen"></i> Compose Message
                            </a>
                        </div>
                        
                        <div class="messages-folders">
                            <ul>
                                <li class="active">
                                    <a href="#inbox" data-tab="inbox">
                                        <i class="fas fa-inbox"></i> Inbox
                                        <?php if ($unreadCount > 0): ?>
                                            <span class="badge"><?php echo $unreadCount; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#sent" data-tab="sent">
                                        <i class="fas fa-paper-plane"></i> Sent
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Messages Content -->
                    <div class="messages-content">
                        <!-- Inbox Tab -->
                        <div id="inbox" class="messages-tab active">
                            <div class="messages-header">
                                <h2>Inbox</h2>
                                <div class="messages-search">
                                    <input type="text" id="inbox-search" placeholder="Search messages...">
                                    <i class="fas fa-search"></i>
                                </div>
                            </div>
                            
                            <div class="messages-list">
                                <?php if (empty($messages)): ?>
                                    <div class="empty-state">
                                        <img src="../../assets/images/empty-messages.svg" alt="Empty Inbox">
                                        <p>Your inbox is empty</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message-item <?php echo $message['is_read'] ? '' : 'unread'; ?>" data-search="<?php echo strtolower($message['sender_name'] . ' ' . $message['subject'] . ' ' . $message['message_text']); ?>">
                                            <div class="message-checkbox">
                                                <input type="checkbox" class="message-select" data-id="<?php echo $message['message_id']; ?>">
                                            </div>
                                            <div class="message-avatar">
                                                <?php if ($message['sender_image']): ?>
                                                    <img src="../../uploads/profile/<?php echo $message['sender_image']; ?>" alt="<?php echo htmlspecialchars($message['sender_name']); ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-details" onclick="window.location='view.php?id=<?php echo $message['message_id']; ?>'">
                                                <div class="message-sender">
                                                    <?php echo htmlspecialchars($message['sender_name']); ?>
                                                    <?php if ($message['sender_type'] === 'veterinarian'): ?>
                                                        <span class="badge vet">Vet</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="message-subject">
                                                    <?php echo htmlspecialchars($message['subject']); ?>
                                                </div>
                                                <div class="message-preview">
                                                    <?php 
                                                        $preview = substr(strip_tags($message['message_text']), 0, 100);
                                                        echo htmlspecialchars($preview) . (strlen($message['message_text']) > 100 ? '...' : '');
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="message-meta">
                                                <div class="message-date">
                                                    <?php 
                                                        $sentDate = new DateTime($message['sent_date']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($sentDate);
                                                        
                                                        if ($diff->d == 0) {
                                                            echo $sentDate->format('g:i A');
                                                        } elseif ($diff->d == 1) {
                                                            echo 'Yesterday';
                                                        } elseif ($diff->d < 7) {
                                                            echo $sentDate->format('l');
                                                        } else {
                                                            echo $sentDate->format('M j');
                                                        }
                                                    ?>
                                                </div>
                                                <div class="message-actions">
                                                    <?php if ($message['is_read']): ?>
                                                        <a href="?action=mark_unread&id=<?php echo $message['message_id']; ?>" class="message-action" title="Mark as unread">
                                                            <i class="fas fa-envelope"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?action=mark_read&id=<?php echo $message['message_id']; ?>" class="message-action" title="Mark as read">
                                                            <i class="fas fa-envelope-open"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="compose.php?reply_to=<?php echo $message['message_id']; ?>" class="message-action" title="Reply">
                                                        <i class="fas fa-reply"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $message['message_id']; ?>" class="message-action delete-message" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sent Tab -->
                        <div id="sent" class="messages-tab">
                            <div class="messages-header">
                                <h2>Sent Messages</h2>
                                <div class="messages-search">
                                    <input type="text" id="sent-search" placeholder="Search messages...">
                                    <i class="fas fa-search"></i>
                                </div>
                            </div>
                            
                            <div class="messages-list">
                                <?php if (empty($sentMessages)): ?>
                                    <div class="empty-state">
                                        <img src="../../assets/images/empty-sent.svg" alt="No Sent Messages">
                                        <p>You haven't sent any messages yet</p>
                                        <a href="compose.php" class="btn-primary">Compose Message</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($sentMessages as $message): ?>
                                        <div class="message-item" data-search="<?php echo strtolower($message['receiver_name'] . ' ' . $message['subject'] . ' ' . $message['message_text']); ?>">
                                            <div class="message-checkbox">
                                                <input type="checkbox" class="message-select" data-id="<?php echo $message['message_id']; ?>">
                                            </div>
                                            <div class="message-avatar">
                                                <?php if ($message['receiver_image']): ?>
                                                    <img src="../../uploads/profile/<?php echo $message['receiver_image']; ?>" alt="<?php echo htmlspecialchars($message['receiver_name']); ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php echo strtoupper(substr($message['receiver_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-details" onclick="window.location='view_sent.php?id=<?php echo $message['message_id']; ?>'">
                                                <div class="message-sender">
                                                    To: <?php echo htmlspecialchars($message['receiver_name']); ?>
                                                    <?php if ($message['receiver_type'] === 'veterinarian'): ?>
                                                        <span class="badge vet">Vet</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="message-subject">
                                                    <?php echo htmlspecialchars($message['subject']); ?>
                                                </div>
                                                <div class="message-preview">
                                                    <?php 
                                                        $preview = substr(strip_tags($message['message_text']), 0, 100);
                                                        echo htmlspecialchars($preview) . (strlen($message['message_text']) > 100 ? '...' : '');
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="message-meta">
                                                <div class="message-date">
                                                    <?php 
                                                        $sentDate = new DateTime($message['sent_date']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($sentDate);
                                                        
                                                        if ($diff->d == 0) {
                                                            echo $sentDate->format('g:i A');
                                                        } elseif ($diff->d == 1) {
                                                            echo 'Yesterday';
                                                        } elseif ($diff->d < 7) {
                                                            echo $sentDate->format('l');
                                                        } else {
                                                            echo $sentDate->format('M j');
                                                        }
                                                    ?>
                                                </div>
                                                <div class="message-actions">
                                                    <?php if ($message['is_read']): ?>
                                                        <span class="message-status read" title="Read">
                                                            <i class="fas fa-check-double"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="message-status unread" title="Unread">
                                                            <i class="fas fa-check"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Delete Message</h2>
            <p>Are you sure you want to delete this message?</p>
            <p class="modal-warning">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-outline" id="cancelDelete">Cancel</button>
                <a href="#" class="btn-danger" id="confirmDelete">Delete</a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab navigation
            const tabLinks = document.querySelectorAll('.messages-folders a');
            const tabContents = document.querySelectorAll('.messages-tab');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active tab link
                    tabLinks.forEach(l => l.parentElement.classList.remove('active'));
                    this.parentElement.classList.add('active');
                    
                    // Show corresponding tab content
                    const tabId = this.getAttribute('data-tab');
                    tabContents.forEach(tab => {
                        tab.classList.remove('active');
                        if (tab.id === tabId) {
                            tab.classList.add('active');
                        }
                    });
                });
            });
            
            // Search functionality
            const inboxSearch = document.getElementById('inbox-search');
            const sentSearch = document.getElementById('sent-search');
            
            function setupSearch(searchInput, messageItems) {
                if (!searchInput) return;
                
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    messageItems.forEach(item => {
                        const searchContent = item.getAttribute('data-search').toLowerCase();
                        if (searchContent.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            setupSearch(inboxSearch, document.querySelectorAll('#inbox .message-item'));
            setupSearch(sentSearch, document.querySelectorAll('#sent .message-item'));
            
            // Delete confirmation modal
            const deleteLinks = document.querySelectorAll('.delete-message');
            const deleteModal = document.getElementById('deleteModal');
            const cancelDelete = document.getElementById('cancelDelete');
            const confirmDelete = document.getElementById('confirmDelete');
            const modalClose = document.querySelector('.modal .close');
            
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const deleteUrl = this.getAttribute('href');
                    confirmDelete.setAttribute('href', deleteUrl);
                    deleteModal.style.display = 'block';
                });
            });
            
            if (modalClose) {
                modalClose.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                });
            }
            
            if (cancelDelete) {
                cancelDelete.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
            
            // Message checkboxes for bulk actions
            const messageCheckboxes = document.querySelectorAll('.message-select');
            const selectAllCheckbox = document.getElementById('select-all');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    messageCheckboxes.forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                });
            }
        });
    </script>
</body>
</html>