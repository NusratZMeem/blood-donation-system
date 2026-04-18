<?php
session_start();
require_once 'db.php';

$donors = [];
$searched = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['blood_group'])) {
    $bloodGroup = $_GET['blood_group'] ?? '';
    $location = $_GET['location'] ?? '';

    try {
        $query = "
            SELECT p.UserID, p.FirstName, p.LastName, p.BloodGroup, d.AvailabilityStatus, d.LastDonationDate, a.City, c.ContactNumber
            FROM Person p
            JOIN Donor d ON p.UserID = d.UserID
            LEFT JOIN UserAddress a ON p.UserID = a.UserID
            LEFT JOIN UserContact c ON p.UserID = c.UserID
            WHERE p.BloodGroup = :bloodGroup AND d.AvailabilityStatus = TRUE AND d.ApprovalStatus = 'Approved'
        ";
        
        $params = [':bloodGroup' => $bloodGroup];
        
        if (!empty($location)) {
            $query .= " AND a.City LIKE :location";
            $params[':location'] = "%$location%";
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searched = true;
    } catch (PDOException $e) {
        $error = "Error fetching donors: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Search Blood</title>
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
        <div class="form-container" style="max-width: 800px;">
            <h2 style="color: var(--primary-red); margin-bottom: 2rem; text-align: center;">Search for Donors</h2>
            
            <form action="search_blood.php" method="GET">
                <div class="grid" style="gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="blood_group">Blood Group *</label>
                        <select id="blood_group" name="blood_group" class="form-control" required>
                            <option value="">Select</option>
                            <option value="A+" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                            <option value="O+" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo (isset($_GET['blood_group']) && $_GET['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="location">Location (City)</label>
                        <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>" placeholder="e.g. New York">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Search</button>
                    </div>
                </div>
            </form>

            <?php if (isset($error)): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-top: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($searched): ?>
                <h3 style="margin-top: 3rem; margin-bottom: 1rem; color: var(--text-dark);">Search Results</h3>
                <?php if (count($donors) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Donor Name</th>
                                    <th>Blood Group</th>
                                    <th>Location</th>
                                    <th>Contact</th>
                                    <th>Last Donation</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donors as $donor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['FirstName'] . ' ' . $donor['LastName']); ?></td>
                                        <td><span class="badge badge-fulfilled"><?php echo htmlspecialchars($donor['BloodGroup']); ?></span></td>
                                        <td><?php echo htmlspecialchars($donor['City'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($donor['ContactNumber'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($donor['LastDonationDate'] ?: 'Never'); ?></td>
                                        <td>
                                            <?php if (isset($_SESSION['bb_user_id'])): ?>
                                                <form action="request_blood.php" method="GET" style="display:inline;">
                                                    <input type="hidden" name="donor_id" value="<?php echo $donor['UserID']; ?>">
                                                    <input type="hidden" name="blood_group" value="<?php echo $donor['BloodGroup']; ?>">
                                                    <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">Request</button>
                                                </form>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">Login to Request</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-light); text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px;">No available donors found matching your criteria.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
