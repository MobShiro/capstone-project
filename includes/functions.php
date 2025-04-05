<?php
// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Sanitization function
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Flash messages
function display_flash_messages() {
    $output = '';
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $type => $message) {
            $output .= "<div class='alert alert-{$type}'>" . h($message) . "</div>";
        }
        unset($_SESSION['flash_messages']);
    }
    return $output;
}