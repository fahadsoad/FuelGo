<?php
session_start();
include 'db_connect.php';
date_default_timezone_set('Asia/Dhaka');
$current_time = date('h:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FuelGo - Premium Fuel Station Management</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="scripts.js" defer></script>
</head>
<body>
    <canvas id="particle-canvas"></canvas>
    
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
    

    <section class="hero">
        <h2>Professional Fuel Station Management System</h2>
        <p>Efficiently manage your fuel stations with real-time monitoring, inventory control, and customer management.</p>
        <div class="datetime">
            <?php echo $current_date; ?> | <?php echo $current_time; ?>
        </div>
    </section>

    <div class="dashboard-grid">
        <div class="container">
            <div class="card-content">
                <h2><i class="fas fa-gas-pump"></i> Fuel Management</h2>
                <p>Monitor fuel levels, track consumption, and manage inventory across all your stations in real-time.</p>
                <a href="login.php" class="btn">Access System</a>
            </div>
        </div>

        <div class="container">
            <div class="card-content">
                <h2><i class="fas fa-building"></i> Station Management</h2>
                <p>Register and manage multiple stations with detailed analytics and performance tracking.</p>
                <a href="owner_registration.php" class="btn">Register as Owner</a>
            </div>
        </div>

        <div class="container">
            <div class="card-content">
                <h2><i class="fas fa-user-tie"></i> Operations Control</h2>
                <p>Update fuel availability, track sales, and manage daily operations for your assigned station.</p>
                <a href="manager_registration.php" class="btn">Register as Manager</a>
            </div>
        </div>

        <div class="container">
            <div class="card-content">
                <h2><i class="fas fa-users"></i> Customer Services</h2>
                <p>Book services, preorder fuel, and provide feedback through our customer portal.</p>
                <a href="customer_registration.php" class="btn">Register as Customer</a>
            </div>
        </div>
    </div>

    <section class="features-section">
        <h2>Why Choose FuelGo?</h2>
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-bolt"></i>
                <h3>Real-Time Monitoring</h3>
                <p>Track fuel levels, sales, and station status in real-time from anywhere.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h3>Advanced Analytics</h3>
                <p>Gain insights with detailed reports and performance analytics.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-mobile-alt"></i>
                <h3>Mobile Access</h3>
                <p>Manage your stations on the go with our mobile-friendly interface.</p>
            </div>
        </div>
    </section>

    <section class="faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-grid">
            <div class="faq-item animate">
                <h3><i class="fas fa-clock"></i> What are the service hours?</h3>
                <p>Fuel management is available 24/7. Manager access is available from 6am to 12am daily.</p>
            </div>
            <div class="faq-item animate">
                <h3><i class="fas fa-map-marker-alt"></i> Where is service available?</h3>
                <p>Currently serving major cities including Dhaka, Chittagong, Sylhet, and expanding to new locations.</p>
            </div>
            <div class="faq-item animate">
                <h3><i class="fas fa-calendar-check"></i> How to manage multiple stations?</h3>
                <p>Register as an owner to access our multi-station management dashboard with centralized control.</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fas fa-envelope"></i> support@fuelgo.com</p>
                <p><i class="fas fa-phone"></i> +880-1611599775</p>
                <p><i class="fas fa-map-marker-alt"></i> Dhaka, Bangladesh</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <p><a href="login.php">Login</a></p>
                <p><a href="owner_registration.php">Station Ownership</a></p>
                <p><a href="manager_registration.php">Manager Registration</a></p>
            </div>
            <div class="footer-section">
                <h3>Connect With Us</h3>
                <p> Coming Soon! <p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 FuelGo. All rights reserved. | Professional Fuel Station Management System</p>
        </div>
    </footer>
</body>
</html>
<?php $conn->close(); ?>