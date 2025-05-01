<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Exam Coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch approved papers
$papers_query = "SELECT p.id, p.faculty_id, p.course_id, p.total_marks, p.duration, p.exam_date, 
                p.status, p.access_key, p.created_at, c.name as course_name, 
                u.username as faculty_name 
                FROM papers p 
                JOIN courses c ON p.course_id = c.id 
                JOIN users u ON p.faculty_id = u.id 
                WHERE p.status = 'approved'
                ORDER BY p.created_at DESC";
$papers_result = $conn->query($papers_query);

// If generating PDF
if (isset($_GET['generate_pdf']) && isset($_GET['access_key'])) {
    $access_key = $_GET['access_key'];
    
    // Check if access key is valid
    $key_query = "SELECT id FROM papers WHERE access_key = ? AND status = 'approved'";
    $key_stmt = $conn->prepare($key_query);
    $key_stmt->bind_param("s", $access_key);
    $key_stmt->execute();
    $paper_result = $key_stmt->get_result();
    
    if ($paper_result->num_rows === 0) {
        $_SESSION['error'] = "Invalid access key or paper not found.";
        header("Location: approved_papers.php");
        exit();
    }
    
    // Redirect to the PDF generation script
    header("Location: generate_pdf.php?access_key=" . $access_key);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Papers - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        .paper-card {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
        }
        .paper-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25);
        }
        .access-key {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: bold;
            letter-spacing: 2px;
            color: #4e73df;
            background-color: #f8f9fc;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #d1d3e2;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar bg-dark text-white">
            <h4 class="p-3">CogniTest</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white" href="coordinator_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active text-white" href="approved_papers.php">Approved Papers</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="rejected_papers.php">Rejected Papers</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h2 class="mb-4">Approved Papers</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- PDF Generator Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i> Generate PDF from Access Key</h5>
                </div>
                <div class="card-body">
                    <form action="approved_papers.php" method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="access_key" placeholder="Enter Access Key" required>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="generate_pdf" class="btn btn-primary">Generate PDF</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($papers_result && $papers_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th>Course</th>
                                <th>Faculty</th>
                                <th>Exam Date</th>
                                <th>Total Marks</th>
                                <th>Duration</th>
                                <th>Access Key</th>
                                <th>Approved On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($paper = $papers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($paper['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($paper['faculty_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($paper['exam_date'])); ?></td>
                                    <td><?php echo $paper['total_marks']; ?></td>
                                    <td><?php echo $paper['duration']; ?> min</td>
                                    <td><span class="access-key"><?php echo $paper['access_key']; ?></span></td>
                                    <td><?php echo date('M j, Y, g:i a', strtotime($paper['created_at'])); ?></td>
                                    <td>
                                        <form action="approved_papers.php" method="GET">
                                            <input type="hidden" name="access_key" value="<?php echo $paper['access_key']; ?>">
                                            <button type="submit" name="generate_pdf" class="btn btn-sm btn-primary">
                                                <i class="fas fa-file-pdf"></i> Download
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No approved papers found.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
