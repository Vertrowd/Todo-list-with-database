<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'user_auth';
$username = 'root';
$password = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $action = $_POST['action'] ?? '';
        
        if ($action === 'signup') {
            handleSignup($pdo);
        } elseif ($action === 'login') {
            handleLogin($pdo);
        } else {
            throw new Exception('Invalid action.');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

function handleSignup($pdo) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('All fields are required.');
    }

    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match.');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Username or email already exists.');
    }

    // Create user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$username, $email, $hashed_password])) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! You can now login.',
            'redirect' => '?login=1'
        ]);
    } else {
        throw new Exception('Registration failed. Please try again.');
    }
}

function handleLogin($pdo) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        throw new Exception('Please enter both username and password.');
    }

    // Check user credentials
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Update last login timestamp
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => 'to-do.php'
        ]);
    } else {
        throw new Exception('Invalid username or password.');
    }
}
// Check if user is logged in for dashboard
if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) === 'to-do.php') {
    // This would be in a separate to-do.php file, but for demo, we'll handle it here
    displayDashboard();
    exit;
}

function displayDashboard() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f4f4f4; }
            .dashboard { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .welcome { margin-bottom: 20px; text-align: center; }
            .logout { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
            .logout:hover { background: #c82333; }
        </style>
    </head>
    <body>
        <div class="dashboard">
            <div class="welcome">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p>You're now logged in to your account.</p>
            </div>
            <a href="?logout=1" class="logout">Logout</a>
        </div>
    </body>
    </html>
    <?php
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

// Determine which form to show
$showLogin = isset($_GET['login']) || !isset($_GET['signup']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $showLogin ? 'Login' : 'Sign Up'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:disabled {
            background: #ccc;
            transform: none;
            cursor: not-allowed;
        }

        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.hidden {
            display: none;
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .switch-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .switch-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        .switch-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="tabs">
            <button class="tab <?php echo $showLogin ? 'active' : ''; ?>" onclick="showForm('login')">Login</button>
            <button class="tab <?php echo !$showLogin ? 'active' : ''; ?>" onclick="showForm('signup')">Sign Up</button>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="form-container <?php echo $showLogin ? 'active' : ''; ?>">
            <h2>Welcome Back</h2>
            <form id="loginFormElement" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="loginUsername">Username:</label>
                    <input type="text" id="loginUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password:</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>
                <button type="submit" id="loginBtn">Login</button>
            </form>
            <div class="switch-text">
                Don't have an account? <a href="?signup=1">Sign up here</a>
            </div>
        </div>

        <!-- Signup Form -->
        <div id="signupForm" class="form-container <?php echo !$showLogin ? 'active' : ''; ?>">
            <h2>Create Account</h2>
            <form id="signupFormElement" method="POST">
                <input type="hidden" name="action" value="signup">
                <div class="form-group">
                    <label for="signupUsername">Username:</label>
                    <input type="text" id="signupUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="signupEmail">Email:</label>
                    <input type="email" id="signupEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="signupPassword">Password:</label>
                    <input type="password" id="signupPassword" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password:</label>
                    <input type="password" id="confirmPassword" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" id="signupBtn">Sign Up</button>
            </form>
            <div class="switch-text">
                Already have an account? <a href="?login=1">Login here</a>
            </div>
        </div>

        <div id="messageDiv" class="message hidden"></div>
    </div>

    <script>
        function showForm(formType) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
            
            document.querySelector(`[onclick="showForm('${formType}')"]`).classList.add('active');
            document.getElementById(formType + 'Form').classList.add('active');
            
            // Clear messages
            hideMessage();
        }

        // Login form handling
        document.getElementById('loginFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'loginBtn');
        });

        // Signup form handling
        document.getElementById('signupFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                showMessage('Passwords do not match!', 'error');
                return;
            }
            
            if (password.length < 8) {
                showMessage('Password must be at least 8 characters long!', 'error');
                return;
            }
            
            submitForm(this, 'signupBtn');
        });

        function submitForm(form, btnId) {
            const formData = new FormData(form);
            const submitBtn = document.getElementById(btnId);
            
            submitBtn.disabled = true;
            submitBtn.textContent = btnId === 'loginBtn' ? 'Logging in...' : 'Creating Account...';

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    if (data.redirect) {
                        setTimeout(() => {
                            if (data.redirect === 'to-do.php') {
                                window.location.href = data.redirect;
                            } else {
                                window.location.href = window.location.pathname + data.redirect;
                            }
                        }, 1500);
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = btnId === 'loginBtn' ? 'Login' : 'Sign Up';
            });
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('messageDiv');
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
            messageDiv.classList.remove('hidden');
            
            if (type === 'success') {
                setTimeout(hideMessage, 5000);
            }
        }

        function hideMessage() {
            document.getElementById('messageDiv').classList.add('hidden');
        }
    </script>
</body>
</html>