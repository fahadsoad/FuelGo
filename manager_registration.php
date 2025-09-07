<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username = "root";
$password = ""; // Update if needed
$dbname = "gas_fuel_station";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = $success = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? "";
    $phone = $_POST['phone'] ?? "";
    if (empty($email) && empty($phone)) {
        $error = "Email or phone required.";
    } else {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $station_name = $_POST['station_name'];

        // Find station_id by name
        $stmt = $conn->prepare("SELECT station_id FROM Station WHERE station_name = ?");
        $stmt->bind_param("s", $station_name);
        $stmt->execute();
        $stmt->bind_result($station_id);
        if ($stmt->fetch()) {
            $stmt->close();

            // Insert into Admin
            $stmt = $conn->prepare("INSERT INTO Admin (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'manager')");
            $name = ""; // Optional
            $stmt->bind_param("ssss", $name, $email, $phone, $pass);
            if ($stmt->execute()) {
                $admin_id = $stmt->insert_id;
                $stmt->close();

                // Insert into Manager
                $stmt = $conn->prepare("INSERT INTO Manager (admin_id, station_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $admin_id, $station_id);
                if ($stmt->execute()) {
                    $success = "Registration successful! Redirecting to login...";
                    header("Refresh: 2; url=login.php");
                } else {
                    $error = "Failed to assign station.";
                }
                $stmt->close();
            } else {
                $error = "Email/phone already exists or other error.";
            }
        } else {
            $error = "Station not found.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Registration - Gas & Fuel Station</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
    <div class="container">
        <h1><i class="fas fa-user-plus"></i> Manager Registration</h1>
        <?php if ($error) echo "<p style='color: #DC3545;'>$error</p>"; ?>
        <?php if ($success) echo "<p style='color: #28A745;'>$success</p>"; ?>
        <form action="manager_registration.php" method="POST">
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
            <div class="form-group">
                <label for="station_name">Station Name:</label>
                <input type="text" id="station_name" name="station_name" required>
            </div>
            <input type="submit" value="Register">
        </form>
        <p>Already have an account? <a href="login.php" class="nav-link">Login</a></p>
    </div>
</body>
</html>