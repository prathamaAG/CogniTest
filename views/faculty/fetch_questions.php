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

// Debug information
error_log("Fetching questions for course ID: $course_id");

// Fetch questions for the selected course
$query = "SELECT id, question_text, marks, bloom_level, unit_number 
          FROM questions 
          WHERE course_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

// Debug information
error_log("Query executed, found " . $result->num_rows . " questions");

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

header('Content-Type: application/json');
echo json_encode($questions);
exit();
?>