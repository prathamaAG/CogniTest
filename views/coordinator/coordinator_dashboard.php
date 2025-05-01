<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Exam Coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch pending papers for review
$papers_query = "SELECT p.id, p.faculty_id, p.course_id, p.total_marks, p.duration, p.exam_date, 
                p.status, p.access_key, p.question_ids, p.created_at, c.name as course_name, 
                u.username as faculty_name 
                FROM papers p 
                JOIN courses c ON p.course_id = c.id 
                JOIN users u ON p.faculty_id = u.id 
                WHERE p.status = 'pending'
                ORDER BY p.created_at DESC";
$papers_result = $conn->query($papers_query);

// If approving a paper
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_paper'])) {
    $paper_id = $_POST['paper_id'];
    $access_key = bin2hex(random_bytes(4)); // Generate 8-character alphanumeric key
    
    $update_query = "UPDATE papers SET status = 'approved', access_key = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        $_SESSION['error'] = "SQL Error: " . $conn->error;
        header("Location: coordinator_dashboard.php");
        exit();
    }
    
    $update_stmt->bind_param("si", $access_key, $paper_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Paper approved successfully! Access Key: " . $access_key;
    } else {
        $_SESSION['error'] = "Failed to approve paper: " . $conn->error;
    }
    
    header("Location: coordinator_dashboard.php");
    exit();
}

// If rejecting a paper
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_paper'])) {
    $paper_id = $_POST['paper_id'];
    $rejection_reason = $_POST['rejection_reason'];
    
    // First check if rejection_reason column exists
    $check_column = $conn->query("SHOW COLUMNS FROM papers LIKE 'rejection_reason'");
    
    if ($check_column->num_rows == 0) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE papers ADD COLUMN rejection_reason TEXT NULL AFTER access_key");
    }
    
    $update_query = "UPDATE papers SET status = 'rejected', rejection_reason = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        $_SESSION['error'] = "SQL Error: " . $conn->error;
        header("Location: coordinator_dashboard.php");
        exit();
    }
    
    $update_stmt->bind_param("si", $rejection_reason, $paper_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Paper rejected with feedback provided.";
    } else {
        $_SESSION['error'] = "Failed to reject paper: " . $conn->error;
    }
    
    header("Location: coordinator_dashboard.php");
    exit();
}

// Function to get paper details
function getPaperDetails($conn, $paper_id) {
    $query = "SELECT p.*, c.name as course_name, u.username as faculty_name 
              FROM papers p 
              JOIN courses c ON p.course_id = c.id 
              JOIN users u ON p.faculty_id = u.id 
              WHERE p.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to get question details from question IDs
function getQuestionsFromIds($conn, $question_ids) {
    $id_array = explode(',', $question_ids);
    $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
    
    $query = "SELECT * FROM questions WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($query);
    
    $types = str_repeat('i', count($id_array));
    $stmt->bind_param($types, ...$id_array);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    return $questions;
}

// Function to analyze questions and get distribution
function analyzeQuestions($questions) {
    $marks_distribution = [];
    $bloom_distribution = [];
    $total_questions = count($questions);
    $total_marks = 0;
    
    foreach ($questions as $q) {
        // Marks distribution
        $marks = $q['marks'];
        $total_marks += $marks;
        if (!isset($marks_distribution[$marks])) {
            $marks_distribution[$marks] = 0;
        }
        $marks_distribution[$marks]++;
        
        // Bloom's taxonomy distribution
        $bloom = $q['bloom_level'];
        if (!isset($bloom_distribution[$bloom])) {
            $bloom_distribution[$bloom] = 0;
        }
        $bloom_distribution[$bloom]++;
    }
    
    // Convert bloom distribution to percentages
    foreach ($bloom_distribution as $bloom => $count) {
        $bloom_distribution[$bloom] = round(($count / $total_questions) * 100, 1);
    }
    
    return [
        'marks_distribution' => $marks_distribution,
        'bloom_distribution' => $bloom_distribution,
        'total_questions' => $total_questions,
        'total_marks' => $total_marks
    ];
}

// If viewing paper details
$paper_details = null;
$questions = null;
$analysis = null;

if (isset($_GET['paper_id'])) {
    $paper_id = $_GET['paper_id'];
    $paper_details = getPaperDetails($conn, $paper_id);
    
    if ($paper_details) {
        $questions = getQuestionsFromIds($conn, $paper_details['question_ids']);
        $analysis = analyzeQuestions($questions);
    }
}

// Generate PDF view
if (isset($_GET['generate_pdf']) && isset($_GET['access_key'])) {
    $access_key = $_GET['access_key'];
    
    // Check if access key is valid
    $key_query = "SELECT id FROM papers WHERE access_key = ? AND status = 'approved'";
    $key_stmt = $conn->prepare($key_query);
    $key_stmt->bind_param("s", $access_key);
    $key_stmt->execute();
    $paper_result = $key_stmt->get_result();
    
    if ($paper_result->num_rows === 0) {
        $_SESSION['error'] = "Invalid access key or paper not approved.";
        header("Location: coordinator_dashboard.php");
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
    <title>Exam Coordinator Dashboard - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stats-card {
            border-left: 4px solid #4e73df;
            border-radius: 5px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-icon {
            font-size: 2rem;
            color: #4e73df;
        }
        .card-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
            color: #4e73df;
        }
        .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #5a5c69;
        }
        .bloom-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .bloom-remembering { background-color: #d1ecf1; color: #0c5460; }
        .bloom-understanding { background-color: #d4edda; color: #155724; }
        .bloom-applying { background-color: #fff3cd; color: #856404; }
        .bloom-analyzing { background-color: #f8d7da; color: #721c24; }
        .bloom-evaluating { background-color: #e2e3e5; color: #383d41; }
        .bloom-creating { background-color: #cce5ff; color: #004085; }
        .question-text {
            white-space: pre-wrap;
        }
        .access-key-box {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .access-key {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            letter-spacing: 2px;
            color: #4e73df;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar bg-dark text-white">
            <h4 class="p-3">CogniTest</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white active" href="coordinator_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="approved_papers.php">Approved Papers</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="rejected_papers.php">Rejected Papers</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
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
                    <form action="coordinator_dashboard.php" method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="access_key" placeholder="Enter Access Key" required>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="generate_pdf" class="btn btn-primary">Generate PDF</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- When viewing specific paper details -->
            <?php if ($paper_details): ?>
                <div class="mb-3">
                    <a href="coordinator_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Papers List
                    </a>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Paper Review: <?php echo htmlspecialchars($paper_details['course_name']); ?></h5>
                        <div>
                            <span class="badge bg-light text-dark">Created: <?php echo date('M j, Y, g:i a', strtotime($paper_details['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Paper Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Basic Information</h6>
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($paper_details['course_name']); ?></p>
                                <p><strong>Faculty:</strong> <?php echo htmlspecialchars($paper_details['faculty_name']); ?></p>
                                <p><strong>Total Marks:</strong> <?php echo $paper_details['total_marks']; ?></p>
                                <p><strong>Duration:</strong> <?php echo $paper_details['duration']; ?> minutes</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Exam Schedule</h6>
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($paper_details['exam_date'])); ?></p>
                                <?php if (isset($paper_details['exam_time'])): ?>
                                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($paper_details['exam_time'])); ?></p>
                                <?php endif; ?>
                                <p><strong>Status:</strong> <span class="badge bg-warning">Pending Review</span></p>
                            </div>
                        </div>

                        <!-- Paper Analysis -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2">Paper Analysis</h5>
                            </div>

                            <!-- Summary Cards -->
                            <div class="col-md-3 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <i class="fas fa-clipboard-list card-icon"></i>
                                            </div>
                                            <div class="col-9 text-end">
                                                <div class="card-title">Total Questions</div>
                                                <div class="card-value"><?php echo $analysis['total_questions']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <i class="fas fa-award card-icon"></i>
                                            </div>
                                            <div class="col-9 text-end">
                                                <div class="card-title">Total Marks</div>
                                                <div class="card-value"><?php echo $analysis['total_marks']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <i class="fas fa-clock card-icon"></i>
                                            </div>
                                            <div class="col-9 text-end">
                                                <div class="card-title">Duration</div>
                                                <div class="card-value"><?php echo $paper_details['duration']; ?> min</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <i class="fas fa-brain card-icon"></i>
                                            </div>
                                            <div class="col-9 text-end">
                                                <div class="card-title">Bloom Levels</div>
                                                <div class="card-value"><?php echo count($analysis['bloom_distribution']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Marks and Bloom's Distribution -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">Marks Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="marksChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">Bloom's Taxonomy Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="bloomChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Questions Preview -->
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2">Question Preview</h5>
                            </div>
                            
                            <div class="col-md-12 mb-4">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Unit</th>
                                                <th>Question</th>
                                                <th>Marks</th>
                                                <th>Bloom Level</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($questions as $i => $q): ?>
                                                <tr>
                                                    <td><?php echo $i + 1; ?></td>
                                                    <td><?php echo $q['unit_number'] ?? 'N/A'; ?></td>
                                                    <td class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></td>
                                                    <td><?php echo $q['marks']; ?></td>
                                                    <td>
                                                        <span class="bloom-badge bloom-<?php echo $q['bloom_level']; ?>">
                                                            <?php echo ucfirst($q['bloom_level']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approval/Rejection Actions -->
                        <div class="row">
                            <div class="col-md-12 d-flex justify-content-between">
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle me-2"></i> Reject Paper
                                </button>
                                
                                <form method="POST" action="coordinator_dashboard.php">
                                    <input type="hidden" name="paper_id" value="<?php echo $paper_details['id']; ?>">
                                    <button type="submit" name="approve_paper" class="btn btn-success">
                                        <i class="fas fa-check-circle me-2"></i> Approve Paper
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            
            <?php else: ?>
                <!-- Dashboard content when not viewing a specific paper -->
                <h2 class="mb-4">Exam Coordinator Dashboard</h2>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Papers Pending Review</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($papers_result && $papers_result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Faculty</th>
                                                    <th>Total Marks</th>
                                                    <th>Duration</th>
                                                    <th>Exam Date</th>
                                                    <th>Submitted On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($paper = $papers_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($paper['course_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($paper['faculty_name']); ?></td>
                                                        <td><?php echo $paper['total_marks']; ?></td>
                                                        <td><?php echo $paper['duration']; ?> min</td>
                                                        <td><?php echo date('M j, Y', strtotime($paper['exam_date'])); ?></td>
                                                        <td><?php echo date('M j, Y, g:i a', strtotime($paper['created_at'])); ?></td>
                                                        <td>
                                                            <a href="coordinator_dashboard.php?paper_id=<?php echo $paper['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i> Review
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> No papers pending review at this time.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Reject Paper Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">Reject Paper</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="coordinator_dashboard.php">
                <div class="modal-body">
                    <input type="hidden" name="paper_id" value="<?php echo $paper_details ? $paper_details['id'] : ''; ?>">
                    
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide detailed feedback to help the faculty improve the paper."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_paper" class="btn btn-danger">Reject Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap & Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if (isset($analysis) && $analysis): ?>
    // Prepare data for marks distribution chart
    const marksLabels = [];
    const marksData = [];
    
    <?php foreach ($analysis['marks_distribution'] as $mark => $count): ?>
        marksLabels.push('<?php echo $mark; ?> marks');
        marksData.push(<?php echo $count; ?>);
    <?php endforeach; ?>
    
    // Prepare data for bloom distribution chart
    const bloomLabels = [];
    const bloomData = [];
    const bloomColors = [
        '#d1ecf1', // remembering
        '#d4edda', // understanding
        '#fff3cd', // applying
        '#f8d7da', // analyzing
        '#e2e3e5', // evaluating
        '#cce5ff'  // creating
    ];
    
    <?php foreach ($analysis['bloom_distribution'] as $bloom => $percentage): ?>
        bloomLabels.push('<?php echo ucfirst($bloom); ?>');
        bloomData.push(<?php echo $percentage; ?>);
    <?php endforeach; ?>
    
    // Create marks distribution chart
    const marksCtx = document.getElementById('marksChart').getContext('2d');
    const marksChart = new Chart(marksCtx, {
        type: 'bar',
        data: {
            labels: marksLabels,
            datasets: [{
                label: 'Number of Questions',
                data: marksData,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Create bloom distribution chart
    const bloomCtx = document.getElementById('bloomChart').getContext('2d');
    const bloomChart = new Chart(bloomCtx, {
        type: 'pie',
        data: {
            labels: bloomLabels,
            datasets: [{
                data: bloomData,
                backgroundColor: bloomColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
<?php endif; ?>
</script>
</body>
</html>
