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

// Fetch pending faculty requests
$query = "SELECT id, username, email FROM users WHERE status = 'pending'";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// Handle approval or rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $faculty_id = $_POST['faculty_id'];
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $update_query = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $faculty_id);
    
    if ($stmt->execute()) {
        header("Location: faculty_assignment.php");
        exit();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Assignment - CogniTest</title>
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
        
        <main class="col-md-10">
            <nav class="navbar navbar-light">
                
            </nav>
            
            <h2 class="mt-4">Pending Faculty Requests</h2>
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="faculty_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="faculty_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No pending requests</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
