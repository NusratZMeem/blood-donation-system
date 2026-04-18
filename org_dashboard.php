<?php
session_start();
require_once 'db.php';

// Access control: Only Hospital or BloodBank roles
if (!isset($_SESSION['bb_user_id']) || !in_array($_SESSION['bb_role'], ['Hospital', 'BloodBank'])) {
    header("Location: login.php");
    exit;
}

$orgId = $_SESSION['bb_user_id'];
$error = '';
$success = '';

// Handle inventory updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_inventory'])) {
    $bloodGroup = $_POST['blood_group'] ?? '';
    $units = (int)($_POST['units'] ?? 0);

    if (!empty($bloodGroup)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO BloodInventory (OrgID, BloodGroup, Units)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE Units = ?, LastUpdated = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$orgId, $bloodGroup, $units, $units]);
            $success = "Inventory for group $bloodGroup updated to $units units.";
        } catch (PDOException $e) {
            $error = "Error updating inventory: " . $e->getMessage();
        }
    }
}

// Fetch current inventory
$inventory = [];
try {
    $stmt = $pdo->prepare("SELECT BloodGroup, Units, LastUpdated FROM BloodInventory WHERE OrgID = ? ORDER BY BloodGroup");
    $stmt->execute([$orgId]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get Org Info
    $stmt2 = $pdo->prepare("SELECT OrgName, OrgType FROM Organization WHERE UserID = ?");
    $stmt2->execute([$orgId]);
    $orgInfo = $stmt2->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$currentStock = [];
foreach ($inventory as $item) {
    $currentStock[$item['BloodGroup']] = $item['Units'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Organization Dashboard</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .stock-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            border-bottom: 4px solid var(--secondary-red);
        }
        .stock-units {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-red);
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="search_blood.php">Search</a></li>
                <?php if (isset($_SESSION['bb_user_id'])): ?>
                    <?php if ($_SESSION['bb_role'] === 'Admin'): ?>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <?php elseif (in_array($_SESSION['bb_role'], ['Hospital', 'BloodBank'])): ?>
                        <li><a href="org_dashboard.php">Inventory</a></li>
                    <?php else: ?>
                        <li><a href="my_requests.php">Requests</a></li>
                        <li><a href="donation_history.php">My History</a></li>
                    <?php endif; ?>
                    <li style="color: white; margin-right: 1rem; align-self: center;">Welcome, <?php echo htmlspecialchars($_SESSION['bb_user_name'] ?? 'User'); ?></li>
                    <li><a href="logout.php" style="background: #e53935; color: white; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 600;">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register_donor.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="form-container" style="max-width: 1000px;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="color: var(--primary-red);"><?php echo htmlspecialchars($orgInfo['OrgName'] ?? 'Organization Dashboard'); ?></h2>
                <p style="color: var(--text-light);">Manage your blood stock levels below.</p>
            </div>

            <?php if ($error): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background-color: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="inventory-grid">
                <?php foreach ($bloodGroups as $bg): ?>
                    <div class="stock-card">
                        <span class="badge badge-fulfilled" style="font-size: 1.1rem;"><?php echo $bg; ?></span>
                        <div class="stock-units"><?php echo $currentStock[$bg] ?? 0; ?></div>
                        <p style="font-size: 0.8rem; color: var(--text-light); margin-bottom: 1rem;">Units Available</p>
                        
                        <form method="POST" style="display: flex; gap: 0.5rem; justify-content: center;">
                            <input type="hidden" name="blood_group" value="<?php echo $bg; ?>">
                            <input type="number" name="units" value="<?php echo $currentStock[$bg] ?? 0; ?>" min="0" class="form-control" style="width: 70px; padding: 0.3rem;">
                            <button type="submit" name="update_inventory" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">Update</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Blood Donation System. All Rights Reserved.</p>
    </footer>
</body>
</html>
