<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

function getQuestionsFromIds($conn, $question_ids) {
    if (empty($question_ids)) return [];
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

function analyzeQuestions($questions) {
    $marks_distribution = [];
    $bloom_distribution = [];
    $total_questions = count($questions);
    $total_marks = 0;
    foreach ($questions as $q) {
        $marks = $q['marks'];
        $total_marks += $marks;
        if (!isset($marks_distribution[$marks])) {
            $marks_distribution[$marks] = 0;
        }
        $marks_distribution[$marks]++;
        $bloom = $q['bloom_level'];
        if (!isset($bloom_distribution[$bloom])) {
            $bloom_distribution[$bloom] = 0;
        }
        $bloom_distribution[$bloom]++;
    }
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

if (isset($_POST['submit_access_key'])) {
    $input_key = trim($_POST['access_key_input']);
    $paper_id = intval($_POST['download_paper_id']);
    $from_list = isset($_POST['from_list']) ? 1 : 0;
    $stmt = $conn->prepare("SELECT access_key FROM papers WHERE id = ? AND faculty_id = ? AND status = 'approved'");
    $stmt->bind_param("ii", $paper_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($input_key === $row['access_key']) {
            header("Location: ../coordinator/generate_pdf.php?access_key=" . urlencode($input_key));
            exit();
        } else {
            $_SESSION['error'] = "Invalid access key entered.";
        }
    } else {
        $_SESSION['error'] = "Invalid request or paper not found.";
    }
    if ($from_list) {
        header("Location: view_paper.php");
    } else {
        header("Location: view_paper.php?paper_id=" . $paper_id);
    }
    exit();
}

$paper_details = null;
$questions = null;
$analysis = null;

if (isset($_GET['paper_id'])) {
    $paper_id = $_GET['paper_id'];
    $paper_query = "SELECT p.*, c.name as course_name,
                    CASE 
                        WHEN p.status = 'approved' THEN 'Approved'
                        WHEN p.status = 'rejected' THEN 'Rejected'
                        ELSE 'Pending'
                    END as status_text,
                    CASE 
                        WHEN p.status = 'approved' THEN 'success'
                        WHEN p.status = 'rejected' THEN 'danger'
                        ELSE 'warning'
                    END as status_class
                    FROM papers p 
                    JOIN courses c ON p.course_id = c.id 
                    WHERE p.id = ? AND p.faculty_id = ?";
    $paper_stmt = $conn->prepare($paper_query);
    $paper_stmt->bind_param("ii", $paper_id, $user_id);
    $paper_stmt->execute();
    $paper_result = $paper_stmt->get_result();
    if ($paper_result->num_rows > 0) {
        $paper_details = $paper_result->fetch_assoc();
        if (!empty($paper_details['question_ids'])) {
            $questions = getQuestionsFromIds($conn, $paper_details['question_ids']);
            $analysis = analyzeQuestions($questions);
        }
    } else {
        $_SESSION['error'] = "Paper not found or you don't have access to it.";
        header("Location: view_paper.php");
        exit();
    }
}

$papers_query = "SELECT p.id, p.course_id, p.total_marks, p.duration, p.exam_date, 
                p.status, p.access_key, p.rejection_reason, p.created_at, c.name as course_name,
                CASE 
                    WHEN p.status = 'approved' THEN 'Approved'
                    WHEN p.status = 'rejected' THEN 'Rejected'
                    ELSE 'Pending'
                END as status_text,
                CASE 
                    WHEN p.status = 'approved' THEN 'success'
                    WHEN p.status = 'rejected' THEN 'danger'
                    ELSE 'warning'
                END as status_class
                FROM papers p 
                JOIN courses c ON p.course_id = c.id 
                WHERE p.faculty_id = ?
                ORDER BY p.created_at DESC";
$papers_stmt = $conn->prepare($papers_query);
$papers_stmt->bind_param("i", $user_id);
$papers_stmt->execute();
$papers_result = $papers_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Papers - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fc; }
        .sidebar { height: 100vh; background-color: #343a40; padding: 15px; }
        .sidebar a { color: white; text-decoration: none; padding: 10px; display: block; border-radius: 5px; }
        .sidebar a:hover { background-color: #495057; }
        .paper-card { border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); transition: all 0.3s ease; }
        .paper-card:hover { transform: translateY(-5px); box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25); }
        .access-key { font-family: monospace; font-size: 1.1rem; font-weight: bold; letter-spacing: 2px; color: #4e73df; background-color: #f8f9fc; padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d3e2; }
        .rejection-box { background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .stats-card { border-left: 4px solid #4e73df; border-radius: 5px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        .card-icon { font-size: 2rem; color: #4e73df; }
        .card-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem; color: #4e73df; }
        .card-value { font-size: 1.5rem; font-weight: 700; color: #5a5c69; }
        .bloom-badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .bloom-remembering { background-color: #d1ecf1; color: #0c5460; }
        .bloom-understanding { background-color: #d4edda; color: #155724; }
        .bloom-applying { background-color: #fff3cd; color: #856404; }
        .bloom-analyzing { background-color: #f8d7da; color: #721c24; }
        .bloom-evaluating { background-color: #e2e3e5; color: #383d41; }
        .bloom-creating { background-color: #cce5ff; color: #004085; }
        .question-text { white-space: pre-wrap; }
        .chart-container { position: relative; height: 300px; width: 100%; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar bg-dark text-white">
            <h4 class="p-3">CogniTest</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="add_question.php">Add Questions</a></li>
                <li class="nav-item"><a class="nav-link active text-white" href="view_paper.php">View Papers</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="generate_paper.php">Generate Paper</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>
        <main class="col-md-10 p-4">
            <?php if ($paper_details): ?>
                <div class="mb-3">
                    <a href="view_paper.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Papers List
                    </a>
                </div>
                <h2 class="mb-4">Paper Analysis: <?php echo htmlspecialchars($paper_details['course_name']); ?></h2>
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
                <div class="card mb-4">
                    <div class="card-header bg-<?php echo $paper_details['status_class']; ?> text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Paper Details</h5>
                        <div>
                            <span class="badge bg-light text-dark">Created: <?php echo date('M j, Y, g:i a', strtotime($paper_details['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Basic Information</h6>
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($paper_details['course_name']); ?></p>
                                <p><strong>Total Marks:</strong> <?php echo $paper_details['total_marks']; ?></p>
                                <p><strong>Duration:</strong> <?php echo $paper_details['duration']; ?> minutes</p>
                                <p><strong>Status:</strong> <span class="badge bg-<?php echo $paper_details['status_class']; ?>"><?php echo $paper_details['status_text']; ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Exam Schedule</h6>
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($paper_details['exam_date'])); ?></p>
                                <?php if (isset($paper_details['exam_time']) && !empty($paper_details['exam_time'])): ?>
                                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($paper_details['exam_time'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($paper_details['status'] === 'approved'): ?>
                            <div class="alert alert-success">
                                <h6 class="fw-bold"><i class="fas fa-check-circle me-2"></i> Paper Approved</h6>
                                <p class="mb-2">Your paper has been approved by the coordinator.</p>
                                <p class="mb-0"><strong>Access Key:</strong> <span class="access-key"><?php echo $paper_details['access_key']; ?></span></p>
                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#accessKeyModalSingle">
                                    <i class="fas fa-file-pdf me-2"></i> Download PDF
                                </button>
                            </div>
                            <div class="modal fade" id="accessKeyModalSingle" tabindex="-1" aria-labelledby="accessKeyModalSingleLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                <form action="generate_pdf.php" method="get" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Enter Access Key</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="text" class="form-control" name="access_key" placeholder="Enter Access Key" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Download PDF</button>
                                    </div>
                                </form>

                                </div>
                            </div>
                        <?php elseif ($paper_details['status'] === 'rejected'): ?>
                            <div class="rejection-box">
                                <h6 class="fw-bold"><i class="fas fa-times-circle me-2"></i> Paper Rejected</h6>
                                <p><strong>Reason for Rejection:</strong></p>
                                <p class="mb-0">
                                    <?php 
                                    if (isset($paper_details['rejection_reason']) && !empty(trim($paper_details['rejection_reason']))) {
                                        echo nl2br(htmlspecialchars($paper_details['rejection_reason']));
                                    } else {
                                        echo 'No reason provided';
                                    }
                                    ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <h6 class="fw-bold"><i class="fas fa-clock me-2"></i> Pending Review</h6>
                                <p class="mb-0">This paper is currently being reviewed by the exam coordinator.</p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($questions) && !empty($analysis)): ?>
                            <div class="row mt-4 mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Paper Analysis</h5>
                                </div>
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
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0">Marks Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="marksChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0">Bloom's Taxonomy Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="bloomChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <h2 class="mb-4">View Submitted Papers</h2>
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
                <?php if ($papers_result->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($paper = $papers_result->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card paper-card h-100">
                                    <div class="card-header bg-<?php echo $paper['status_class']; ?> text-white">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($paper['course_name']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Status:</strong> <span class="badge bg-<?php echo $paper['status_class']; ?>"><?php echo $paper['status_text']; ?></span></p>
                                        <p><strong>Total Marks:</strong> <?php echo $paper['total_marks']; ?></p>
                                        <p><strong>Duration:</strong> <?php echo $paper['duration']; ?> minutes</p>
                                        <p><strong>Exam Date:</strong> <?php echo date('F j, Y', strtotime($paper['exam_date'])); ?></p>
                                        <?php if (isset($paper['exam_time']) && !empty($paper['exam_time'])): ?>
                                        <p><strong>Exam Time:</strong> <?php echo date('g:i A', strtotime($paper['exam_time'])); ?></p>
                                        <?php endif; ?>
                                        <p><strong>Submitted On:</strong> <?php echo date('M j, Y, g:i a', strtotime($paper['created_at'])); ?></p>
                                        <?php if ($paper['status'] === 'approved'): ?>
                                            <div class="mt-3">
                                                <p><strong>Access Key:</strong> <span class="access-key"><?php echo $paper['access_key']; ?></span></p>
                                                <!-- <button type="button" class="btn btn-primary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#accessKeyModalList<?php echo $paper['id']; ?>">
                                                    <i class="fas fa-file-pdf me-2"></i> Download PDF
                                                </button> -->
                                                <div class="modal fade" id="accessKeyModalList<?php echo $paper['id']; ?>" tabindex="-1" aria-labelledby="accessKeyModalListLabel<?php echo $paper['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <form method="POST" class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="accessKeyModalListLabel<?php echo $paper['id']; ?>">Enter Access Key</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="download_paper_id" value="<?php echo $paper['id']; ?>">
                                                                <input type="hidden" name="from_list" value="1">
                                                                <div class="mb-3">
                                                                    <label for="access_key_input_list<?php echo $paper['id']; ?>" class="form-label">Access Key</label>
                                                                    <input type="text" class="form-control" id="access_key_input_list<?php echo $paper['id']; ?>" name="access_key_input" required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="submit" name="submit_access_key" class="btn btn-primary">Download PDF</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ($paper['status'] === 'rejected'): ?>
                                            <div class="rejection-box mt-3">
                                                <p class="mb-0"><strong>Reason for Rejection:</strong></p>
                                                <p class="mb-0">
                                                    <?php 
                                                    if (isset($paper['rejection_reason']) && !empty(trim($paper['rejection_reason']))) {
                                                        echo nl2br(htmlspecialchars($paper['rejection_reason']));
                                                    } else {
                                                        echo 'No reason provided';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mt-3">
                                                <p class="mb-0">This paper is pending review by the exam coordinator.</p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-3">
                                            <a href="view_paper.php?paper_id=<?php echo $paper['id']; ?>" class="btn btn-info w-100">
                                                <i class="fas fa-chart-pie me-2"></i> View Analysis
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-footer text-muted">
                                        <small>Last Updated: <?php echo date('M j, Y, g:i a', strtotime($paper['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> You haven't submitted any papers yet.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if (isset($analysis) && $analysis): ?>
    const marksLabels = [];
    const marksData = [];
    const marksColors = [
        'rgba(78, 115, 223, 0.7)',
        'rgba(54, 185, 204, 0.7)',
        'rgba(246, 194, 62, 0.7)',
        'rgba(231, 74, 59, 0.7)',
        'rgba(28, 200, 138, 0.7)'
    ];
    <?php foreach ($analysis['marks_distribution'] as $mark => $count): ?>
        marksLabels.push('<?php echo $mark; ?> marks');
        marksData.push(<?php echo $count; ?>);
    <?php endforeach; ?>
    const bloomLabels = [];
    const bloomData = [];
    const bloomColors = [
        '#d1ecf1',
        '#d4edda',
        '#fff3cd',
        '#f8d7da',
        '#e2e3e5',
        '#cce5ff'
    ];
    <?php foreach ($analysis['bloom_distribution'] as $bloom => $percentage): ?>
        bloomLabels.push('<?php echo ucfirst($bloom); ?>');
        bloomData.push(<?php echo $percentage; ?>);
    <?php endforeach; ?>
    const marksCtx = document.getElementById('marksChart').getContext('2d');
    const marksChart = new Chart(marksCtx, {
        type: 'bar',
        data: {
            labels: marksLabels,
            datasets: [{
                label: 'Number of Questions',
                data: marksData,
                backgroundColor: marksColors,
                borderColor: marksColors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw + ' question(s)';
                        }
                    }
                }
            }
        }
    });
    const bloomCtx = document.getElementById('bloomChart').getContext('2d');
    const bloomChart = new Chart(bloomCtx, {
        type: 'pie',
        data: {
            labels: bloomLabels,
            datasets: [{
                data: bloomData,
                backgroundColor: bloomColors,
                borderColor: bloomColors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 15, padding: 15 } },
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
