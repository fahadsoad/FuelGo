<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'owner' && $_SESSION['role'] != 'manager')) {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$sid = $_GET['sid'] ?? null;
if (!$sid) {
    die("No station ID provided.");
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$authorized = false;
if ($role == 'owner') {
    $stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($owner_id);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $sid, $owner_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $authorized = true;
    }
    $stmt->close();
} elseif ($role == 'manager') {
    $stmt = $conn->prepare("SELECT station_id FROM Manager WHERE admin_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($manager_station_id);
    $stmt->fetch();
    $stmt->close();
    if ($manager_station_id == $sid) {
        $authorized = true;
    }
}

if (!$authorized) {
    die("Unauthorized access.");
}

// Fetch station details
$stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$station = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch owner name
$owner_name = '';
if ($station['owner_id']) {
    $stmt = $conn->prepare("SELECT a.name FROM Admin a JOIN Owner o ON a.admin_id = o.admin_id WHERE o.owner_id = ?");
    $stmt->bind_param("i", $station['owner_id']);
    $stmt->execute();
    $stmt->bind_result($owner_name);
    $stmt->fetch();
    $stmt->close();
}

// Fetch food corner details
$stmt = $conn->prepare("SELECT * FROM Food_Corner WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc() ?? ['dry_food' => 'no', 'set_menu' => 'no', 'drinks' => 'no'];
$stmt->close();

// Fetch reviews
$reviews = [];
$stmt = $conn->prepare("SELECT review_text, rating, review_date FROM Review WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($role == 'owner') {
        $station_name = $_POST['station_name'];
        $location = $_POST['location'];
        $capacity = $_POST['capacity'];
        $octane_amount = $_POST['octane_amount'] ?? 0;
        $diesel_amount = $_POST['diesel_amount'] ?? 0;
        $petrol_amount = $_POST['petrol_amount'] ?? 0;

        $stmt = $conn->prepare("UPDATE Station SET station_name = ?, location = ?, capacity = ?, octane_amount = ?, diesel_amount = ?, petrol_amount = ? WHERE station_id = ?");
        $stmt->bind_param("ssidddi", $station_name, $location, $capacity, $octane_amount, $diesel_amount, $petrol_amount, $sid);
        $stmt->execute();
        $stmt->close();

        if (isset($_POST['delete_station'])) {
            $stmt = $conn->prepare("DELETE FROM Station WHERE station_id = ?");
            $stmt->bind_param("i", $sid);
            $stmt->execute();
            $stmt->close();
            header("Location: dashboard.php");
            exit();
        }
    } elseif ($role == 'manager') {
        $fuel_available = $_POST['fuel_available'];
        $gas_available = $_POST['gas_available'];
        $octane_available = $_POST['octane_available'];
        $diesel_available = $_POST['diesel_available'];
        $petrol_available = $_POST['petrol_available'];
        $total_sale = $_POST['total_sale'];

        $stmt = $conn->prepare("UPDATE Station SET fuel_available = ?, gas_available = ?, octane_available = ?, diesel_available = ?, petrol_available = ?, total_sale = ? WHERE station_id = ?");
        $stmt->bind_param("sssssdi", $fuel_available, $gas_available, $octane_available, $diesel_available, $petrol_available, $total_sale, $sid);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: station_dashboard.php?sid=$sid");
    exit();
}

// Update station status based on capacity
if ($station['service_count'] >= $station['capacity']) {
    $stmt = $conn->prepare("UPDATE Station SET station_status = 'off' WHERE station_id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE Station SET station_status = 'on' WHERE station_id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $stmt->close();
}

// Refresh station data after updates
$stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$station = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get today's bookings count
$today = date('Y-m-d');
$booking_stmt = $conn->prepare("SELECT COUNT(*) FROM Booking WHERE station_id = ? AND booking_date = ?");
$booking_stmt->bind_param("is", $sid, $today);
$booking_stmt->execute();
$booking_stmt->bind_result($today_bookings);
$booking_stmt->fetch();
$booking_stmt->close();

// Get total revenue
$revenue_stmt = $conn->prepare("SELECT SUM(total_amount) FROM Booking WHERE station_id = ? AND status = 'completed'");
$revenue_stmt->bind_param("i", $sid);
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
    <title>Station Dashboard - <?php echo htmlspecialchars($station['station_name']); ?></title>
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
            <h1 class="dashboard-title"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($station['station_name']); ?></h1>
        </div>

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
                <div class="stat-value">Taka: <?php echo number_format($total_revenue, 2); ?></div>
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
                <a href="order_booked.php?sid=<?php echo $sid; ?>" class="btn">Manage Bookings</a>
            </div>
            <div class="action-card">
                <i class="fas fa-utensils"></i>
                <h3>Food Corner</h3>
                <a href="#food-corner" class="btn">Manage Food</a>
            </div>
            <div class="action-card">
                <i class="fas fa-star"></i>
                <h3>Customer Reviews</h3>
                <a href="#reviews" class="btn">View Reviews</a>
            </div>
            <?php if ($role == 'owner'): ?>
            <div class="action-card">
                <i class="fas fa-edit"></i>
                <h3>Update Station</h3>
                <a href="#update-station" class="btn">Edit Details</a>
            </div>
            <?php else: ?>
            <div class="action-card">
                <i class="fas fa-edit"></i>
                <h3>Update Availability</h3>
                <a href="#update-availability" class="btn">Edit Availability</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=5" alt="Station Details">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-info-circle"></i> Station Details</h2>
                    <p><strong>ID:</strong> <?php echo $station['station_id']; ?></p>
                    <p><strong>Owner:</strong> <?php echo htmlspecialchars($owner_name); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($station['location']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo $station['capacity']; ?></p>
                    <p><strong>Current Customers:</strong> <?php echo $station['service_count']; ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $station['station_status']; ?>"><?php echo $station['station_status']; ?></span></p>
                    <p><strong>Fuel Available:</strong> <span class="highlight"><?php echo $station['fuel_available']; ?></span></p>
                    <p><strong>Gas Available:</strong> <span class="highlight"><?php echo $station['gas_available']; ?></span></p>
                    <p><strong>Octane Available:</strong> <span class="highlight"><?php echo $station['octane_available']; ?> (<?php echo $station['octane_amount']; ?> liters)</span></p>
                    <p><strong>Diesel Available:</strong> <span class="highlight"><?php echo $station['diesel_available']; ?> (<?php echo $station['diesel_amount']; ?> liters)</span></p>
                    <p><strong>Petrol Available:</strong> <span class="highlight"><?php echo $station['petrol_available']; ?> (<?php echo $station['petrol_amount']; ?> liters)</span></p>
                    <p><strong>Cash Amount:</strong> <span class="highlight"><?php echo $station['cash_amount']; ?> Taka</span></p>
                    <p><strong>Total Sale:</strong> <span class="highlight"><?php echo $station['total_sale']; ?> Taka</span></p>
                </div>
            </div>
            
            <div class="card" id="food-corner">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=6" alt="Food Corner">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-utensils"></i> Food Corner</h2>
                    <p><strong>Dry Food:</strong> <?php echo $food['dry_food']; ?></p>
                    <p><strong>Set Menu:</strong> <?php echo $food['set_menu']; ?></p>
                    <p><strong>Drinks:</strong> <?php echo $food['drinks']; ?></p>
                    <a href="#" class="btn">Manage Options</a>
                </div>
            </div>
            
            <div class="card" id="reviews">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=7" alt="Customer Reviews">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-star"></i> Customer Reviews</h2>
                    <?php if (empty($reviews)): ?>
                        <p>No reviews yet.</p>
                    <?php else: ?>
                        <ul class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <li>
                                <strong>Rating:</strong> <span class="rating"><?php echo $review['rating']; ?>/5</span> <i class="fas fa-star"></i><br>
                                <strong>Review:</strong> <?php echo htmlspecialchars($review['review_text']); ?><br>
                                <strong>Date:</strong> <?php echo $review['review_date']; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($role == 'owner'): ?>
            <div class="card" id="update-station">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=8" alt="Update Station">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-edit"></i> Update Station</h2>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="station_name">Station Name:</label>
                            <input type="text" name="station_name" value="<?php echo htmlspecialchars($station['station_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($station['location']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="capacity">Capacity:</label>
                            <input type="number" name="capacity" value="<?php echo $station['capacity']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="octane_amount">Octane Amount (liters):</label>
                            <input type="number" step="0.01" name="octane_amount" value="<?php echo $station['octane_amount']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="diesel_amount">Diesel Amount (liters):</label>
                            <input type="number" step="0.01" name="diesel_amount" value="<?php echo $station['diesel_amount']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="petrol_amount">Petrol Amount (liters):</label>
                            <input type="number" step="0.01" name="petrol_amount" value="<?php echo $station['petrol_amount']; ?>">
                        </div>
                        <button type="submit" class="btn">Update</button>
                        <button type="submit" name="delete_station" class="btn" style="background: linear-gradient(to right, #e74c3c, #c0392b); margin-left: 10px;">Delete Station</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card" id="update-availability">
                <div class="card-img">
                    <img src="https://picsum.photos/400/250?random=9" alt="Update Availability">
                </div>
                <div class="card-content">
                    <h2><i class="fas fa-edit"></i> Update Availability</h2>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="fuel_available">Fuel Available:</label>
                            <select name="fuel_available">
                                <option value="yes" <?php if ($station['fuel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['fuel_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gas_available">Gas Available:</label>
                            <select name="gas_available">
                                <option value="yes" <?php if ($station['gas_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['gas_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="octane_available">Octane Available:</label>
                            <select name="octane_available">
                                <option value="yes" <?php if ($station['octane_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['octane_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="diesel_available">Diesel Available:</label>
                            <select name="diesel_available">
                                <option value="yes" <?php if ($station['diesel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['diesel_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="petrol_available">Petrol Available:</label>
                            <select name="petrol_available">
                                <option value="yes" <?php if ($station['petrol_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($station['petrol_available'] == 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="total_sale">Total Sale (Taka):</label>
                            <input type="number" step="0.01" name="total_sale" value="<?php echo $station['total_sale']; ?>" required>
                        </div>
                        <button type="submit" class="btn">Update</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Sidebar toggle functionality
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', (event) => {
                if (sidebar.classList.contains('active') && 
                    !sidebar.contains(event.target) && 
                    event.target !== sidebarToggle) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>