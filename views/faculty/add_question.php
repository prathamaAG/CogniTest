<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch course name from users table
$query = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();

$course_name = $faculty['name'];

// Fetch only the courses assigned to the logged-in faculty
$query = "SELECT id, name FROM courses WHERE name = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result = $stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST['course_id'];
    $unit_number = $_POST['unit_number'];
    $question_text = $_POST['question_text'];
    $marks = $_POST['marks'];
    $bloom_level = $_POST['bloom_level'];
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO questions (course_id, unit_number, question_text, marks, bloom_level, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisisss", $course_id, $unit_number, $question_text, $marks, $bloom_level, $user_id, $created_at);
    if ($stmt->execute()) {
        $success_message = "Question added successfully!";
    } else {
        $error_message = "Error adding question: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question - CogniTest</title>
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
                <li class="nav-item"><a class="nav-link" href="add_question.php">Add Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="view_paper.php">Review Papers</a></li>
                <li class="nav-item"><a class="nav-link" href="generate_paper.php">Generate Paper</a></li>
                
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>

        <main class="col-md-10">
            <div class="container mt-5">
                <h2>Add a New Question</h2>
                <?php if (isset($success_message)) echo "<div class='alert alert-success'>$success_message</div>"; ?>
                <?php if (isset($error_message)) echo "<div class='alert alert-danger'>$error_message</div>"; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select class="form-control" name="course_id" required>
                            <option value="">Choose Course</option>
                            <?php foreach ($courses as $course) { ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo $course['name']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="unit_number" class="form-label">Unit Number</label>
                        <input type="number" class="form-control" name="unit_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="bloom_level" class="form-label">Bloom's Taxonomy Level</label>
                        <select class="form-control" name="bloom_level" required>
                            <option value="">Select Level</option>
                            <option value="Remembering">Remembering</option>
                            <option value="Understanding">Understanding</option>
                            <option value="Applying">Applying</option>
                            <option value="Analyzing">Analyzing</option>
                            <option value="Evaluating">Evaluating</option>
                            <option value="Creating">Creating</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="marks" class="form-label">Marks</label>
                        <input type="number" class="form-control" name="marks" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </form>
            </div>
        </main>
    </div>
</div>
</body>
</html>
