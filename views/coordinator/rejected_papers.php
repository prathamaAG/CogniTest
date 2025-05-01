<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Exam Coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Add debugging to check SQL errors
$conn->query("SET sql_mode = ''"); // Less strict SQL mode

// Modified query with simpler ordering (updated_at might not exist)
$papers_query = "SELECT p.id, p.faculty_id, p.course_id, p.total_marks, p.duration, p.exam_date, 
                p.exam_time, p.status, p.rejection_reason, p.created_at, p.question_ids,
                c.name as course_name, u.username as faculty_name 
                FROM papers p 
                JOIN courses c ON p.course_id = c.id 
                JOIN users u ON p.faculty_id = u.id 
                WHERE p.status = 'rejected'
                ORDER BY p.created_at DESC";
                
$papers_result = $conn->query($papers_query);

// Debug query
if (!$papers_result) {
    echo "Error in query: " . $conn->error;
    exit;
}

// Debug count
$count = $papers_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Papers - CogniTest</title>
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
        .rejection-reason {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .rejection-reason:hover {
            white-space: normal;
            overflow: visible;
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
                <li class="nav-item"><a class="nav-link text-white" href="approved_papers.php">Approved Papers</a></li>
                <li class="nav-item"><a class="nav-link active text-white" href="rejected_papers.php">Rejected Papers</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h2 class="mb-4">Rejected Papers</h2>

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

            <?php if ($papers_result && $papers_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-danger">
                            <tr>
                                <th>Course</th>
                                <th>Faculty</th>
                                <th>Exam Date</th>
                                <th>Total Marks</th>
                                <th>Duration</th>
                                <th>Rejection Reason</th>
                                <th>Submitted On</th>
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
                                    <td>
                                        <div class="rejection-reason" title="<?php echo htmlspecialchars($paper['rejection_reason'] ?? 'No reason provided'); ?>">
                                            <?php echo htmlspecialchars($paper['rejection_reason'] ?? 'No reason provided'); ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y, g:i a', strtotime($paper['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $paper['id']; ?>">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $paper['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $paper['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title" id="detailsModalLabel<?php echo $paper['id']; ?>">Rejected Paper Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Course:</strong> <?php echo htmlspecialchars($paper['course_name']); ?></p>
                                                                <p><strong>Faculty:</strong> <?php echo htmlspecialchars($paper['faculty_name']); ?></p>
                                                                <p><strong>Exam Date:</strong> <?php echo date('F j, Y', strtotime($paper['exam_date'])); ?></p>
                                                                <p><strong>Duration:</strong> <?php echo $paper['duration']; ?> minutes</p>
                                                                <?php if (!empty($paper['exam_time'])): ?>
                                                                <p><strong>Exam Time:</strong> <?php echo date('g:i A', strtotime($paper['exam_time'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Total Marks:</strong> <?php echo $paper['total_marks']; ?></p>
                                                                <p><strong>Submitted On:</strong> <?php echo date('F j, Y, g:i a', strtotime($paper['created_at'])); ?></p>
                                                                <p><strong>Question IDs:</strong> <?php echo $paper['question_ids']; ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <h6 class="fw-bold">Rejection Reason:</h6>
                                                            <div class="p-3 bg-light rounded">
                                                                <?php echo nl2br(htmlspecialchars($paper['rejection_reason'] ?? 'No reason provided')); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No rejected papers found. (Debug: Found <?php echo $count; ?> papers)
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
