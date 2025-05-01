<?php
require_once '../../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];

    $query = "DELETE FROM courses WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "Course deleted successfully!";
    } else {
        echo "Error deleting course.";
    }
}
?>
