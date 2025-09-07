<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gas_fuel_station";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = $success = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? "";
    $email = $_POST['email'] ?? "";
    $phone = $_POST['phone'] ?? "";
    if (empty($email) && empty($phone)) {
        $error = "Email or phone required.";
    } else {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO Customer (name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $pass);
        if ($stmt->execute()) {
            $success = "Registration successful! Redirecting to login...";
            header("Refresh: 2; url=login.php");
        } else {
            $error = "Email/phone already exists.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
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
        <?php if (isset($_SESSION['user_id'])): ?>
            <span>Welcome, <?php echo $_SESSION['role']; ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn">Login</a>
        <?php endif; ?>
    </div>
</header>
    <header>
        <h1><i class="fas fa-user-plus"></i> Customer Registration</h1>
    </header>
    <div class="container">
        <div class="card">
            <h2>Join Us</h2>
            <p>Register to book services and preorder food.</p>
            <?php if ($error) echo "<p style='color: #DC3545;'>$error</p>"; ?>
            <?php if ($success) echo "<p style='color: #28A745;'>$success</p>"; ?>
            <form action="customer_registration.php" method="POST">
                <div class="form-group">
                    <label for="name">Name (optional):</label>
                    <input type="text" id="name" name="name">
                </div>
                <div class="form-group">
                    <label for="email">Email (optional if phone provided):</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group">
                    <label for="phone">Phone (optional if email provided):</label>
                    <input type="text" id="phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <input type="submit" value="Register" class="btn">
            </form>
            <p>Already have an account? <a href="login.php" class="nav-link">Login</a></p>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 Gas & Fuel Station</p>
    </footer>
</body>
</html>