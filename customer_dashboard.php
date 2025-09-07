<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Search by location
$location = $_GET['location'] ?? '';
$stations = [];
if ($location) {
    $stmt = $conn->prepare("SELECT * FROM Station WHERE location LIKE ?");
    $loc = "%$location%";
    $stmt->bind_param("s", $loc);
    $stmt->execute();
    $stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM Station");
    $stmt->execute();
    $stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    $station_id = $_POST['station_id'];
    $service_date = $_POST['service_date'];
    $service_time = $_POST['service_time'];
    $pre_booking = 'yes';
    $payment_status = 'pending'; // Assume online payment later

    $stmt = $conn->prepare("INSERT INTO Service (customer_id, station_id, service_date, service_time, pre_booking, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $user_id, $station_id, $service_date, $service_time, $pre_booking, $payment_status);
    $stmt->execute();
    $stmt->close();
}

// Handle review
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['review'])) {
    $station_id = $_POST['station_id'];
    $review_text = $_POST['review_text'];
    $rating = $_POST['rating'];

    $stmt = $conn->prepare("INSERT INTO Review (customer_id, station_id, review_text, rating) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $user_id, $station_id, $review_text, $rating);
    $stmt->execute();
    $stmt->close();
}

// Handle preorder food (assume add to Service or new table; here add as note in Service)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preorder'])) {
    $station_id = $_POST['station_id'];
    $food_note = "Preorder: Dry Food: " . $_POST['dry_food'] . ", Set Menu: " . $_POST['set_menu'] . ", Drinks: " . $_POST['drinks'];

    // Add to Service note or separate table; here use review_text as placeholder for simplicity
    $stmt = $conn->prepare("INSERT INTO Service (customer_id, station_id, pre_booking) VALUES (?, ?, 'yes')");
    $stmt->bind_param("ii", $user_id, $station_id);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
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
        
        .search-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
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
            <span>Welcome, Customer</span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-user"></i> Customer Dashboard</h1>
        </div>

        <div class="search-section">
            <h2>Search Stations</h2>
            <form action="" method="GET" class="search-form">
                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
                </div>
                <button type="submit" class="btn">Search</button>
            </form>
        </div>

        <div class="dashboard-grid">
            <?php foreach ($stations as $station): ?>
                <div class="card">
                    <div class="card-img">
                        <img src="https://picsum.photos/400/250?random=<?php echo $station['station_id']; ?>" alt="Station">
                    </div>
                    <div class="card-content">
                        <h2><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($station['station_name']); ?></h2>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($station['location']); ?></p>
                        <p><strong>Availability:</strong> Fuel: <?php echo $station['fuel_available']; ?>, Gas: <?php echo $station['gas_available']; ?></p>
                        <p><strong>Capacity Vacancy:</strong> <?php echo $station['capacity'] - $station['service_count']; ?> spots</p>
                        <p><strong>Fuel Types:</strong> Octane: <?php echo $station['octane_available']; ?>, Diesel: <?php echo $station['diesel_available']; ?>, Petrol: <?php echo $station['petrol_available']; ?></p>
                        
                        <form method="POST">
                            <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                            <div class="form-group">
                                <label for="service_date">Date:</label>
                                <input type="date" name="service_date" required>
                            </div>
                            <div class="form-group">
                                <label for="service_time">Time:</label>
                                <input type="time" name="service_time" required>
                            </div>
                            <button type="submit" name="book" class="btn">Book Service</button>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                            <h3>Preorder Food</h3>
                            <div style="margin-bottom: 1rem;">
                                <label><input type="checkbox" name="dry_food"> Dry Food</label>
                                <label><input type="checkbox" name="set_menu"> Set Menu</label>
                                <label><input type="checkbox" name="drinks"> Drinks</label>
                            </div>
                            <button type="submit" name="preorder" class="btn">Preorder</button>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                            <h3>Give Review</h3>
                            <div class="form-group">
                                <textarea name="review_text" placeholder="Your review" required style="width: 100%; padding: 0.9rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;"></textarea>
                            </div>
                            <div class="form-group">
                                <select name="rating" required style="width: 100%; padding: 0.9rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 Star</option>
                                    <option value="2">2 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="5">5 Stars</option>
                                </select>
                            </div>
                            <button type="submit" name="review" class="btn">Submit Review</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>