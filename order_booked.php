<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'owner' && $_SESSION['role'] != 'manager')) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

$station_id = $_GET['station_id'] ?? null;

// Check if user has access to this station
if ($_SESSION['role'] == 'manager') {
    $stmt = $conn->prepare("SELECT station_id FROM Manager WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($manager_station_id);
    $stmt->fetch();
    $stmt->close();
    
    if ($station_id && $station_id != $manager_station_id) {
        die("Unauthorized access to this station.");
    }
    $station_id = $manager_station_id;
} else if ($_SESSION['role'] == 'owner' && $station_id) {
    // Verify owner has access to this station
    $stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($owner_id);
    $stmt->fetch();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT station_id FROM Station WHERE station_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $station_id, $owner_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        die("Unauthorized access to this station.");
    }
    $stmt->close();
}

// Get station name
$station_name = "All Stations";
if ($station_id) {
    $stmt = $conn->prepare("SELECT station_name FROM Station WHERE station_id = ?");
    $stmt->bind_param("i", $station_id);
    $stmt->execute();
    $stmt->bind_result($station_name);
    $stmt->fetch();
    $stmt->close();
}

// Get bookings
if ($station_id) {
    $stmt = $conn->prepare("
        SELECT b.*, c.name as customer_name, c.phone as customer_phone 
        FROM Booking b 
        JOIN Customer c ON b.customer_id = c.customer_id 
        WHERE b.station_id = ? 
        ORDER BY b.booking_date DESC, b.booking_time DESC
    ");
    $stmt->bind_param("i", $station_id);
} else {
    // Owner viewing all stations
    $stmt = $conn->prepare("
        SELECT b.*, c.name as customer_name, c.phone as customer_phone, s.station_name 
        FROM Booking b 
        JOIN Customer c ON b.customer_id = c.customer_id 
        JOIN Station s ON b.station_id = s.station_id 
        ORDER BY b.booking_date DESC, b.booking_time DESC
    ");
}
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Dashboard - FuelGo Station Management</title>
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

        .filters {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .filter-group select, .filter-group input {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-paid {
            background: #d4edda;
            color: #155724;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            margin-right: 0.5rem;
            color: var(--primary);
            transition: color 0.3s;
        }

        .action-btn:hover {
            color: var(--primary-dark);
        }

        .btn-danger {
            color: var(--danger);
        }

        .btn-danger:hover {
            color: #c0392b;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:hover:not(.active) {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-gas-pump"></i>
            <span>FuelGo</span>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo $_SESSION['role']; ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-calendar-check"></i> Order Booking Dashboard</h1>
            <div>
                <?php if ($station_id): ?>
                    <a href="station_dashboard.php?sid=<?php echo $station_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Station</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <h2>Station: <?php echo htmlspecialchars($station_name); ?></h2>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-list-alt"></i>
                <div class="stat-value"><?php echo $bookings->num_rows; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-value">
                    <?php
                    $pending = 0;
                    foreach ($bookings as $booking) {
                        if ($booking['status'] == 'pending') $pending++;
                    }
                    echo $pending;
                    ?>
                </div>
                <div class="stat-label">Pending Confirmation</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="stat-value">
                    <?php
                    $confirmed = 0;
                    foreach ($bookings as $booking) {
                        if ($booking['status'] == 'confirmed') $confirmed++;
                    }
                    echo $confirmed;
                    ?>
                </div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-times-circle"></i>
                <div class="stat-value">
                    <?php
                    $cancelled = 0;
                    foreach ($bookings as $booking) {
                        if ($booking['status'] == 'cancelled') $cancelled++;
                    }
                    echo $cancelled;
                    ?>
                </div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label for="date-filter">Date</label>
                <input type="date" id="date-filter" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label for="status-filter">Status</label>
                <select id="status-filter">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="service-filter">Service Type</label>
                <select id="service-filter">
                    <option value="all">All Services</option>
                    <option value="fuel">Fuel</option>
                    <option value="gas">Gas</option>
                    <option value="food">Food</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" placeholder="Customer name or phone">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button class="btn" id="apply-filters"><i class="fas fa-filter"></i> Apply Filters</button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Service Type</th>
                        <th>Date & Time</th>
                        <th>Fuel Type</th>
                        <th>Quantity</th>
                        <th>Total (₮)</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td>#BK<?php echo str_pad($booking['booking_id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($booking['customer_name']); ?><br><small><?php echo htmlspecialchars($booking['customer_phone']); ?></small></td>
                        <td><?php echo ucfirst($booking['booking_type']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])); ?></td>
                        <td><?php echo $booking['fuel_type'] != 'none' ? ucfirst($booking['fuel_type']) : '-'; ?></td>
                        <td><?php echo $booking['quantity'] . ($booking['booking_type'] == 'food' ? ' sets' : ' L'); ?></td>
                        <td><?php echo number_format($booking['total_amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        <td><span class="status-badge payment-<?php echo $booking['payment_status']; ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                        <td>
                            <button class="action-btn" title="View Details" onclick="viewBooking(<?php echo $booking['booking_id']; ?>)"><i class="fas fa-eye"></i></button>
                            <?php if ($booking['status'] == 'pending'): ?>
                                <button class="action-btn" title="Confirm" onclick="confirmBooking(<?php echo $booking['booking_id']; ?>)"><i class="fas fa-check"></i></button>
                                <button class="action-btn btn-danger" title="Cancel" onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)"><i class="fas fa-times"></i></button>
                            <?php endif; ?>
                            <?php if ($booking['status'] == 'confirmed'): ?>
                                <button class="action-btn" title="Complete" onclick="completeBooking(<?php echo $booking['booking_id']; ?>)"><i class="fas fa-check-double"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button class="pagination-btn active">1</button>
            <button class="pagination-btn">2</button>
            <button class="pagination-btn">3</button>
            <button class="pagination-btn">Next</button>
        </div>
    </div>

    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const applyFiltersBtn = document.getElementById('apply-filters');
            applyFiltersBtn.addEventListener('click', function() {
                alert('Filters applied! In a real application, this would filter the booking data.');
            });

            // Pagination functionality
            const paginationButtons = document.querySelectorAll('.pagination-btn');
            paginationButtons.forEach(button => {
                button.addEventListener('click', function() {
                    paginationButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    alert('Loading page ' + this.textContent + '...');
                });
            });
        });

        function viewBooking(bookingId) {
            alert('Viewing booking details for ID: ' + bookingId);
            // In a real application, this would open a modal or redirect to a details page
        }

        function confirmBooking(bookingId) {
            if (confirm('Are you sure you want to confirm this booking?')) {
                // In a real application, this would make an AJAX call to update the status
                alert('Booking confirmed!');
                // Reload the page to see the updated status
                location.reload();
            }
        }

        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                // In a real application, this would make an AJAX call to update the status
                alert('Booking cancelled!');
                // Reload the page to see the updated status
                location.reload();
            }
        }

        function completeBooking(bookingId) {
            if (confirm('Are you sure you want to mark this booking as completed?')) {
                // In a real application, this would make an AJAX call to update the status
                alert('Booking completed!');
                // Reload the page to see the updated status
                location.reload();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>