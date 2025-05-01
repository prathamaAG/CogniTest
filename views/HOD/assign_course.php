<?php
session_start();
require_once '../../config/database.php';

// Ensure HOD access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: ../auth/login.php");
    exit();
}

$faculty_id = $_GET['faculty_id'] ?? null;
$courses = $conn->query("SELECT name FROM courses");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assign Course</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Assign Course to Faculty</h2>
    <form action="assign_course_action.php" method="POST">
        <input type="hidden" name="faculty_id" value="<?= $faculty_id ?>">
        <select name="course" class="form-control">
            <?php while ($course = $courses->fetch_assoc()): ?>
                <option value="<?= $course['name'] ?>"><?= $course['name'] ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary mt-3">Assign</button>
    </form>
</div>
</body>
</html>
