<?php
session_start();
require_once 'db.php';

// Check if logged in and is Donor
if (!isset($_SESSION['bb_user_id']) || $_SESSION['bb_role'] !== 'Donor') {
    header("Location: index.php");
    exit;
}

$history = [];
try {
    // Fetch donation history for logged in user
    $query = "
        SELECT da.DonationDate, da.QuantityDonated, o.OrgName, p.FirstName AS PatientFirst, p.LastName AS PatientLast
        FROM DonationArrangement da
        LEFT JOIN Organization o ON da.OrgID = o.UserID
        LEFT JOIN Person p ON da.PatientID = p.UserID
        WHERE da.DonorID = ?
        ORDER BY da.DonationDate DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['bb_user_id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching history data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | My Donation History</title>
    <link rel="stylesheet" href="index.css">
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
        <div class="form-container" style="max-width: 800px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 style="color: var(--primary-red); margin: 0;">My Donation History</h2>
                <span class="badge badge-fulfilled">Donor View</span>
            </div>

            <?php if (isset($error)): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (count($history) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Location (Hospital/Bank)</th>
                                <th>Patient Receiving</th>
                                <th>Units Donated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['DonationDate'])); ?></td>
                                    <td>
                                        <?php 
                                            if ($record['OrgName']) {
                                                echo '<strong>' . htmlspecialchars($record['OrgName']) . '</strong>';
                                            } else {
                                                echo '<span style="color:var(--text-light); font-style:italic;">Direct / Independent</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($record['PatientFirst']) {
                                                echo htmlspecialchars($record['PatientFirst'] . ' ' . $record['PatientLast']);
                                            } else {
                                                echo '<span style="color:var(--text-light); font-style:italic;">Hospital Stock / General Use</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['QuantityDonated']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-light); text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px;">
                    You have no recorded donations yet. <br><br>
                    <a href="search_blood.php" class="btn btn-primary" style="margin-top: 1rem;">View Local Blood Requests</a>
                </p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
