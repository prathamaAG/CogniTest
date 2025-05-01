<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in (either as coordinator or faculty)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['coordinator', 'faculty'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check for access key
if (!isset($_GET['access_key']) || empty($_GET['access_key'])) {
    $_SESSION['error'] = "Access key is required to generate PDF.";
    
    // Redirect based on role
    if ($_SESSION['role'] === 'coordinator') {
        header("Location: coordinator_dashboard.php");
    } else {
        header("Location: view_paper.php");
    }
    exit();
}

$access_key = $_GET['access_key'];
$download_pdf = isset($_GET['download']) && $_GET['download'] == 'true';

// Fetch paper details with course code
$paper_query = "SELECT p.*, c.name AS course_name, c.code AS course_code, u.username AS faculty_name
                FROM papers p
                JOIN courses c ON p.course_id = c.id
                JOIN users u ON p.faculty_id = u.id
                WHERE p.access_key = ?";

$paper_stmt = $conn->prepare($paper_query);
if (!$paper_stmt) {
    die("Prepare statement failed: " . $conn->error . "<br>Query: " . $paper_query);
}

$paper_stmt->bind_param("s", $access_key);
$paper_stmt->execute();
$paper_result = $paper_stmt->get_result();

if ($paper_result->num_rows === 0) {
    $_SESSION['error'] = "Invalid access key or paper not found.";
    
    // Redirect based on role
    if ($_SESSION['role'] === 'coordinator') {
        header("Location: coordinator_dashboard.php");
    } else {
        header("Location: view_paper.php");
    }
    exit();
}

$paper = $paper_result->fetch_assoc();

// Check if paper is approved
if ($paper['status'] !== 'approved') {
    $_SESSION['error'] = "This paper has not been approved yet.";
    
    // Redirect based on role
    if ($_SESSION['role'] === 'coordinator') {
        header("Location: coordinator_dashboard.php");
    } else {
        header("Location: view_paper.php");
    }
    exit();
}

// Fetch questions
if (empty($paper['question_ids'])) {
    $_SESSION['error'] = "No questions found in this paper.";
    
    // Redirect based on role
    if ($_SESSION['role'] === 'coordinator') {
        header("Location: coordinator_dashboard.php");
    } else {
        header("Location: view_paper.php");
    }
    exit();
}

$question_ids = explode(',', $paper['question_ids']);
$placeholders = str_repeat("?,", count($question_ids) - 1) . "?";

// Fix ordering issue with a simpler query
$question_query = "SELECT * FROM questions WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)";

$params = array_merge($question_ids, $question_ids);
$types = str_repeat("i", count($params));

$question_stmt = $conn->prepare($question_query);
if (!$question_stmt) {
    die("Prepare statement failed: " . $conn->error . "<br>Query: " . $question_query);
}

$question_stmt->bind_param($types, ...$params);
$question_stmt->execute();
$questions_result = $question_stmt->get_result();

$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[] = $row;
}

// Group questions by marks
$section_A = array_filter($questions, function($q) { return $q['marks'] == 2; });
$section_B = array_filter($questions, function($q) { return $q['marks'] == 3; });
$section_C = array_filter($questions, function($q) { return $q['marks'] == 5; });

// Function to display questions
function displayQuestions($questions, $startNum) {
    $questionNum = $startNum;
    $output = '';
    foreach ($questions as $question) {
        $output .= '<div class="question">';
        $output .= '<div class="question-header">';
        $output .= 'Q' . $questionNum . '. ' . nl2br(htmlspecialchars($question['question_text'])) . ' [' . $question['marks'] . ' Marks]';
        $output .= '</div>';
        $output .= '</div>';
        $questionNum++;
    }
    return ['html' => $output, 'nextNum' => $questionNum];
}

// If this is a download request, generate and serve the PDF
if ($download_pdf) {
    // Load Dompdf library (install via composer require dompdf/dompdf)
    require_once '../../vendor/autoload.php';
    
    // If Dompdf isn't available, we'll use an alternative approach
    if (!class_exists('\\Dompdf\\Dompdf')) {
        // Alternative PDF generation using mPDF if available
        if (class_exists('\\Mpdf\\Mpdf')) {
            generatePDFWithMPDF($paper, $section_A, $section_B, $section_C);
        } else {
            // Fallback to direct HTML download with PDF-like filename
            generatePDFAsHTML($paper, $section_A, $section_B, $section_C);
        }
    } else {
        // Use Dompdf for PDF generation
        generatePDFWithDompdf($paper, $section_A, $section_B, $section_C);
    }
    exit;
}

// Function to generate PDF with Dompdf
function generatePDFWithDompdf($paper, $section_A, $section_B, $section_C) {
    // Initialize Dompdf
    $dompdf = new \Dompdf\Dompdf([
        'enable_remote' => true,
        'enable_css_float' => true,
        'enable_html5_parser' => true,
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ]);
    
    $html = generateHTMLForPDF($paper, $section_A, $section_B, $section_C);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = slugify($paper['course_code'] . '_' . $paper['course_name']) . '_exam.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
}

// Function to generate PDF with mPDF (alternative)
function generatePDFWithMPDF($paper, $section_A, $section_B, $section_C) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 20,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    $html = generateHTMLForPDF($paper, $section_A, $section_B, $section_C);
    
    $mpdf->WriteHTML($html);
    
    $filename = slugify($paper['course_code'] . '_' . $paper['course_name']) . '_exam.pdf';
    $mpdf->Output($filename, 'D');
}

// Fallback for direct HTML download
function generatePDFAsHTML($paper, $section_A, $section_B, $section_C) {
    $html = generateHTMLForPDF($paper, $section_A, $section_B, $section_C);
    
    // Set headers to force download
    $filename = slugify($paper['course_code'] . '_' . $paper['course_name']) . '_exam.html';
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $html;
}

// Generate HTML specifically for PDF output
function generateHTMLForPDF($paper, $section_A, $section_B, $section_C) {
    $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($paper['course_name']) . ' - Examination Paper</title>
        <style>
            body {
                font-family: "Times New Roman", Times, serif;
                margin: 40px;
                line-height: 1.6;
                font-size: 12pt;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .university-name {
                font-size: 16pt;
                font-weight: bold;
                margin-bottom: 5px;
                text-transform: uppercase;
            }
            .institute-name {
                font-size: 14pt;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .department-name {
                font-size: 13pt;
                margin-bottom: 15px;
            }
            .course-details {
                font-size: 13pt;
                font-weight: bold;
                margin-bottom: 20px;
            }
            .exam-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                border-top: 1px solid #000;
                border-bottom: 1px solid #000;
                padding: 10px 0;
            }
            .left-info, .right-info {
                width: 48%;
            }
            .instructions {
                margin-bottom: 20px;
                border: 1px solid #ccc;
                padding: 10px 15px;
                background-color: #f9f9f9;
            }
            .instructions h3 {
                margin-top: 0;
            }
            .section-header {
                font-weight: bold;
                margin-top: 25px;
                margin-bottom: 15px;
                font-size: 14pt;
                border-bottom: 1px solid #999;
                padding-bottom: 5px;
            }
            .question {
                margin-bottom: 20px;
            }
            .question-header {
                margin-bottom: 5px;
                line-height: 1.4;
            }
            .best-luck {
                text-align: center;
                margin-top: 40px;
                font-weight: bold;
                font-style: italic;
                font-size: 14pt;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="university-name">CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY</div>
            <div class="institute-name">Chandubhai S Patel Institute of Technology</div>
            <div class="department-name">Department of Computer Science and Engineering</div>
            <div class="course-details">' . 
                htmlspecialchars($paper['course_name']) . 
                (!empty($paper['course_code']) ? ' (' . htmlspecialchars($paper['course_code']) . ')' : '') . 
            '</div>
        </div>
        
        <div class="exam-info">
            <div class="left-info">
                <p><strong>Date:</strong> ' . date('F j, Y', strtotime($paper['exam_date'])) . '</p>' .
                (isset($paper['exam_time']) ? '<p><strong>Time:</strong> ' . date('g:i A', strtotime($paper['exam_time'])) . '</p>' : '') . '
            </div>
            <div class="right-info">
                <p><strong>Duration:</strong> ' . $paper['duration'] . ' minutes</p>
                <p><strong>Total Marks:</strong> ' . $paper['total_marks'] . '</p>
            </div>
        </div>
        
        <div class="instructions">
            <h3>Instructions:</h3>
            <ol>
                <li>All questions are mandatory.</li>
                <li>Time allowed is ' . $paper['duration'] . ' minutes.</li>
                <li>The paper carries a total of ' . $paper['total_marks'] . ' marks.</li>
                <li>Write your answers clearly and legibly.</li>
                <li>Do not use any unfair means during the examination.</li>
            </ol>
        </div>';
    
    // Display Section A
    if (!empty($section_A)) {
        $result = displayQuestions($section_A, 1);
        $html .= '<div class="section-header">Section A (2 Marks Each)</div>' . $result['html'];
        $nextQuestionNum = $result['nextNum'];
    } else {
        $nextQuestionNum = 1;
    }
    
    // Display Section B
    if (!empty($section_B)) {
        $result = displayQuestions($section_B, $nextQuestionNum);
        $html .= '<div class="section-header">Section B (3 Marks Each)</div>' . $result['html'];
        $nextQuestionNum = $result['nextNum'];
    }
    
    // Display Section C
    if (!empty($section_C)) {
        $result = displayQuestions($section_C, $nextQuestionNum);
        $html .= '<div class="section-header">Section C (5 Marks Each)</div>' . $result['html'];
    }
    
    $html .= '<div class="best-luck">
            <p>Best of Luck</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Function to create a URL-friendly version of a string
function slugify($text) {
    // Replace non letter or digit characters with underscore
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '_');
    // Remove duplicate separators
    $text = preg_replace('~-+~', '_', $text);
    // Lowercase
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'exam_paper';
    }
    
    return $text;
}

// Generate HTML output for preview
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paper['course_name']); ?> - Examination Paper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            line-height: 1.6;
            font-size: 12pt;
            background-color: #f5f5f5;
        }
        .preview-controls {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #343a40;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .control-buttons {
            display: flex;
            gap: 10px;
        }
        .paper-container {
            max-width: 210mm;
            margin: 80px auto 40px;
            padding: 40px;
            background-color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .paper-container::before {
            content: '';
            position: absolute;
            top: 10px;
            right: 10px;
            border: 50px solid transparent;
            border-bottom: 50px solid rgba(0,0,0,0.03);
            transform: rotate(135deg);
            z-index: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        .university-name {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .institute-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .department-name {
            font-size: 13pt;
            margin-bottom: 15px;
        }
        .course-details {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .exam-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 10px 0;
        }
        .left-info, .right-info {
            width: 48%;
        }
        .instructions {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 10px 15px;
            background-color: #f9f9f9;
        }
        .instructions h3 {
            margin-top: 0;
        }
        .section-header {
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 14pt;
            border-bottom: 1px solid #999;
            padding-bottom: 5px;
        }
        .question {
            margin-bottom: 20px;
        }
        .question-header {
            margin-bottom: 5px;
            line-height: 1.4;
        }
        .best-luck {
            text-align: center;
            margin-top: 40px;
            font-weight: bold;
            font-style: italic;
            font-size: 14pt;
        }
        .zoom-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            border-radius: 30px;
            padding: 5px 15px;
            display: flex;
            align-items: center;
            z-index: 1000;
        }
        .zoom-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #343a40;
        }
        .zoom-btn:hover {
            background-color: #f0f0f0;
            border-radius: 50%;
        }
        .zoom-level {
            margin: 0 10px;
            font-weight: bold;
            min-width: 45px;
            text-align: center;
        }
        @media print {
            .preview-controls, .zoom-controls {
                display: none;
            }
            .paper-container {
                margin: 0;
                box-shadow: none;
                max-width: 100%;
                padding: 20px;
            }
            .paper-container::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="preview-controls">
        <div>
            <h5 class="mb-0"><?php echo htmlspecialchars($paper['course_name']); ?> - Examination Paper</h5>
        </div>
        <div class="control-buttons">
            <a href="<?php echo $_SESSION['role'] === 'coordinator' ? 'coordinator_dashboard.php' : 'view_paper.php'; ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="generate_pdf.php?access_key=<?php echo $access_key; ?>&download=true" class="btn btn-primary btn-sm">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
        </div>
    </div>
    
    <div class="paper-container" id="paperContainer">
        <div class="header">
            <div class="university-name">CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY</div>
            <div class="institute-name">Chandubhai S Patel Institute of Technology</div>
            <div class="department-name">Department of Computer Science and Engineering</div>
            <div class="course-details">
                <?php echo htmlspecialchars($paper['course_name']); ?> 
                <?php if (!empty($paper['course_code'])): ?>
                    (<?php echo htmlspecialchars($paper['course_code']); ?>)
                <?php endif; ?>
            </div>
        </div>
        
        <div class="exam-info">
            <div class="left-info">
                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($paper['exam_date'])); ?></p>
                <?php if (isset($paper['exam_time'])): ?>
                    <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($paper['exam_time'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="right-info">
                <p><strong>Duration:</strong> <?php echo $paper['duration']; ?> minutes</p>
                <p><strong>Total Marks:</strong> <?php echo $paper['total_marks']; ?></p>
            </div>
        </div>
        
        <div class="instructions">
            <h3>Instructions:</h3>
            <ol>
                <li>All questions are mandatory.</li>
                <li>Time allowed is <?php echo $paper['duration']; ?> minutes.</li>
                <li>The paper carries a total of <?php echo $paper['total_marks']; ?> marks.</li>
                <li>Write your answers clearly and legibly.</li>
                <li>Do not use any unfair means during the examination.</li>
            </ol>
        </div>
        
        <?php if (!empty($section_A)): ?>
        <div class="section-header">Section A (2 Marks Each)</div>
        <?php $result = displayQuestions($section_A, 1); echo $result['html']; $nextQuestionNum = $result['nextNum']; ?>
        <?php else: ?>
        <?php $nextQuestionNum = 1; ?>
        <?php endif; ?>
        
        <?php if (!empty($section_B)): ?>
        <div class="section-header">Section B (3 Marks Each)</div>
        <?php $result = displayQuestions($section_B, $nextQuestionNum); echo $result['html']; $nextQuestionNum = $result['nextNum']; ?>
        <?php endif; ?>
        
        <?php if (!empty($section_C)): ?>
        <div class="section-header">Section C (5 Marks Each)</div>
        <?php $result = displayQuestions($section_C, $nextQuestionNum); echo $result['html']; ?>
        <?php endif; ?>
        
        <div class="best-luck">
            <p>Best of Luck</p>
        </div>
    </div>
    
    <div class="zoom-controls">
        <button class="zoom-btn" id="zoomOut" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
        <span class="zoom-level" id="zoomLevel">100%</span>
        <button class="zoom-btn" id="zoomIn" title="Zoom In"><i class="fas fa-search-plus"></i></button>
        <button class="zoom-btn" id="zoomReset" title="Reset Zoom"><i class="fas fa-undo"></i></button>
    </div>
    
    <script>
        // Zoom functionality
        let currentZoom = 100;
        const paperContainer = document.getElementById('paperContainer');
        const zoomLevel = document.getElementById('zoomLevel');
        
        document.getElementById('zoomIn').addEventListener('click', function() {
            if (currentZoom < 200) {
                currentZoom += 10;
                updateZoom();
            }
        });
        
        document.getElementById('zoomOut').addEventListener('click', function() {
            if (currentZoom > 50) {
                currentZoom -= 10;
                updateZoom();
            }
        });
        
        document.getElementById('zoomReset').addEventListener('click', function() {
            currentZoom = 100;
            updateZoom();
        });
        
        function updateZoom() {
            paperContainer.style.transform = `scale(${currentZoom/100})`;
            paperContainer.style.transformOrigin = 'top center';
            zoomLevel.textContent = `${currentZoom}%`;
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Plus: Zoom In
            if (e.ctrlKey && e.key === '+') {
                e.preventDefault();
                document.getElementById('zoomIn').click();
            }
            // Ctrl + Minus: Zoom Out
            else if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                document.getElementById('zoomOut').click();
            }
            // Ctrl + 0: Reset Zoom
            else if (e.ctrlKey && e.key === '0') {
                e.preventDefault();
                document.getElementById('zoomReset').click();
            }
            // Ctrl + P: Print
            else if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
