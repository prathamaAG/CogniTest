<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - CogniTest</title>
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

        .card {
            height: 170px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
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
                <li class="nav-item"><a class="nav-link" href="add_question.php">Add Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="view_paper.php">Review Papers</a></li>
                <li class="nav-item"><a class="nav-link" href="generate_paper.php">Generate Paper</a></li>
                
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>
    
        <main class="col-md-10">
            <nav class="navbar navbar-light">
                
            </nav>

            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="card bg-primary text-white p-3"><a class="nav-link" href="add_question.php">Add Questions</a></div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white p-3"><a class="nav-link" href="view_paper.php">Review Papers</a></div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white p-3"><a class="nav-link" href="generate_paper.php">Generate Paper</a></div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card bg-danger text-white p-3"><a class="nav-link" href="view_courses.php">View Courses</a></div>
                </div> -->
            </div>
        </main>
    </div>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
