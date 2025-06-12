<?php
session_start();


// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "tihan_project_management";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Check if email is from IIT Hyderabad domain
    if (!preg_match("/@tihaniith\.in$/", $email)) {
        $error = "Only @tihaniith.in emails are allowed.";
    } else {
        $stmt = $conn->prepare("SELECT email, password, role, employee_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['employee_id'] = $user['employee_id'];

                // Redirect to dashboard or admin page based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'superadmin') {
                    header("Location: superadmin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No user found with that email.";
        }
        $stmt->close();
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TiHAN Project Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/samarkan?styles=6066" rel="stylesheet">
                
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f1f5f9;
            --accent: #06b6d4;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --danger: #ef4444;
            --success: #10b981;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-lg: 0 35px 60px -12px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            background-image: url('assets/images/WhatsApp Image 2025-06-03 at 13.01.41_a5cac95a.jpg');
            background-repeat: no-repeat;
            background-size: cover; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: right;
            padding: 4rem;
            position: relative;
            overflow: auto;
        }

        /* Animated background */
        /* .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(255,255,255,0.1) 50%, 
                transparent 70%);
            animation: shimmer 3s infinite;
        } */

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Floating shapes */
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 3rem;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            /* box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); */
            gap: 1rem;
            animation: pulse 2s infinite;
        }
        .logo img {
          width: 105x;
          height: 60px;
          object-fit: contain;
          border-radius: 12px;
          /* background: rgba(255, 255, 255, 0.2); */
          padding: 5px;
      }
            .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo i {
            color: white;
            font-size: 2rem;
        }

        .header-section h1 {
            color: var(--dark);
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
            font-family: 'Samarkan', sans-serif;
        }

        .header-section p {
            color: var(--gray);
            font-size: 1rem;
            font-weight: 400;
            font-style: italic;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--dark);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            color: var(--dark); 
            background: white;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }

        .form-control:focus + i {
            color: var(--primary);
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--danger);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-left: 4px solid var(--danger);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-footer {
            margin-top: 2rem;
            text-align: center;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray);
        }

        .remember-me input[type="checkbox"] {
            margin-right: 0.5rem;
            accent-color: var(--primary);
        }

        .links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
        }

        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .loading {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .header-section h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- <div class="bg-animation"></div>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div> -->

    <div class="login-container">
        <div class="header-section">
            <div class="logo">
                <img src="assets/images/tihan_logo.webp" alt="TiHAN Logo">
            </div>
            <h1> NIDHI </h1>
            <p>Project Management System</p>
        </div>

        <?php if (!empty($error)) { ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php } ?>

        <form method="POST" action="index.php" id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="Enter your @tihaniith.in email" 
                           required
                           autocomplete="email">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your password" 
                           required
                           autocomplete="current-password">
                    <i class="fas fa-lock" id="passwordIcon"></i>
                </div>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me for 30 days</label>
            </div>

            <button type="submit" class="btn-primary" id="loginBtn">
                <span class="btn-text">Sign In</span>
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Signing in...</span>
                </div>
            </button>
        </form>

        <div class="form-footer">
            <div class="links">
                <a href="#" id="forgotPassword">Forgot Password?</a>
                <a href="register.php">Create Account</a>
            </div>
        </div>
    </div>

    <script>
        // Add interactive password visibility toggle
        document.getElementById('passwordIcon').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-lock');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-lock');
            }
        });

        // Add form submission animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const loading = btn.querySelector('.loading');
            
            btnText.style.display = 'none';
            loading.style.display = 'flex';
            btn.disabled = true;
        });

        // Add input focus animations
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.classList.remove('focused');
            });
        });

        // Email validation for TiHAN domain
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email && !email.endsWith('@tihaniith.in')) {
                this.style.borderColor = 'var(--danger)';
                this.style.animation = 'shake 0.5s ease-in-out';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.animation = 'none';
            }
        });

        // Add forgot password functionality
        document.getElementById('forgotPassword').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Please contact your administrator to reset your password.');
        });
    </script>
</body>
</html>