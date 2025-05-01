<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch courses
$query = "SELECT * FROM courses ORDER BY id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Courses - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        .editable {
            cursor: pointer;
            border-bottom: 1px dashed #007bff;
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
            <div class="container mt-4">
                <h2>Edit Courses</h2>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Credits</th>
                            <th>Semester</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr data-id="<?= $row['id'] ?>">
                                <td><?= $row['id'] ?></td>
                                <td contenteditable="true" class="editable" data-field="code"><?= $row['code'] ?></td>
                                <td contenteditable="true" class="editable" data-field="name"><?= $row['name'] ?></td>
                                <td contenteditable="true" class="editable" data-field="credits"><?= $row['credits'] ?></td>
                                <td contenteditable="true" class="editable" data-field="semester"><?= $row['semester'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success save-btn">Save</button>
                                    <button class="btn btn-sm btn-danger delete-btn">Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script>
$(document).ready(function() {
    // Save edited course
    $(".save-btn").click(function() {
        var row = $(this).closest("tr");
        var id = row.data("id");
        var course_code = row.find("[data-field='code']").text().trim();
        var course_name = row.find("[data-field='name']").text().trim();
        var credits = row.find("[data-field='credits']").text().trim();
        var semester = row.find("[data-field='semester']").text().trim();

        $.ajax({
            url: "update_course.php",
            type: "POST",
            data: {
                id: id,
                code: course_code,
                name: course_name,
                credits: credits,
                semester: semester
            },
            success: function(response) {
                alert(response);
            }
        });
    });

    // Delete course
    $(".delete-btn").click(function() {
        var row = $(this).closest("tr");
        var id = row.data("id");

        if (confirm("Are you sure you want to delete this course?")) {
            $.ajax({
                url: "delete_course.php",
                type: "POST",
                data: { id: id },
                success: function(response) {
                    alert(response);
                    row.remove();
                }
            });
        }
    });
});
</script>

</body>
</html>
