<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Fetch manager details to get station ID
$stmt = $conn->prepare("SELECT station_id FROM Manager WHERE admin_id = ?");
if (!$stmt) {
    die("Prepare failed (Manager fetch): " . $conn->error);
}
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($station_id);
if (!$stmt->fetch()) {
    die("Manager not assigned to any station. Please contact support.");
}
$stmt->close();

if (!$station_id) {
    die("Station ID not retrieved.");
}

// Fetch station details
$stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$stmt->bind_param("i", $station_id);
$stmt->execute();
$result = $stmt->get_result();
$station = $result->fetch_assoc();
$stmt->close();

// Handle form submission for updating availability
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fuel_available = $_POST['fuel_available'];
    $gas_available = $_POST['gas_available'];
    $octane_available = $_POST['octane_available'];
    $diesel_available = $_POST['diesel_available'];
    $petrol_available = $_POST['petrol_available'];
    $total_sale = $_POST['total_sale'];

    $update_stmt = $conn->prepare("UPDATE Station SET fuel_available = ?, gas_available = ?, octane_available = ?, diesel_available = ?, petrol_available = ?, total_sale = ? WHERE station_id = ?");
    $update_stmt->bind_param("sssssdi", $fuel_available, $gas_available, $octane_available, $diesel_available, $petrol_available, $total_sale, $station_id);
    
    if ($update_stmt->execute()) {
        $success = "Station details updated successfully!";
        // Refresh station data
        $refresh_stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
        $refresh_stmt->bind_param("i", $station_id);
        $refresh_stmt->execute();
        $result = $refresh_stmt->get_result();
        $station = $result->fetch_assoc();
        $refresh_stmt->close();
    } else {
        $error = "Error updating station details: " . $conn->error;
    }
    $update_stmt->close();
}

// Update station status based on capacity
if ($station['service_count'] >= $station['capacity']) {
    $status_stmt = $conn->prepare("UPDATE Station SET station_status = 'off' WHERE station_id = ?");
    $status_stmt->bind_param("i", $station_id);
    $status_stmt->execute();
    $status_stmt->close();
} else {
    $status_stmt = $conn->prepare("UPDATE Station SET station_status = 'on' WHERE station_id = ?");
    $status_stmt->bind_param("i", $station_id);
    $status_stmt->execute();
    $status_stmt->close();
}

// Refresh station data after updates
$refresh_stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$refresh_stmt->bind_param("i", $station_id);
$refresh_stmt->execute();
$result = $refresh_stmt->get_result();
$station = $result->fetch_assoc();
$refresh_stmt->close();

// Get today's bookings count
$today = date('Y-m-d');
$booking_stmt = $conn->prepare("SELECT COUNT(*) FROM Booking WHERE station_id = ? AND booking_date = ?");
$booking_stmt->bind_param("is", $station_id, $today);
$booking_stmt->execute();
$booking_stmt->bind_result($today_bookings);
$booking_stmt->fetch();
$booking_stmt->close();

// Get total revenue
$revenue_stmt = $conn->prepare("SELECT SUM(total_amount) FROM Booking WHERE station_id = ? AND status = 'completed'");
$revenue_stmt->bind_param("i", $station_id);
$revenue_stmt->execute();
$revenue_stmt->bind_result($total_revenue);
$revenue_stmt->fetch();
$revenue_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }

        .stat-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark);
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .action-card h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .stats-grid, .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <a href="javascript:history.back()" class="logo-link">
            <i class="fas fa-gas-pump"></i>
            <span>FuelGo</span>
        </a>
    </div>
    <div class="user-info">
        <span>Welcome, <?php echo $_SESSION['role']; ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<style>
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

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-tachometer-alt"></i> Manager Dashboard</h1>
        </div>

        <h2>Station: <?php echo htmlspecialchars($station['station_name']); ?></h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-car"></i>
                <div class="stat-value"><?php echo $station['service_count']; ?> / <?php echo $station['capacity']; ?></div>
                <div class="stat-label">Current Capacity</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <div class="stat-value"><?php echo $today_bookings; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stat-value">₮<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-gas-pump"></i>
                <div class="stat-value"><?php echo $station['station_status'] == 'on' ? 'Open' : 'Closed'; ?></div>
                <div class="stat-label">Station Status</div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card">
                <i class="fas fa-calendar-check"></i>
                <h3>View Bookings</h3>
                <a href="order_booked.php?sid=<?php echo $station_id; ?>" class="btn">Manage Bookings</a>
            </div>
            <div class="action-card">
                <i class="fas fa-edit"></i>
                <h3>Update Availability</h3>
                <button class="btn" onclick="document.getElementById('update-form').scrollIntoView({behavior: 'smooth'})">Update Now</button>
            </div>
            <div class="action-card">
                <i class="fas fa-utensils"></i>
                <h3>Food Corner</h3>
                <a href="station_dashboard.php?sid=<?php echo $station_id; ?>#food-corner" class="btn">Manage Food</a>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=5" alt="Station Details">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-info-circle"></i> Station Details</h2>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($station['station_name']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($station['location']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo $station['capacity']; ?></p>
                    <p><strong>Current Customers:</strong> <?php echo $station['service_count']; ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $station['station_status']; ?>"><?php echo $station['station_status']; ?></span></p>
                    <p><strong>Fuel Available:</strong> <span class="highlight"><?php echo $station['fuel_available']; ?></span></p>
                    <p><strong>Gas Available:</strong> <span class="highlight"><?php echo $station['gas_available']; ?></span></p>
                    <p><strong>Octane Available:</strong> <span class="highlight"><?php echo $station['octane_available']; ?> (<?php echo $station['octane_amount']; ?> liters)</span></p>
                    <p><strong>Diesel Available:</strong> <span class="highlight"><?php echo $station['diesel_available']; ?> (<?php echo $station['diesel_amount']; ?> liters)</span></p>
                    <p><strong>Petrol Available:</strong> <span class="highlight"><?php echo $station['petrol_available']; ?> (<?php echo $station['petrol_amount']; ?> liters)</span></p>
                    <p><strong>Total Sale:</strong> <span class="highlight"><?php echo $station['total_sale']; ?> Taka</span></p>
                </div>
            </div>

            <div class="card" id="update-form">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=6" alt="Update Availability">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-edit"></i> Update Availability</h2>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="fuel_available">Fuel Available:</label>
                            <select name="fuel_available" id="fuel_available">
                                <option value="yes" <?php if ($station['fuel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['fuel_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gas_available">Gas Available:</label>
                            <select name="gas_available" id="gas_available">
                                <option value="yes" <?php if ($station['gas_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['gas_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="octane_available">Octane Available:</label>
                            <select name="octane_available" id="octane_available">
                                <option value="yes" <?php if ($station['octane_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['octane_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="diesel_available">Diesel Available:</label>
                            <select name="diesel_available" id="diesel_available">
                                <option value="yes" <?php if ($station['diesel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['diesel_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="petrol_available">Petrol Available:</label>
                            <select name="petrol_available" id="petrol_available">
                                <option value="yes" <?php if ($station['petrol_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['petrol_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="total_sale">Total Sale (Taka):</label>
                            <input type="number" step="0.01" name="total_sale" id="total_sale" value="<?php echo $station['total_sale']; ?>" required>
                        </div>
                        <button type="submit" class="btn">Update Availability</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>