<?php
session_start();
require_once 'db.php';

$hospitals = [];

try {
    $query = "
        SELECT o.UserID, o.OrgName, o.OrgType, a.Street, a.City, c.ContactNumber, u.Email
        FROM Organization o
        JOIN User u ON o.UserID = u.UserID
        LEFT JOIN UserAddress a ON o.UserID = a.UserID
        LEFT JOIN UserContact c ON o.UserID = c.UserID
        ORDER BY o.OrgName
    ";
    
    $stmt = $pdo->query($query);
    $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch inventory for all orgs
    $hospitals = [];
    foreach ($orgs as $org) {
        $invStmt = $pdo->prepare("SELECT BloodGroup, Units FROM BloodInventory WHERE OrgID = ?");
        $invStmt->execute([$org['UserID']]);
        $org['Inventory'] = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        $hospitals[] = $org;
    }
} catch (PDOException $e) {
    $error = "Error fetching network info: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Hospital Partner Network</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="search_blood.php">Search Blood</a></li>
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
        <div class="form-container" style="max-width: 900px;">
            <h2 style="color: var(--primary-red); margin-bottom: 2rem; text-align: center;">Partner Network</h2>
            <p style="text-align: center; color: var(--text-light); margin-bottom: 3rem;">
                Browse the hospitals and blood banks connected to our platform.
            </p>

            <?php if (isset($error)): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (count($hospitals) > 0): ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                    <?php foreach ($hospitals as $org): ?>
                        <div class="card" style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 0.5rem; font-size: 1.2rem;"><?php echo htmlspecialchars($org['OrgName']); ?></h3>
                            <span class="badge badge-pending" style="margin-bottom: 1rem; display: inline-block;">
                                <?php echo htmlspecialchars($org['OrgType']); ?>
                            </span>
                            
                            <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <strong>Location:</strong><br>
                                <?php 
                                    $address = array_filter([$org['Street'], $org['City']]);
                                    echo htmlspecialchars(!empty($address) ? implode(', ', $address) : 'N/A');
                                ?>
                            </p>
                            <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <strong>Contact:</strong><br>
                                <?php echo htmlspecialchars($org['ContactNumber'] ?: 'N/A'); ?>
                            </p>
                            <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <strong>Email:</strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($org['Email']); ?>" style="color: var(--primary-red);"><?php echo htmlspecialchars($org['Email']); ?></a>
                            </p>

                            <div style="margin-top: 1.5rem; border-top: 1px solid #eee; padding-top: 1rem;">
                                <strong style="font-size: 0.85rem; color: var(--text-light);">Current Stock:</strong>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                                    <?php if (!empty($org['Inventory'])): ?>
                                        <?php foreach ($org['Inventory'] as $inv): ?>
                                            <?php if ($inv['Units'] > 0): ?>
                                                <div style="background: #fdf2f2; border: 1px solid #fee2e2; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                                    <strong><?php echo $inv['BloodGroup']; ?>:</strong> <?php echo $inv['Units']; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="font-size: 0.75rem; color: var(--text-light);">No units currently in stock.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--text-light); text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px;">No partner organizations are currently registered in the system.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
