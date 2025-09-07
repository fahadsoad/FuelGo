<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gas_fuel_station";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'owner' || $_SESSION['role'] == 'manager') {
        header("Location: dashboard.php");
    } else if ($_SESSION['role'] == 'customer') {
        header("Location: customer_dashboard.php");
    }
    exit();
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check Admin first
    $stmt = $conn->prepare("SELECT admin_id, password, role FROM Admin WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_password, $role);
        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['role'] = $role;
            
            // Redirect based on role
            if ($role == 'owner') {
                header("Location: dashboard.php");
            } else if ($role == 'manager') {
                header("Location: manager_dashboard.php");
            }
            exit();
        }
        $stmt->close();
    }

    // Check Customer
    $stmt = $conn->prepare("SELECT customer_id, password FROM Customer WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password);
    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = 'customer';
        header("Location: customer_dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1631718110622-31bce46915f7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            padding: 2rem;
        }
        
        .login-form {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: var(--gray);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 163, 224, 0.2);
        }
        
        .login-btn {
            width: 100%;
            padding: 0.9rem;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #006A9E 100%);
            transform: translateY(-2px);
        }
        
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 0.5rem;
            font-weight: 500;
        }
        
        .login-links a:hover {
            text-decoration: underline;
        }
        
        .logo-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
            transition: opacity 0.3s;
        }

        .logo-link:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <a href="index.php" class="logo-link">
                <i class="fas fa-gas-pump"></i>
                <span>FuelGo</span>
            </a>
        </div>
        <div class="user-info">
            <a href="index.php" class="btn">Back to Home</a>
        </div>
    </header>

    <div class="login-container">
        <div class="login-form">
            <div class="login-header">
                <h1><i class="fas fa-user-shield"></i> Login</h1>
                <p>Access your account to manage your stations</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="login-links">
                <p>Don't have an account?</p>
                <div>
                    <a href="owner_registration.php">Owner</a> | 
                    <a href="manager_registration.php">Manager</a> | 
                    <a href="customer_registration.php">Customer</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>