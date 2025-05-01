<?php
require_once '../../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $code = $_POST['code'];
    $name = $_POST['name'];
    $credits = $_POST['credits'];
    $semester = $_POST['semester'];

    $query = "UPDATE courses SET code = ?, name = ?, credits = ?, semester = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiii", $code, $name, $credits, $semester, $id);

    if ($stmt->execute()) {
        echo "Course updated successfully!";
    } else {
        echo "Error updating course.";
    }
}
?>
