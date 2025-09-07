<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$admin_id = $_SESSION['user_id'];

// Fetch owner details to link stations
$stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
if (!$stmt) {
    die("Prepare failed (Owner fetch): " . $conn->error);
}
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($owner_id);
if (!$stmt->fetch()) {
    die("Owner not found. Please contact support.");
}
$stmt->close();

if (!$owner_id) {
    die("Owner ID not retrieved.");
}

// Get stations for this owner
$stmt = $conn->prepare("SELECT station_id, station_name, location, capacity, fuel_available, gas_available, octane_available, diesel_available, petrol_available, octane_amount, diesel_amount, petrol_amount, cash_amount, station_status, service_count, total_sale FROM Station WHERE owner_id = ?");
if (!$stmt) {
    // Fallback query with original columns
    $stmt = $conn->prepare("SELECT station_id, station_name, location, capacity, fuel_available, gas_available, station_status, service_count, cash_amount, total_sale FROM Station WHERE owner_id = ?");
    if (!$stmt) {
        die("Prepare failed (Fallback query): " . $conn->error);
    }
}
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    die("Query execution failed: " . $stmt->error);
}

// Get overall stats for the owner
$total_stations = $result->num_rows;
$total_revenue = 0;
$total_capacity = 0;
$active_stations = 0;

while ($row = $result->fetch_assoc()) {
    $total_revenue += $row['total_sale'];
    $total_capacity += $row['capacity'];
    if ($row['station_status'] == 'on') {
        $active_stations++;
    }
}

// Reset pointer for later use
$result->data_seek(0);

// Get manager count for this owner
$manager_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM Manager m 
    JOIN Station s ON m.station_id = s.station_id 
    WHERE s.owner_id = ?
");
$manager_stmt->bind_param("i", $owner_id);
$manager_stmt->execute();
$manager_stmt->bind_result($total_managers);
$manager_stmt->fetch();
$manager_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Gas & Fuel Station</title>
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
        
        .manager-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close-modal {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
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
            <h1 class="dashboard-title"><i class="fas fa-tachometer-alt"></i> Owner Dashboard</h1>
            <a href="add_station.php" class="btn">Add New Station</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-building"></i>
                <div class="stat-value"><?php echo $total_stations; ?></div>
                <div class="stat-label">Total Stations</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $total_managers; ?></div>
                <div class="stat-label">Total Managers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stat-value">Taka: <?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="stat-value"><?php echo $active_stations; ?></div>
                <div class="stat-label">Active Stations</div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Station</h3>
                <a href="add_station.php" class="btn">Add New Station</a>
            </div>
            <div class="action-card">
                <i class="fas fa-users"></i>
                <h3>Manage Managers</h3>
                <button class="btn" onclick="openManagerModal()">Manage Managers</button>
            </div>
        </div>

        <h2>Your Stations</h2>
        <div class="dashboard-grid">
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='card'>";
                    echo "<div class='card-img'>";
                    echo "<img src='https://picsum.photos/400/250?random=" . $row['station_id'] . "' alt='Station Image'>";
                    echo "</div>";
                    echo "<div class='card-content'>";
                    echo "<h2><i class='fas fa-gas-pump'></i> " . htmlspecialchars($row['station_name']) . "</h2>";
                    echo "<p><strong>Location:</strong> " . htmlspecialchars($row['location']) . "</p>";
                    echo "<p><strong>Capacity:</strong> " . $row['capacity'] . " (Vacancy: " . ($row['capacity'] - $row['service_count']) . ")</p>";
                    echo "<p><strong>Fuel Status:</strong> " . $row['fuel_available'] . ", Gas: " . $row['gas_available'] . "</p>";
                    if (isset($row['octane_available'])) {
                        echo "<p><strong>Fuel Types:</strong> Octane: " . $row['octane_available'] . " (" . $row['octane_amount'] . "L), Diesel: " . $row['diesel_available'] . " (" . $row['diesel_amount'] . "L), Petrol: " . $row['petrol_available'] . " (" . $row['petrol_amount'] . "L)</p>";
                        echo "<p><strong>Cash:</strong> " . $row['cash_amount'] . " Taka</p>";
                        echo "<p><strong>Total Sale:</strong> " . $row['total_sale'] . " Taka</p>";
                    } else {
                        echo "<p><strong>Cash:</strong> " . $row['cash_amount'] . " Taka</p>";
                        echo "<p><strong>Total Sale:</strong> " . $row['total_sale'] . " Taka</p>";
                    }
                    echo "<p><strong>Status:</strong> <span class='status-badge status-" . $row['station_status'] . "'>" . $row['station_status'] . "</span></p>";
                    echo "<a href='station_dashboard.php?sid=" . $row['station_id'] . "' class='btn'>View Station</a>";
                    echo "<a href='order_booked.php?sid=" . $row['station_id'] . "' class='btn' style='margin-top: 10px;'><i class='fas fa-calendar-check'></i> View Bookings</a>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<div class='card'>";
                echo "<div class='card-img'>";
                echo "<img src='https://picsum.photos/400/250?random=0' alt='No Stations Image'>";
                echo "</div>";
                echo "<div class='card-content'>";
                echo "<h2>No Stations Yet</h2>";
                echo "<p>Register a new station to get started.</p>";
                echo "<a href='owner_registration.php' class='btn'>Add Station</a>";
                echo "</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
    
    <!-- Manager Management Modal -->
    <div id="managerModal" class="manager-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeManagerModal()">&times;</span>
            <h2><i class="fas fa-users"></i> Manage Managers</h2>
            
            <div class="form-container">
                <h3>Assign Manager to Station</h3>
                <form id="assignManagerForm">
                    <div class="form-group">
                        <label for="manager_email">Manager Email:</label>
                        <input type="email" id="manager_email" name="manager_email" required>
                    </div>
                    <div class="form-group">
                        <label for="station_select">Select Station:</label>
                        <select id="station_select" name="station_id" required>
                            <option value="">Select a Station</option>
                            <?php
                            // Reset pointer and loop through stations again
                            $result->data_seek(0);
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . $row['station_id'] . "'>" . htmlspecialchars($row['station_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Assign Manager</button>
                </form>
            </div>
            
            <div class="current-managers" style="margin-top: 2rem;">
                <h3>Current Managers</h3>
                <div id="managersList">
                    <p>Loading managers...</p>
                </div>
            </div>
        </div>
    </div>
    
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>

    <script>
        // Manager modal functionality
        function openManagerModal() {
            document.getElementById('managerModal').style.display = 'flex';
            loadManagers();
        }
        
        function closeManagerModal() {
            document.getElementById('managerModal').style.display = 'none';
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('managerModal');
            if (event.target === modal) {
                closeManagerModal();
            }
        }
        
        // Load managers for the owner's stations
        function loadManagers() {
            const managersList = document.getElementById('managersList');
            managersList.innerHTML = '<p>Loading managers...</p>';
            
        
        }
        
        // Handle form submission for assigning managers
        document.getElementById('assignManagerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('manager_email').value;
            const stationId = document.getElementById('station_select').value;
            
            // In a real application, this would send data to the server
            alert(`Manager ${email} would be assigned to station ID ${stationId}`);
            
            // Clear form
            document.getElementById('assignManagerForm').reset();
        });
    </script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>