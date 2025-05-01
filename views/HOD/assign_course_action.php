<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['faculty_id'], $_POST['course'])) {
    $faculty_id = $_POST['faculty_id'];
    $course = $_POST['course'];

    $query = "UPDATE users SET name = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $course, $faculty_id);

    if ($stmt->execute()) {
        echo "<script>alert('Course Assigned Successfully!'); window.location.href='view_faculty.php';</script>";
    } else {
        echo "<script>alert('Failed to Assign Course!'); window.history.back();</script>";
    }
    $stmt->close();
}
?>
