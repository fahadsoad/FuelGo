<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get owner_id from session
$stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($owner_id);
$stmt->fetch();
$stmt->close();

if (!$owner_id) {
    die("Owner not found. Please contact support.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $station_name = $_POST['station_name'];
    $location = $_POST['location'];
    $capacity = $_POST['capacity'];
    $octane_amount = $_POST['octane_amount'] ?? 0;
    $diesel_amount = $_POST['diesel_amount'] ?? 0;
    $petrol_amount = $_POST['petrol_amount'] ?? 0;
    
    // Insert new station
    $stmt = $conn->prepare("INSERT INTO Station (station_name, owner_id, location, capacity, octane_amount, diesel_amount, petrol_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisiddd", $station_name, $owner_id, $location, $capacity, $octane_amount, $diesel_amount, $petrol_amount);
    
    if ($stmt->execute()) {
        $success = "Station added successfully!";
        
        // Get the new station ID to create food corner entry
        $new_station_id = $stmt->insert_id;
        
        // Create food corner entry for the new station
        $food_stmt = $conn->prepare("INSERT INTO Food_Corner (station_id) VALUES (?)");
        $food_stmt->bind_param("i", $new_station_id);
        $food_stmt->execute();
        $food_stmt->close();
        
        // Clear form
        $_POST = array();
    } else {
        $error = "Error adding station: " . $conn->error;
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
    <title>Add New Station - FuelGo</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--dark);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
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
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 163, 224, 0.2);
        }
        
        .form-actions {
            grid-column: span 2;
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                grid-column: span 1;
                flex-direction: column;
                gap: 1rem;
            }
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
            <a href="dashboard.php" class="logo-link">
                <i class="fas fa-gas-pump"></i>
                <span>FuelGo</span>
            </a>
        </div>
        <div class="user-info">
            <span>Welcome, Owner</span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-plus-circle"></i> Add New Station</h1>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form action="add_station.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="station_name">Station Name *</label>
                        <input type="text" id="station_name" name="station_name" value="<?php echo isset($_POST['station_name']) ? htmlspecialchars($_POST['station_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="capacity">Capacity (Number of vehicles) *</label>
                        <input type="number" id="capacity" name="capacity" min="1" value="<?php echo isset($_POST['capacity']) ? htmlspecialchars($_POST['capacity']) : '10'; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="octane_amount">Initial Octane Amount (Liters)</label>
                        <input type="number" id="octane_amount" name="octane_amount" step="0.01" min="0" value="<?php echo isset($_POST['octane_amount']) ? htmlspecialchars($_POST['octane_amount']) : '0'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="diesel_amount">Initial Diesel Amount (Liters)</label>
                        <input type="number" id="diesel_amount" name="diesel_amount" step="0.01" min="0" value="<?php echo isset($_POST['diesel_amount']) ? htmlspecialchars($_POST['diesel_amount']) : '0'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="petrol_amount">Initial Petrol Amount (Liters)</label>
                        <input type="number" id="petrol_amount" name="petrol_amount" step="0.01" min="0" value="<?php echo isset($_POST['petrol_amount']) ? htmlspecialchars($_POST['petrol_amount']) : '0'; ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn"><i class="fas fa-plus"></i> Add Station</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>