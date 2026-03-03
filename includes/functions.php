<?php
// /includes/functions.php

function redirect_if_not_found($resource, $redirect_url = '404.php') {
    if (!$resource) {
        header("Location: " . $redirect_url);
        exit;
    }
}

function check_id_exists($id, $table, $connection, $redirect_url = '404.php') {
    if (!$id || $id <= 0) {
        header("Location: " . $redirect_url);
        exit;
    }

    $query = "SELECT id FROM " . $table . " WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        header("Location: " . $redirect_url);
        exit;
    }
}
