<?php
session_start();
require_once 'db.php';

// Only logged in users (or specific roles) can request blood
if (!isset($_SESSION['bb_user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bloodGroup = $_POST['blood_group'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $urgency = $_POST['urgency'] ?? 'Medium';
    $donorId = !empty($_POST['donor_id']) ? $_POST['donor_id'] : null;
    $hospitalId = (!empty($_POST['hospital_id']) && $_POST['hospital_id'] !== 'none') ? $_POST['hospital_id'] : null;
    
    if (empty($bloodGroup) || empty($quantity) || empty($urgency)) {
        $error = "Please fill all required fields.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO BloodRequest (RequesterID, RequiredBloodGroup, Quantity, UrgencyLevel, Status, TargetDonorID, HospitalID) VALUES (?, ?, ?, ?, 'Pending', ?, ?)");
            $stmt->execute([$_SESSION['bb_user_id'], $bloodGroup, $quantity, $urgency, $donorId, $hospitalId]);
            
            if ($donorId) {
                $success = "Direct request sent to the donor. They will respond soon!";
            } else {
                $success = "Blood request submitted successfully. We will notify you when a match is found.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all organizations/hospitals for selection
$orgs = [];
try {
    $stmt = $pdo->query("SELECT UserID, OrgName, OrgType FROM Organization ORDER BY OrgName");
    $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Check for pre-filled blood group (from search)
$preBloodGroup = $_GET['blood_group'] ?? '';
$targetDonor = $_GET['donor_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Request Blood</title>
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
        <div class="form-container">
            <h2 style="color: var(--primary-red); margin-bottom: 2rem; text-align: center;">Request Blood</h2>
            
            <?php if ($error): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background-color: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php else: ?>

            <form action="request_blood.php" method="POST">
                <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($targetDonor); ?>">
                
                <div class="form-group">
                    <label for="blood_group">Required Blood Group *</label>
                    <select id="blood_group" name="blood_group" class="form-control" required>
                        <option value="">Select</option>
                        <option value="A+" <?php echo ($preBloodGroup == 'A+') ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo ($preBloodGroup == 'A-') ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo ($preBloodGroup == 'B+') ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo ($preBloodGroup == 'B-') ? 'selected' : ''; ?>>B-</option>
                        <option value="AB+" <?php echo ($preBloodGroup == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo ($preBloodGroup == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        <option value="O+" <?php echo ($preBloodGroup == 'O+') ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo ($preBloodGroup == 'O-') ? 'selected' : ''; ?>>O-</option>
                    </select>
                </div>
                
                <div class="grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="quantity">Quantity (Units) *</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="urgency">Urgency Level *</label>
                        <select id="urgency" name="urgency" class="form-control" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="hospital_id">Hospital / Facility (Where donation occurs) *</label>
                    <select id="hospital_id" name="hospital_id" class="form-control" required>
                        <option value="">Select a Hospital/Bank</option>
                        <option value="none" style="font-weight: bold; color: var(--primary-red);">None (Direct Donation / Private Person)</option>
                        <?php foreach ($orgs as $org): ?>
                            <option value="<?php echo $org['UserID']; ?>">
                                <?php echo htmlspecialchars($org['OrgName'] . ' (' . $org['OrgType'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color: var(--text-light); font-size: 0.8rem; margin-top: 0.3rem;">
                        If you are a hospital, please select yourself for internal stock requests.
                    </p>
                </div>

                <div class="form-group">
                    <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 1rem;">
                        Submitting this request will broadcast it to eligible donors in the system.
                    </p>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Submit Request</button>
            </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
