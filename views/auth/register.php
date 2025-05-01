<?php
session_start();
require_once '../../config/database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    
    if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
        // Check if email already exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email is already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Set default status: 'approved' for HOD and Exam Coordinator, 'pending' for Faculty
            if ($role == 'HOD') {
                $status = 'approved';
            } elseif ($role == 'Exam Coordinator') {
                $status = 'approved'; // Giving Exam Coordinator approved status by default
            } else {
                $status = 'pending';
            }

            // Insert new user
            $query = "INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $status);
            
            if ($stmt->execute()) {
                $success = "Registration successful! " . ($status == 'pending' ? "Waiting for HOD approval." : "You can now log in.");
            } else {
                $error = "Registration failed. Try again!";
            }
        }
    } else {
        $error = "All fields are required!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CogniTest</title>
    <style>
        * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: linear-gradient(120deg, #e8f0fe 0%, #f6fbff 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.auth-container {
    background: rgba(255, 255, 255, 0.85);
    padding: 32px 28px 28px 28px;
    border-radius: 14px;
    backdrop-filter: blur(10px);
    box-shadow: 0 6px 24px rgba(60, 80, 180, 0.09), 0 2px 8px rgba(0,0,0,0.03);
    text-align: center;
    width: 360px;
    max-width: 95vw;
    border: 1px solid rgba(180, 200, 255, 0.13);
    transition: box-shadow 0.22s;
}

.auth-container:hover {
    box-shadow: 0 12px 36px 0 rgba(60, 80, 180, 0.13), 0 2.5px 10px 0 rgba(0,0,0,0.06);
}

h2 {
    margin-bottom: 18px;
    color: #3262a8;
    font-size: 1.7rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: linear-gradient(90deg, #3a5a92 40%, #6db7f7 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.error {
    color: #d7263d;
    background: rgba(215, 38, 61, 0.08);
    border: 1px solid #ffd6d6;
    border-radius: 0.5rem;
    font-size: 0.97rem;
    margin-bottom: 0.8rem;
    padding: 0.8rem 1rem;
    text-align: left;
}

.success {
    color: #218838;
    background: rgba(40, 167, 69, 0.08);
    border: 1px solid #d4edda;
    border-radius: 0.5rem;
    font-size: 0.97rem;
    margin-bottom: 0.8rem;
    padding: 0.8rem 1rem;
    text-align: left;
}

form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

input, select {
    width: 100%;
    padding: 13px;
    border: 1.5px solid #e3eafc;
    border-radius: 9px;
    font-size: 1rem;
    background: rgba(255,255,255,0.96);
    color: #2a3553;
    transition: border 0.2s, box-shadow 0.2s;
    box-shadow: 0 1.5px 6px 0 rgba(0,0,0,0.03);
    text-align: center;
}

input:focus, select:focus {
    background: rgba(255,255,255,1);
    border-color: #6db7f7;
    box-shadow: 0 0 0 2px rgba(109,183,247,0.13);
    outline: none;
}

input::placeholder {
    color: #a7b1c9;
    font-size: 0.97rem;
    opacity: 1;
}

button {
    width: 100%;
    padding: 13px;
    background: linear-gradient(90deg, #6db7f7 0%, #3a5a92 100%);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 1.07rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.22s, box-shadow 0.22s, transform 0.12s;
    box-shadow: 0 2px 8px 0 rgba(109,183,247,0.09);
    letter-spacing: 0.5px;
}

button:hover, button:focus {
    background: linear-gradient(90deg, #3a5a92 0%, #6db7f7 100%);
    box-shadow: 0 4px 16px 0 rgba(109,183,247,0.13);
    transform: translateY(-1px) scale(1.03);
}

p {
    margin-top: 12px;
    font-size: 0.97rem;
    color: #6c7a93;
    text-align: center;
}

a {
    color: #6db7f7;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.18s;
    position: relative;
}

a:hover {
    color: #3a5a92;
    text-decoration: underline;
}

a::after {
    content: '';
    display: block;
    width: 0;
    height: 2px;
    background: #6db7f7;
    transition: width 0.22s;
    position: absolute;
    left: 0;
    bottom: -2px;
}

a:hover::after {
    width: 100%;
}

::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-thumb {
    background: #e3eafc;
    border-radius: 4px;
}
::-webkit-scrollbar-track {
    background: #f4faff;
}

@media (max-width: 480px) {
    .auth-container {
        padding: 1.1rem 0.7rem 1.3rem 0.7rem;
        width: 98vw;
    }
    h2 {
        font-size: 1.15rem;
    }
}

    </style>
</head>
<body>
    <div class="auth-container">
        <h2>Register</h2>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <?php if ($success) echo "<p class='success'>$success</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="HOD">HOD</option>
                <option value="Faculty">Faculty</option>
                <option value="Exam Coordinator">Exam Coordinator</option>
            </select>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>
