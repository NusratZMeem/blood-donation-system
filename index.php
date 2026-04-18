<?php 
session_start(); 
require_once 'db.php';

$error = '';
$success = '';

// Handle Donor Status Toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status']) && isset($_SESSION['bb_user_id']) && $_SESSION['bb_role'] === 'Donor') {
    $donorId = $_SESSION['bb_user_id'];
    try {
        // Check last donation date
        $stmt = $pdo->prepare("SELECT AvailabilityStatus, LastDonationDate, ApprovalStatus FROM Donor WHERE UserID = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($donor['ApprovalStatus'] !== 'Approved') {
            $error = "Your account is not yet approved by the admin.";
        } else {
            $newStatus = $donor['AvailabilityStatus'] ? 0 : 1;
            
            // Restriction: If trying to become available, check last donation date (2 months)
            if ($newStatus == 1 && $donor['LastDonationDate']) {
                $lastDonation = new DateTime($donor['LastDonationDate']);
                $now = new DateTime();
                $diff = $now->diff($lastDonation);
                $months = ($diff->y * 12) + $diff->m;
                
                if ($months < 2) {
                    $error = "You cannot set your status to Available within 2 months of your last donation (Last donation: " . $donor['LastDonationDate'] . ").";
                }
            }

            if (!$error) {
                $upd = $pdo->prepare("UPDATE Donor SET AvailabilityStatus = ? WHERE UserID = ?");
                $upd->execute([$newStatus, $donorId]);
                $success = "Status updated successfully!";
            }
        }
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Fetch Available Donors for the public section
$availableDonors = [];
try {
    $stmt = $pdo->query("
        SELECT p.FirstName, p.LastName, p.BloodGroup, a.City, d.UserID
        FROM Donor d
        JOIN Person p ON d.UserID = p.UserID
        LEFT JOIN UserAddress a ON d.UserID = a.UserID
        WHERE d.ApprovalStatus = 'Approved' AND d.AvailabilityStatus = TRUE
        LIMIT 10
    ");
    $availableDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* silent fail */ }

// Fetch donor's current status if logged in
$myStatus = null;
if (isset($_SESSION['bb_user_id']) && $_SESSION['bb_role'] === 'Donor') {
    $stmt = $pdo->prepare("SELECT AvailabilityStatus FROM Donor WHERE UserID = ?");
    $stmt->execute([$_SESSION['bb_user_id']]);
    $myStatus = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Home</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="search_blood.php">Search blood</a></li>
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
        <?php if ($error): ?>
            <div style="background:#ffebee;color:#c62828;padding:1rem;margin:1rem auto;max-width:1000px;border-radius:8px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background:#e8f5e9;color:#2e7d32;padding:1rem;margin:1rem auto;max-width:1000px;border-radius:8px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Donor Dashboard Summary (If logged in as Donor) -->
        <?php if (isset($_SESSION['bb_user_id']) && $_SESSION['bb_role'] === 'Donor'): ?>
            <section class="form-container" style="max-width: 1000px; margin-bottom: 2rem; background: #fff1f1; border: 1px solid var(--secondary-red);">
                <div style="display:flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin:0; color:var(--primary-red);">Donor Dashboard</h3>
                        <p style="margin:0.3rem 0; color:var(--text-light);">Manage your availability and respond to requests.</p>
                    </div>
                    <div style="display:flex; align-items:center; gap: 1rem;">
                        <span class="badge <?php echo $myStatus ? 'badge-fulfilled' : 'badge-pending'; ?>">
                            Currently: <?php echo $myStatus ? 'Available' : 'Not Available'; ?>
                        </span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="toggle_status" value="1">
                            <button type="submit" class="btn <?php echo $myStatus ? 'btn-secondary' : 'btn-primary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                <?php echo $myStatus ? 'Set Not Available' : 'Set Available'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="hero" style="background-image: linear-gradient(rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.85)), url('https://images.unsplash.com/photo-1615461066841-6116ecaaba7f?auto=format&fit=crop&q=80&w=2000'); background-size: cover; background-position: center; border-radius: 12px; margin-bottom: 2rem;">
            <h2>Give Blood, Save Lives</h2>
            <p>Join our community of lifesavers. Whether you are looking to donate blood or request it for a patient in need, our platform connects you instantly.</p>
            <div style="margin-top: 2rem;">
                <?php if (!isset($_SESSION['bb_user_id'])): ?>
                    <a href="register_donor.php" class="btn btn-primary">Become a Donor</a>
                <?php endif; ?>
                <a href="search_blood.php" class="btn btn-secondary" style="<?php echo !isset($_SESSION['bb_user_id']) ? 'margin-left: 1rem;' : ''; ?>">Search for Blood</a>
            </div>
        </section>

        <!-- New Section: Available Blood Donors (Public) -->
        <section style="margin-bottom: 3rem;">
            <h2 style="color: var(--primary-red); margin-bottom: 1.5rem; text-align: center;">Available Blood Donors</h2>
            <?php if (count($availableDonors) > 0): ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($availableDonors as $donor): ?>
                        <div class="card" style="padding: 1.5rem; border-left: 5px solid var(--primary-red);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <h4 style="margin:0;"><?php echo htmlspecialchars($donor['FirstName'] . ' ' . $donor['LastName']); ?></h4>
                                <span class="badge badge-fulfilled"><?php echo htmlspecialchars($donor['BloodGroup']); ?></span>
                            </div>
                            <p style="margin: 0.5rem 0; color: var(--text-light); font-size: 0.9rem;">
                                📍 <?php echo htmlspecialchars($donor['City'] ?: 'Contact for location'); ?>
                            </p>
                            <div style="margin-top: 1rem;">
                                <form action="request_blood.php" method="GET">
                                    <input type="hidden" name="donor_id" value="<?php echo $donor['UserID']; ?>">
                                    <input type="hidden" name="blood_group" value="<?php echo $donor['BloodGroup']; ?>">
                                    <button type="submit" class="btn btn-secondary" style="width:100%; font-size: 0.85rem; padding: 0.5rem;">Request Blood</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="search_blood.php" style="color: var(--primary-red); text-decoration: none; font-weight: 600;">View all donors →</a>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:var(--text-light); background:#f9f9f9; padding:2rem; border-radius:8px;">No donors are currently marked as available. Please use the search page.</p>
            <?php endif; ?>
        </section>

        <section class="grid">
            <div class="card">
                <h3>For Donors</h3>
                <p>Register to become a blood donor, manage your availability, and track your past donations seamlessly.</p>
                <a href="register_donor.php" class="btn btn-secondary">Join Now</a>
            </div>
            <div class="card">
                <h3>For Patients & Hospitals</h3>
                <p>Submit urgent blood requests and find matching donors by blood group and location instantly.</p>
                <a href="search_blood.php" class="btn btn-secondary">Search Donors</a>
            </div>
            <div class="card">
                <h3>Hospital Network</h3>
                <p>View participating hospitals and blood banks in our network and partner with us to save more lives.</p>
                <a href="hospital_info.php" class="btn btn-secondary">View Hospitals</a>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Blood Donation System. All Rights Reserved. | <a href="admin_dashboard.php" style="color:inherit; text-decoration:none;">Admin</a></p>
    </footer>
</body>
</html>
