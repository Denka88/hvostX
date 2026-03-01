<?php
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isModeratorOrHigher() {
    return isset($_SESSION['user_role']) &&
           in_array($_SESSION['user_role'], ['admin', 'moderator']);
}

function checkAdminAccess() {
    if (!isAdmin()) {
        header("Location: ../index.php");
        exit;
    }
}

function checkModeratorAccess() {
    if (!isModeratorOrHigher()) {
        header("Location: ../index.php");
        exit;
    }
}
?>
