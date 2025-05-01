<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in as Faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Courses Assigned to Faculty
$course_query = "SELECT c.id, c.name 
                 FROM courses c 
                 INNER JOIN users u ON c.name = u.name
                 WHERE u.id = ?";
$course_stmt = $conn->prepare($course_query);
$course_stmt->bind_param("i", $user_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

// Handle Paper Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    $course_id = $_POST['course_id'];
    $total_marks = $_POST['total_marks'];
    $duration = $_POST['duration'];
    $exam_date = $_POST['exam_date'];
    $exam_time = $_POST['exam_time'];
    $status = "pending";
    $access_key = bin2hex(random_bytes(4));
    $selected_questions = isset($_POST['questions']) ? $_POST['questions'] : [];

    // Debug: Log the questions being submitted
    error_log("Submitting questions: " . print_r($selected_questions, true));

    if (!empty($selected_questions)) {
        $question_ids = implode(",", $selected_questions);

        // Combine date and time for database storage
        $exam_datetime = $exam_date . ' ' . $exam_time;

        // Insert paper details into papers table
        $insert_query = "INSERT INTO papers (faculty_id, course_id, total_marks, duration, exam_date, exam_time, status, access_key, question_ids, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iiissssss", $user_id, $course_id, $total_marks, $duration, $exam_date, $exam_time, $status, $access_key, $question_ids);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Paper generated successfully!";
        } else {
            $_SESSION['error'] = "Failed to generate paper: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Please select at least one question.";
    }

    header("Location: generate_paper.php");
    exit();
}

// Handle Question Edit/Delete AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'edit') {
        // Process edit question functionality
        $question_id = $_POST['question_id'];
        $question_text = $_POST['question_text'];
        $marks = $_POST['marks'];
        $bloom_level = $_POST['bloom_level'];
        $unit_number = $_POST['unit_number'];
        
        $update_query = "UPDATE questions SET question_text = ?, marks = ?, bloom_level = ?, unit_number = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sisii", $question_text, $marks, $bloom_level, $unit_number, $question_id);
        
        $result = $update_stmt->execute();
        
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Question updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update question: ' . $conn->error]);
        }
        exit();
    } elseif ($_POST['action'] == 'delete') {
        // Process delete question functionality
        $question_id = $_POST['question_id'];
        
        $delete_query = "DELETE FROM questions WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $question_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Question deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete question: ' . $conn->error]);
        }
        exit();
    }
}

// Fetch Unique Units and Marks for Filters
function fetchUniqueValues($conn, $courseId) {
    $units = [];
    $marks = [];
    $bloomLevels = ['remembering', 'understanding', 'applying', 'analyzing', 'evaluating', 'creating'];
    
    if ($courseId) {
        // Fetch unique unit numbers
        $unit_query = "SELECT DISTINCT unit_number FROM questions WHERE course_id = ? ORDER BY unit_number";
        $unit_stmt = $conn->prepare($unit_query);
        $unit_stmt->bind_param("i", $courseId);
        $unit_stmt->execute();
        $unit_result = $unit_stmt->get_result();
        
        while ($row = $unit_result->fetch_assoc()) {
            if ($row['unit_number'] !== null) {
                $units[] = $row['unit_number'];
            }
        }
        
        // Fetch unique marks
        $marks_query = "SELECT DISTINCT marks FROM questions WHERE course_id = ? ORDER BY marks";
        $marks_stmt = $conn->prepare($marks_query);
        $marks_stmt->bind_param("i", $courseId);
        $marks_stmt->execute();
        $marks_result = $marks_stmt->get_result();
        
        while ($row = $marks_result->fetch_assoc()) {
            $marks[] = $row['marks'];
        }
    }
    
    return ['units' => $units, 'marks' => $marks, 'bloomLevels' => $bloomLevels];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Paper - CogniTest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }
        .sidebar {
            height: 350vh;
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
        .question-table {
            margin-top: 20px;
        }
        .question-text {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .modal-dialog {
            max-width: 700px;
        }
        .filter-section {
            background-color: #f0f2f5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-title {
            margin-bottom: 15px;
            color: #343a40;
            font-weight: 600;
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
        .text-muted-row {
            opacity: 0.6;
        }
        .marks-status-perfect {
            background-color: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .marks-status-remaining {
            background-color: #ffc107;
            color: #212529;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .marks-status-exceeded {
            background-color: #dc3545;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .marks-warning {
            color: #ffc107;
            margin-left: 5px;
        }
        #selectedIdsDebug {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar bg-dark text-white">
            <h4 class="p-3">CogniTest</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="add_question.php">Add Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="view_paper.php">Review Papers</a></li>
                <li class="nav-item"><a class="nav-link active" href="generate_paper.php">Generate Paper</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php">Logout</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h2>Generate Exam Paper</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php elseif (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST" action="generate_paper.php" id="paperForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Select Course</label>
                        <select class="form-select" name="course_id" id="courseSelect" required>
                            <option value="">-- Select Course --</option>
                            <?php while ($row = $course_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Total Marks</label>
                        <input type="number" class="form-control" name="total_marks" id="totalMarksInput" min="1" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Duration (Minutes)</label>
                        <input type="number" class="form-control" name="duration" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Exam Date</label>
                        <input type="date" class="form-control" name="exam_date" required>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Exam Time</label>
                        <input type="time" class="form-control" name="exam_time" required>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section" id="filterSection" style="display: none;">
                    <h5 class="filter-title">Filter Questions</h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select" id="unitFilter">
                                <option value="">All Units</option>
                                <!-- Units will be populated dynamically -->
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Marks</label>
                            <select class="form-select" id="marksFilter">
                                <option value="">All Marks</option>
                                <!-- Marks will be populated dynamically -->
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Bloom's Level</label>
                            <select class="form-select" id="bloomFilter">
                                <option value="">All Levels</option>
                                <option value="remembering">Remembering</option>
                                <option value="understanding">Understanding</option>
                                <option value="applying">Applying</option>
                                <option value="analyzing">Analyzing</option>
                                <option value="evaluating">Evaluating</option>
                                <option value="creating">Creating</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary w-100" id="resetFilters">Reset Filters</button>
                        </div>
                    </div>
                </div>

                <h5>Select Questions</h5>
                <div class="mb-3">
                    <div id="questionContainer">
                        <div class="alert alert-info">Please select a course to view available questions.</div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="bloomChart" width="400" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div id="selectedQuestionsSummary" class="p-3 border rounded">
                                <h5>Selected Questions Summary</h5>
                                <div id="summaryContent">
                                    <p>No questions selected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Debug section -->
                <div class="mt-3 mb-3">
                    <button type="button" id="showSelectedBtn" class="btn btn-outline-info btn-sm">Show Selected Question IDs</button>
                    <div id="selectedIdsDebug" style="display:none;" class="mt-2 p-2 border rounded bg-light">
                        <h6>Selected Question IDs (Total: <span id="selectedCount">0</span>):</h6>
                        <div id="selectedIdsList"></div>
                    </div>
                </div>

                <!-- Hidden inputs for selected questions -->
                <div id="hiddenQuestionsContainer"></div>

                <button type="submit" class="btn btn-primary" id="generatePaperBtn">Generate Paper</button>
            </form>
        </main>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editQuestionForm">
                    <input type="hidden" id="edit_question_id" name="question_id">
                    <div class="mb-3">
                        <label for="edit_question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit_number" class="form-label">Unit Number</label>
                        <input type="number" class="form-control" id="edit_unit_number" name="unit_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_marks" class="form-label">Marks</label>
                        <input type="number" class="form-control" id="edit_marks" name="marks" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_bloom_level" class="form-label">Bloom's Level</label>
                        <select class="form-select" id="edit_bloom_level" name="bloom_level" required>
                            <option value="remembering">Remembering</option>
                            <option value="understanding">Understanding</option>
                            <option value="applying">Applying</option>
                            <option value="analyzing">Analyzing</option>
                            <option value="evaluating">Evaluating</option>
                            <option value="creating">Creating</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveQuestionChanges">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Global variables to store all questions and filters
let allQuestions = [];
let selectedQuestionIds = new Set(); // Store selected question IDs
let bloomChartInstance = null;
let totalMarksLimit = 0;
let currentTotalMarks = 0;
let remainingMarksMap = {}; // Track remaining marks by mark value

// Listen for changes on total marks input
document.getElementById('totalMarksInput').addEventListener('change', function() {
    totalMarksLimit = parseInt(this.value) || 0;
    updateTotalMarksLimit();
});

// Add form validation before submission
document.getElementById('paperForm').addEventListener('submit', function(e) {
    if (selectedQuestionIds.size === 0) {
        e.preventDefault();
        alert('Please select at least one question.');
        return false;
    }
    
    if (totalMarksLimit > 0) {
        if (currentTotalMarks !== totalMarksLimit) {
            if (!confirm(`Warning: The selected questions total ${currentTotalMarks} marks, but you specified ${totalMarksLimit} marks. Do you want to continue anyway?`)) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    // Log what we're submitting for debugging
    console.log("Submitting questions:", Array.from(selectedQuestionIds));
});

document.getElementById('courseSelect').addEventListener('change', function () {
    let courseId = this.value;
    if (courseId) {
        // Show the filter section
        document.getElementById('filterSection').style.display = 'block';
        
        // Reset selections when course changes
        selectedQuestionIds = new Set();
        currentTotalMarks = 0;
        
        // Fetch questions for the selected course
        fetchQuestions(courseId);
        
        // Fetch and populate filter options
        fetchFilterOptions(courseId);
    } else {
        document.getElementById('filterSection').style.display = 'none';
        document.getElementById('questionContainer').innerHTML = '<div class="alert alert-info">Please select a course to view available questions.</div>';
    }
});

function fetchQuestions(courseId) {
    fetch('fetch_questions.php?course_id=' + courseId)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Fetched questions:", data);
            allQuestions = data;
            displayQuestions(data);
        })
        .catch(error => {
            console.error('Error fetching questions:', error);
            document.getElementById('questionContainer').innerHTML = `
                <div class="alert alert-danger">
                    <p><strong>Error loading questions:</strong> ${error.message}</p>
                    <p>Please check if the fetch_questions.php file exists and is accessible.</p>
                </div>`;
        });
}

function fetchFilterOptions(courseId) {
    fetch('get_filter_options.php?course_id=' + courseId)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            // Populate unit filter dropdown
            const unitFilter = document.getElementById('unitFilter');
            unitFilter.innerHTML = '<option value="">All Units</option>';
            
            if (data.units && data.units.length > 0) {
                data.units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = `Unit ${unit}`;
                    unitFilter.appendChild(option);
                });
            }
            
            // Populate marks filter dropdown
            const marksFilter = document.getElementById('marksFilter');
            marksFilter.innerHTML = '<option value="">All Marks</option>';
            
            if (data.marks && data.marks.length > 0) {
                data.marks.forEach(mark => {
                    const option = document.createElement('option');
                    option.value = mark;
                    option.textContent = `${mark} Marks`;
                    marksFilter.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching filter options:', error);
            // Silent failure for filter options, we'll just have the defaults
        });
}

// Apply filters when any filter changes
document.getElementById('unitFilter').addEventListener('change', applyFilters);
document.getElementById('marksFilter').addEventListener('change', applyFilters);
document.getElementById('bloomFilter').addEventListener('change', applyFilters);

// Reset filters button
document.getElementById('resetFilters').addEventListener('click', function() {
    document.getElementById('unitFilter').value = '';
    document.getElementById('marksFilter').value = '';
    document.getElementById('bloomFilter').value = '';
    applyFilters();
});

function applyFilters() {
    const unitFilter = document.getElementById('unitFilter').value;
    const marksFilter = document.getElementById('marksFilter').value;
    const bloomFilter = document.getElementById('bloomFilter').value;
    
    // Filter the questions based on selected criteria
    const filteredQuestions = allQuestions.filter(q => {
        return (unitFilter === '' || q.unit_number == unitFilter) &&
               (marksFilter === '' || q.marks == marksFilter) &&
               (bloomFilter === '' || q.bloom_level === bloomFilter);
    });
    
    displayQuestions(filteredQuestions);
}

function displayQuestions(questions) {
    let container = document.getElementById('questionContainer');
    container.innerHTML = '';

    if (questions.length === 0) {
        container.innerHTML = "<div class='alert alert-warning'>No questions found matching the selected filters.</div>";
        return;
    }

    // Create table to display questions
    let table = document.createElement('table');
    table.className = 'table table-striped table-bordered question-table';
    
    // Create table header
    let thead = document.createElement('thead');
    thead.innerHTML = `
        <tr>
            <th>ID</th>
            <th>Unit</th>
            <th>Question</th>
            <th>Marks</th>
            <th>Bloom Level</th>
            <th>Select</th>
            <th>Actions</th>
        </tr>
    `;
    table.appendChild(thead);
    
    // Create table body
    let tbody = document.createElement('tbody');
    questions.forEach(q => {
        let tr = document.createElement('tr');
        let isSelected = selectedQuestionIds.has(q.id.toString());
        
        // Get bloom level class for styling
        let bloomClass = `bloom-${q.bloom_level}`;
        
        tr.innerHTML = `
            <td>${q.id}</td>
            <td>${q.unit_number || 'Not set'}</td>
            <td class="question-text" title="${q.question_text.replace(/"/g, '&quot;')}">${q.question_text}</td>
            <td>${q.marks}</td>
            <td><span class="bloom-badge ${bloomClass}">${q.bloom_level}</span></td>
            <td><input class="form-check-input question-checkbox" type="checkbox" name="questions[]" value="${q.id}" data-marks="${q.marks}" data-bloom="${q.bloom_level}" data-unit="${q.unit_number || 'Not set'}" ${isSelected ? 'checked' : ''}></td>
            <td>
                <button type="button" class="btn btn-sm btn-primary edit-question" 
                    data-id="${q.id}" 
                    data-question="${q.question_text.replace(/"/g, '&quot;')}" 
                    data-unit="${q.unit_number || ''}" 
                    data-marks="${q.marks}" 
                    data-bloom="${q.bloom_level}">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger delete-question" data-id="${q.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    
    container.appendChild(table);
    
    // Initialize action buttons
    initializeActionButtons();
    
    // Add event listeners to checkboxes for summary and chart with marks validation
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const questionId = this.value;
            const marks = parseInt(this.getAttribute('data-marks'));
            
            if (this.checked) {
                // Add question to selected set
                selectedQuestionIds.add(questionId);
                currentTotalMarks += marks;
                
                // If checked, check if we exceed the limit
                if (totalMarksLimit > 0 && currentTotalMarks > totalMarksLimit) {
                    alert(`You can't select this question as it exceeds the total marks limit of ${totalMarksLimit}`);
                    this.checked = false;
                    selectedQuestionIds.delete(questionId);
                    currentTotalMarks -= marks;
                    return;
                }
            } else {
                // Remove question from selected set
                selectedQuestionIds.delete(questionId);
                currentTotalMarks -= marks;
            }
            
            // Update summary and checkbox states
            updateSummaryAndChart();
            updateCheckboxStates();
        });
    });
    
    // Initialize checkbox states if we have a total marks limit
    if (totalMarksLimit > 0) {
        updateCheckboxStates();
    }
    
    // Update summary and chart based on current selections
    updateSummaryAndChart();
}

function updateSummaryAndChart() {
    // Get all currently displayed selected questions
    const selectedQuestions = [];
    let totalMarks = 0;
    let unitDistribution = {};
    let bloomDistribution = {};
    
    // Create a map of all questions for quick lookup
    const questionsMap = {};
    allQuestions.forEach(q => {
        questionsMap[q.id] = q;
    });
    
    // Update hidden inputs for form submission
    const hiddenContainer = document.getElementById('hiddenQuestionsContainer');
    hiddenContainer.innerHTML = ''; // Clear previous hidden inputs
    
    // Process all selected question IDs
    selectedQuestionIds.forEach(id => {
        // Add hidden input for each selected question
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'questions[]';
        hiddenInput.value = id;
        hiddenContainer.appendChild(hiddenInput);
        
        const q = questionsMap[id];
        if (q) {
            selectedQuestions.push({
                id: id,
                marks: q.marks,
                bloom: q.bloom_level,
                unit: q.unit_number || 'Not set'
            });
            
            totalMarks += q.marks;
            
            // Track unit distribution
            const unit = q.unit_number || 'Not set';
            unitDistribution[unit] = (unitDistribution[unit] || 0) + 1;
            
            // Track bloom level distribution
            bloomDistribution[q.bloom_level] = (bloomDistribution[q.bloom_level] || 0) + 1;
        }
    });
    
    // Update current total marks
    currentTotalMarks = totalMarks;
    
    const summaryContainer = document.getElementById('summaryContent');
    
    if (selectedQuestions.length === 0) {
        summaryContainer.innerHTML = '<p>No questions selected</p>';
        
        // Update chart with empty data
        updateBloomLevelChart([]);
        return;
    }
    
    // Calculate remaining marks
    let remainingMarks = totalMarksLimit - totalMarks;
    let marksStatus = '';
    
    if (totalMarksLimit > 0) {
        if (remainingMarks === 0) {
            marksStatus = '<span class="marks-status-perfect">Perfect match!</span>';
        } else if (remainingMarks > 0) {
            marksStatus = `<span class="marks-status-remaining">${remainingMarks} marks remaining</span>`;
        } else {
            marksStatus = `<span class="marks-status-exceeded">Exceeds by ${Math.abs(remainingMarks)} marks</span>`;
        }
    }
    
    // Generate summary HTML
    let summaryHTML = `
        <p><strong>Total Questions:</strong> ${selectedQuestions.length}</p>
        <p><strong>Selected Marks:</strong> ${totalMarks} ${totalMarksLimit > 0 ? `/ ${totalMarksLimit} ${marksStatus}` : ''}</p>
        <h6>Unit Distribution:</h6>
        <ul>
    `;
    
    for (const unit in unitDistribution) {
        summaryHTML += `<li>Unit ${unit}: ${unitDistribution[unit]} questions</li>`;
    }
    
    summaryHTML += `
        </ul>
        <h6>Bloom's Level Distribution:</h6>
        <ul>
    `;
    
    for (const bloom in bloomDistribution) {
        summaryHTML += `<li>${bloom.charAt(0).toUpperCase() + bloom.slice(1)}: ${bloomDistribution[bloom]} questions</li>`;
    }
    
    summaryHTML += '</ul>';
    
    summaryContainer.innerHTML = summaryHTML;
    
    // Update Bloom's level chart
    updateBloomLevelChart(selectedQuestions);
}

function updateBloomLevelChart(selectedQuestions) {
    const ctx = document.getElementById('bloomChart').getContext('2d');
    
    // Destroy existing chart if it exists
    if (bloomChartInstance) {
        bloomChartInstance.destroy();
    }
    
    // Count questions by Bloom's level
    const bloomLevels = ['remembering', 'understanding', 'applying', 'analyzing', 'evaluating', 'creating'];
    const bloomData = bloomLevels.map(level => {
        return selectedQuestions.filter(q => q.bloom === level).length;
    });
    
    // Create new chart
    bloomChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: bloomLevels.map(level => level.charAt(0).toUpperCase() + level.slice(1)),
            datasets: [{
                label: 'Questions by Bloom\'s Taxonomy',
                data: bloomData,
                backgroundColor: [
                    '#d1ecf1', // remembering
                    '#d4edda', // understanding
                    '#fff3cd', // applying
                    '#f8d7da', // analyzing
                    '#e2e3e5', // evaluating
                    '#cce5ff'  // creating
                ],
                borderColor: [
                    '#0c5460',
                    '#155724',
                    '#856404',
                    '#721c24',
                    '#383d41',
                    '#004085'
                ],
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
                title: {
                    display: true,
                    text: 'Questions by Bloom\'s Taxonomy Level'
                },
                legend: {
                    display: false
                }
            }
        }
    });
}

// Functions for total marks limit validation
function updateTotalMarksLimit() {
    calculateCurrentTotal();
    updateSummaryAndChart();
    updateCheckboxStates();
}

function calculateCurrentTotal() {
    // Calculate totals for each mark value
    const markValueTotals = {};
    let total = 0;
    
    // Create a map of all questions for quick lookup
    const questionsMap = {};
    allQuestions.forEach(q => {
        questionsMap[q.id] = q;
    });
    
    // Sum up marks for each mark value
    selectedQuestionIds.forEach(id => {
        const q = questionsMap[id];
        if (q) {
            const marks = q.marks;
            markValueTotals[marks] = (markValueTotals[marks] || 0) + 1;
            total += marks;
        }
    });
    
    // Store the totals
    currentTotalMarks = total;
    
    // Calculate remaining marks for each mark value
    if (totalMarksLimit > 0) {
        remainingMarksMap = {};
        // Initialize with total limit for all mark values
        allQuestions.forEach(q => {
            remainingMarksMap[q.marks] = totalMarksLimit;
        });
        
        // Subtract used marks
        selectedQuestionIds.forEach(id => {
            const q = questionsMap[id];
            if (q) {
                const marks = q.marks;
                remainingMarksMap[marks] -= marks;
            }
        });
    }
}

function updateCheckboxStates() {
    if (totalMarksLimit <= 0) {
        // If no total marks limit set, enable all checkboxes
        document.querySelectorAll('.question-checkbox:not(:checked)').forEach(checkbox => {
            checkbox.disabled = false;
            checkbox.title = "";
            checkbox.closest('tr').classList.remove('text-muted-row');
            
            // Remove warning icon if present
            const warningIcon = checkbox.closest('tr').querySelector('.marks-warning');
            if (warningIcon) {
                warningIcon.remove();
            }
        });
        return;
    }
    
    // Get remaining marks for the paper
    const remainingMarks = totalMarksLimit - currentTotalMarks;
    
    document.querySelectorAll('.question-checkbox:not(:checked)').forEach(checkbox => {
        const checkboxMarks = parseInt(checkbox.getAttribute('data-marks'));
        const row = checkbox.closest('tr');
        
        // Only disable if adding this question would exceed the total limit
        if (checkboxMarks > remainingMarks) {
            checkbox.disabled = true;
            checkbox.title = "Selecting this would exceed the total marks limit";
            row.classList.add('text-muted-row');
            
            // Add warning icon if not already present
            if (!row.querySelector('.marks-warning')) {
                const marksCell = row.querySelector('td:nth-child(4)'); // The marks column
                const warningIcon = document.createElement('span');
                warningIcon.className = 'marks-warning';
                warningIcon.innerHTML = '<i class="fas fa-exclamation-circle" title="Selecting this would exceed the total marks limit"></i>';
                marksCell.appendChild(warningIcon);
            }
        } else {
            checkbox.disabled = false;
            checkbox.title = "";
            row.classList.remove('text-muted-row');
            
            // Remove warning icon if present
            const warningIcon = row.querySelector('.marks-warning');
            if (warningIcon) {
                warningIcon.remove();
            }
        }
    });
}

function initializeActionButtons() {
    // Edit question button click handler
    document.querySelectorAll('.edit-question').forEach(button => {
        button.addEventListener('click', function() {
            const questionId = this.getAttribute('data-id');
            const questionText = this.getAttribute('data-question');
            const unitNumber = this.getAttribute('data-unit');
            const marks = this.getAttribute('data-marks');
            const bloomLevel = this.getAttribute('data-bloom');
            
            // Populate modal with question data
            document.getElementById('edit_question_id').value = questionId;
            document.getElementById('edit_question_text').value = questionText;
            document.getElementById('edit_unit_number').value = unitNumber;
            document.getElementById('edit_marks').value = marks;
            
            // Make sure the bloom level is selected properly
            const bloomSelect = document.getElementById('edit_bloom_level');
            for (let i = 0; i < bloomSelect.options.length; i++) {
                if (bloomSelect.options[i].value === bloomLevel) {
                    bloomSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
            modal.show();
        });
    });
    
    // Delete question button click handler
    document.querySelectorAll('.delete-question').forEach(button => {
        button.addEventListener('click', function() {
            const questionId = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this question?')) {
                // Send AJAX request to delete the question
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('question_id', questionId);
                
                fetch('generate_paper.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        
                        // Remove from selected questions if it was selected
                        if (selectedQuestionIds.has(questionId)) {
                            selectedQuestionIds.delete(questionId);
                        }
                        
                        // Remove from allQuestions array
                        allQuestions = allQuestions.filter(q => q.id != questionId);
                        
                        // Refresh the question list
                        applyFilters(); // This will re-display with current filters
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });
}

// Save question changes button click handler
document.getElementById('saveQuestionChanges').addEventListener('click', function() {
    // Get form values
    const questionId = document.getElementById('edit_question_id').value;
    const questionText = document.getElementById('edit_question_text').value;
    const unitNumber = document.getElementById('edit_unit_number').value;
    const marks = document.getElementById('edit_marks').value;
    const bloomLevel = document.getElementById('edit_bloom_level').value;
    
    // Create FormData object
    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('question_id', questionId);
    formData.append('question_text', questionText);
    formData.append('unit_number', unitNumber);
    formData.append('marks', marks);
    formData.append('bloom_level', bloomLevel);
    
    fetch('generate_paper.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            
            // Close modal
            const modalElement = document.getElementById('editQuestionModal');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
            
            // Update the question in allQuestions array
            const questionIndex = allQuestions.findIndex(q => q.id == questionId);
            if (questionIndex !== -1) {
                allQuestions[questionIndex].question_text = questionText;
                allQuestions[questionIndex].unit_number = unitNumber;
                allQuestions[questionIndex].marks = marks;
                allQuestions[questionIndex].bloom_level = bloomLevel;
            }
            
            // Refresh the question list with current filters
            applyFilters();
        } else {
            alert(data.message || 'An error occurred while updating the question');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing your request.');
    });
});

// Debug button to show selected IDs
document.getElementById('showSelectedBtn').addEventListener('click', function() {
    const debugDiv = document.getElementById('selectedIdsDebug');
    const idsList = document.getElementById('selectedIdsList');
    const countSpan = document.getElementById('selectedCount');
    
    if (debugDiv.style.display === 'none') {
        // Show the debug info
        debugDiv.style.display = 'block';
        const selectedIds = Array.from(selectedQuestionIds);
        idsList.innerHTML = selectedIds.join(', ');
        countSpan.textContent = selectedIds.length;
        this.textContent = 'Hide Selected Question IDs';
    } else {
        // Hide the debug info
        debugDiv.style.display = 'none';
        this.textContent = 'Show Selected Question IDs';
    }
});
</script>
</body>
</html>