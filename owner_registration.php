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
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $station_name = $_POST['station_name'];

    // Insert into Admin (name and phone optional/empty)
    $stmt = $conn->prepare("INSERT INTO Admin (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'owner')");
    $name = ""; // Optional; add field if needed
    $phone = ""; // Optional
    $stmt->bind_param("ssss", $name, $email, $phone, $pass);
    if ($stmt->execute()) {
        $admin_id = $stmt->insert_id;
        $stmt->close();

        // Insert into Owner
        $stmt = $conn->prepare("INSERT INTO Owner (admin_id) VALUES (?)");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $owner_id = $stmt->insert_id;
        $stmt->close();

        // Insert into Station (basic fields; update others later)
        $stmt = $conn->prepare("INSERT INTO Station (station_name, owner_id, capacity) VALUES (?, ?, 10)"); // Default capacity
        $stmt->bind_param("si", $station_name, $owner_id);
        if ($stmt->execute()) {
            $success = "Registration successful! Redirecting to login...";
            header("Refresh: 2; url=login.php");
        } else {
            $error = "Failed to create station.";
        }
        $stmt->close();
    } else {
        $error = "Email already exists or other error.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Registration - Gas & Fuel Station</title>
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
        <h1><i class="fas fa-user-plus"></i> Owner Registration</h1>
        <?php if ($error) echo "<p style='color: #DC3545;'>$error</p>"; ?>
        <?php if ($success) echo "<p style='color: #28A745;'>$success</p>"; ?>
        <form action="owner_registration.php" method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
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