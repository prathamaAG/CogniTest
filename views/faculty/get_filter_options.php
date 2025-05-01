<?php
session_start();
require_once '../../config/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if course_id is provided
if (!isset($_GET['course_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Course ID is required']);
    exit();
}

$course_id = $_GET['course_id'];

// Fetch unique unit numbers
$unit_query = "SELECT DISTINCT unit_number FROM questions WHERE course_id = ? ORDER BY unit_number";
$unit_stmt = $conn->prepare($unit_query);
$unit_stmt->bind_param("i", $course_id);
$unit_stmt->execute();
$unit_result = $unit_stmt->get_result();

$units = [];
while ($row = $unit_result->fetch_assoc()) {
    if ($row['unit_number'] !== null) {
        $units[] = $row['unit_number'];
    }
}

// Fetch unique marks
$marks_query = "SELECT DISTINCT marks FROM questions WHERE course_id = ? ORDER BY marks";
$marks_stmt = $conn->prepare($marks_query);
$marks_stmt->bind_param("i", $course_id);
$marks_stmt->execute();
$marks_result = $marks_stmt->get_result();

$marks = [];
while ($row = $marks_result->fetch_assoc()) {
    $marks[] = $row['marks'];
}

$bloomLevels = ['remembering', 'understanding', 'applying', 'analyzing', 'evaluating', 'creating'];

$response = [
    'units' => $units,
    'marks' => $marks,
    'bloomLevels' => $bloomLevels
];

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
