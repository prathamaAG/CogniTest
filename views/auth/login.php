<?php
session_start();
require_once("../../config/database.php");

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and Password are required!";
        header("Location: login.php");
        exit();
    }

    // Use prepared statements for security
    $query = "SELECT id, email, password, role, status FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $_SESSION['error'] = "Your account is pending approval.";
                header("Location: login.php");
                exit();
            }

            // Store user info in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = strtolower($user['role']); // Standardizing case

            // Redirect based on role
            switch ($_SESSION['role']) {
                case 'hod':
                    header("Location: ../HOD/dashboard.php");
                    break;
                case 'faculty':
                    header("Location: ../faculty/dashboard.php");
                    break;
                case 'coordinator':
                    header("Location: ../coordinator/coordinator_dashboard.php");
                    break;
                default:
                    $_SESSION['error'] = "Invalid role detected.";
                    header("Location: login.php");
                    break;
            }
            exit();
        } else {
            $_SESSION['error'] = "Incorrect password.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "User not found.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CogniTest</title>
    <style>
      * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    background: linear-gradient(120deg, #f7fafc 0%, #e9f3fb 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
}

.auth-container {
    background: rgba(255,255,255,0.92);
    box-shadow: 0 8px 32px 0 rgba(60, 80, 180, 0.05), 0 1.5px 6px 0 rgba(0,0,0,0.03);
    border-radius: 1.2rem;
    padding: 2.2rem 2rem 2rem 2rem;
    width: 340px;
    max-width: 94vw;
    backdrop-filter: blur(7px);
    border: 1px solid rgba(180, 200, 255, 0.11);
    position: relative;
    z-index: 2;
    transition: box-shadow 0.25s;
}

.auth-container:hover {
    box-shadow: 0 12px 36px 0 rgba(60, 80, 180, 0.10), 0 2.5px 10px 0 rgba(0,0,0,0.06);
}

h2 {
    margin-bottom: 1.4rem;
    font-size: 1.7rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: #3a5a92;
    background: linear-gradient(90deg, #3a5a92 30%, #6db7f7 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-align: center;
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

form {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

input {
    width: 100%;
    padding: 0.92rem 1rem;
    border: 1.5px solid #e3eafc;
    border-radius: 0.75rem;
    font-size: 1rem;
    background: rgba(255,255,255,0.97);
    color: #2a3553;
    transition: border 0.2s, box-shadow 0.2s;
    box-shadow: 0 1.5px 6px 0 rgba(0,0,0,0.03);
}

input:focus {
    outline: none;
    border-color: #6db7f7;
    box-shadow: 0 0 0 2px rgba(109,183,247,0.13);
    background: rgba(255,255,255,1);
}

input::placeholder {
    color: #a7b1c9;
    font-size: 0.97rem;
    opacity: 1;
}

button {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(90deg, #6db7f7 0%, #3a5a92 100%);
    color: #fff;
    border: none;
    border-radius: 0.75rem;
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
    margin-top: 1.1rem;
    font-size: 0.96rem;
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
        <h2>Login</h2>
        <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </div>
</body>
</html>
