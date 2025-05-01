<?php
session_start();
require_once '../../config/database.php';

// Ensure HOD access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch faculty members
$query = "SELECT id, username, email, name FROM users WHERE role = 'faculty' and status = 'approved'";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css"> <!-- Ensure this path is correct -->
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
            <div class="col-md-10 mt-4">
                <h2>Faculty List</h2>
                <table class="table table-bordered mt-3">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Assigned Course</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-id="<?= $row['id'] ?>">
                            <td><?= $row['id'] ?></td>
                            <td>
                                <span class="view-mode"><?= $row['username'] ?></span>
                                <input type="text" class="edit-input form-control" value="<?= $row['username'] ?>" style="display:none;">
                            </td>
                            <td>
                                <span class="view-mode"><?= $row['email'] ?></span>
                                <input type="email" class="edit-input form-control" value="<?= $row['email'] ?>" style="display:none;">
                            </td>
                            <td>
                                <span class="view-mode"><?= $row['name'] ?: 'Not Assigned' ?></span>
                                <input type="text" class="edit-input form-control" value="<?= $row['name'] ?>" style="display:none;">
                            </td>
                            <td>
                                <button class="btn btn-primary edit-btn">Edit</button>
                                <button class="btn btn-success save-btn" style="display:none;">Save</button>
                                <button class="btn btn-danger delete-btn">Delete</button>
                                <a href="assign_course.php?faculty_id=<?= $row['id'] ?>" class="btn btn-warning">Assign Course</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
$(document).ready(function() {
    $(".edit-btn").click(function() {
        var row = $(this).closest("tr");
        row.find(".view-mode").hide();
        row.find(".edit-input").show();
        row.find(".edit-btn").hide();
        row.find(".save-btn").show();
    });

    $(".save-btn").click(function() {
        var row = $(this).closest("tr");
        var facultyId = row.data("id");
        var updatedUsername = row.find(".edit-input:eq(0)").val();
        var updatedEmail = row.find(".edit-input:eq(1)").val();
        var updatedCourse = row.find(".edit-input:eq(2)").val();

        $.post("update_faculty.php", { id: facultyId, username: updatedUsername, email: updatedEmail, name: updatedCourse }, function(response) {
            if (response == "success") {
                row.find(".view-mode:eq(0)").text(updatedUsername).show();
                row.find(".view-mode:eq(1)").text(updatedEmail).show();
                row.find(".view-mode:eq(2)").text(updatedCourse || "Not Assigned").show();
                row.find(".edit-input").hide();
                row.find(".edit-btn").show();
                row.find(".save-btn").hide();
            } else {
                alert("Failed to update.");
            }
        });
    });

    $(".delete-btn").click(function() {
        var row = $(this).closest("tr");
        var facultyId = row.data("id");

        if (confirm("Are you sure you want to delete this faculty?")) {
            $.post("delete_faculty.php", { id: facultyId }, function(response) {
                if (response == "success") {
                    row.remove();
                } else {
                    alert("Failed to delete.");
                }
            });
        }
    });
});
</script>

</body>
</html>