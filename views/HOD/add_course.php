<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

// Handle form submission
$success_msg = "";
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_code = strtoupper(trim($_POST['course_code']));
    $course_name = trim($_POST['course_name']);
    $credits = trim($_POST['credits']);
    $semester = trim($_POST['semester']);

    // Validate course code format
    if (!preg_match("/^CSE[A-Za-z0-9]+$/", $course_code)) {
        $error_msg = "Course code must start with 'CSE' followed by a unique three digit number.";
    } else {
        // Check if course code already exists
        $query = "SELECT COUNT(*) FROM courses WHERE code = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error_msg = "Course code already exists. Please choose a different code.";
        } else {
            // Insert course if valid
            $query = "INSERT INTO courses (code, name, credits, semester, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $course_code, $course_name, $credits, $semester);

            if ($stmt->execute()) {
                $success_msg = "Course added successfully!";
            } else {
                $error_msg = "Error adding course. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }

        .sidebar {
            height: 100vh;
            background-color: #343a40;
            padding: 15px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px;
            display: block;
            border-radius: 5px;
        }

        .sidebar a:hover {
            background-color: #495057;
        }

        .navbar {
            background-color: white;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
<div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar bg-dark text-white">
                <h4 class="p-3">CogniTest</h4>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="faculty_assignment.php">Faculty Assignment</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_faculty.php">View Faculty</a></li>
                    <li class="nav-item"><a class="nav-link" href="add_course.php">Add Course</a></li>
                    <li class="nav-item"><a class="nav-link" href="edit_course.php">Edit Course</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
                </ul>
            </nav>

        <!-- Main Content -->
        <main class="col-md-10">
            <nav class="navbar navbar-light">
                
            </nav>

            <div class="container mt-4">
                <h2>Add Course</h2>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="course_code" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="credits" class="form-label">Credits</label>
                        <input type="number" class="form-control" id="credits" name="credits" required>
                    </div>
                    <div class="mb-3">
                        <label for="semester" class="form-label">Semester</label>
                        <input type="number" class="form-control" id="semester" name="semester" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </form>
            </div>
        </main>
    </div>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
