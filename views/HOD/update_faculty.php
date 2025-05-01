<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['username'], $_POST['email'], $_POST['name'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $name = $_POST['name'];

    $query = "UPDATE users SET username = ?, email = ?, name = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $username, $email, $name, $id);

    echo $stmt->execute() ? "success" : "error";
    $stmt->close();
}
?>
