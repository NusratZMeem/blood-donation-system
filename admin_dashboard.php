<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['bb_user_id']) || $_SESSION['bb_role'] !== 'Admin') {
    die("Access Denied. Admins only.");
}

$error = '';

// Handle donor approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['donor_id'], $_POST['action'])) {
        try {
            if ($_POST['action'] === 'approve') {
                $stmt = $pdo->prepare("UPDATE Donor SET ApprovalStatus = 'Approved', AvailabilityStatus = TRUE WHERE UserID = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE Donor SET ApprovalStatus = 'Rejected', AvailabilityStatus = FALSE WHERE UserID = ?");
            }
            $stmt->execute([$_POST['donor_id']]);
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
        header("Location: admin_dashboard.php");
        exit;
    }

    // Handle blood request status updates
    if (isset($_POST['request_id'], $_POST['status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE BloodRequest SET Status = ? WHERE RequestID = ?");
            $stmt->execute([$_POST['status'], $_POST['request_id']]);
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
        header("Location: admin_dashboard.php");
        exit;
    }
}

// Fetch pending donors
$pendingDonors = [];
try {
    $stmt = $pdo->query("
        SELECT d.UserID, p.FirstName, p.LastName, p.BloodGroup, u.Email, d.ApprovalStatus, u.RegistrationDate, a.City
        FROM Donor d
        JOIN Person p ON d.UserID = p.UserID
        JOIN User u ON d.UserID = u.UserID
        LEFT JOIN UserAddress a ON d.UserID = a.UserID
        ORDER BY FIELD(d.ApprovalStatus, 'Pending', 'Approved', 'Rejected'), u.RegistrationDate DESC
    ");
    $pendingDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = $e->getMessage(); }

// Fetch all blood requests
$requests = [];
try {
    $stmt = $pdo->query("
        SELECT br.RequestID, br.RequiredBloodGroup, br.Quantity, br.UrgencyLevel, br.Status, br.RequestDate, u.Email
        FROM BloodRequest br
        JOIN User u ON br.RequesterID = u.UserID
        ORDER BY br.RequestDate DESC
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Blood Donation System</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid #eee; }
        .tab-btn { padding: 0.7rem 1.5rem; border: none; background: none; font-size: 1rem; font-weight: 600; color: var(--text-light); cursor: pointer; position: relative; }
        .tab-btn.active { color: var(--primary-red); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--primary-red); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .action-row { display: flex; gap: 0.5rem; }
        .btn-sm { padding: 0.3rem 0.8rem; font-size: 0.85rem; border-radius: 50px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .btn-approve { background: #e8f5e9; color: #2e7d32; }
        .btn-approve:hover { background: #2e7d32; color: white; }
        .btn-reject { background: #ffebee; color: #c62828; }
        .btn-reject:hover { background: #c62828; color: white; }
    </style>
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank Admin</a></h1>
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
                    <li style="color: white; margin-right: 1rem; align-self: center;">Welcome, Administrator</li>
                    <li><a href="logout.php" style="background: #e53935; color: white; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 600;">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register_donor.php">Register</a></li>
                <?php endif; ?>
        </nav>
    </header>

    <main>
        <div class="form-container" style="max-width: 1050px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="color: var(--primary-red); margin: 0;">Admin Dashboard</h2>
                <span class="badge badge-pending">Administrator</span>
            </div>

            <?php if ($error): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('donors', this)">Donor Approvals 
                    <?php $pending = array_filter($pendingDonors, fn($d) => $d['ApprovalStatus'] === 'Pending'); if (count($pending) > 0): ?><span class="badge badge-pending" style="margin-left:0.5rem;"><?php echo count($pending); ?></span><?php endif; ?>
                </button>
                <button class="tab-btn" onclick="switchTab('requests', this)">Blood Requests</button>
            </div>

            <!-- Donor Approvals Tab -->
            <div id="tab-donors" class="tab-content active">
                <h3 style="margin-bottom: 1rem;">Donor Applications</h3>
                <?php if (count($pendingDonors) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Blood Group</th><th>City</th><th>Registered</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingDonors as $d): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['FirstName'].' '.$d['LastName']); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['Email']); ?></td>
                                <td><span class="badge badge-fulfilled"><?php echo htmlspecialchars($d['BloodGroup']); ?></span></td>
                                <td><?php echo htmlspecialchars($d['City'] ?: 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($d['RegistrationDate'])); ?></td>
                                <td>
                                    <?php 
                                        $cls = ['Pending'=>'badge-pending','Approved'=>'badge-fulfilled','Rejected'=>'badge-cancelled'];
                                        echo '<span class="badge '.$cls[$d['ApprovalStatus']].'">'.$d['ApprovalStatus'].'</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($d['ApprovalStatus'] === 'Pending'): ?>
                                    <div class="action-row">
                                        <form method="POST">
                                            <input type="hidden" name="donor_id" value="<?php echo $d['UserID']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-sm btn-approve">✓ Approve</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="donor_id" value="<?php echo $d['UserID']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-sm btn-reject">✗ Reject</button>
                                        </form>
                                    </div>
                                    <?php else: echo '<span style="color:var(--text-light);font-size:0.85rem;">—</span>'; endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="color: var(--text-light); text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px;">No donor applications found.</p>
                <?php endif; ?>
            </div>

            <!-- Blood Requests Tab -->
            <div id="tab-requests" class="tab-content">
                <h3 style="margin-bottom: 1rem;">Manage Blood Requests</h3>
                <?php if (count($requests) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Requester</th><th>Blood Group</th><th>Units</th><th>Urgency</th><th>Date</th><th>Status</th><th>Update</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $req): ?>
                            <?php $badgeClass = ['Pending'=>'badge-pending','Fulfilled'=>'badge-fulfilled','Cancelled'=>'badge-cancelled'][$req['Status']] ?? 'badge-pending'; ?>
                            <tr>
                                <td>#<?php echo $req['RequestID']; ?></td>
                                <td><?php echo htmlspecialchars($req['Email']); ?></td>
                                <td><strong><?php echo htmlspecialchars($req['RequiredBloodGroup']); ?></strong></td>
                                <td><?php echo $req['Quantity']; ?></td>
                                <td><?php echo $req['UrgencyLevel']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($req['RequestDate'])); ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $req['Status']; ?></span></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $req['RequestID']; ?>">
                                        <select name="status" class="form-control" style="padding:0.3rem;font-size:0.85rem;" onchange="this.form.submit()">
                                            <option value="Pending" <?php echo $req['Status']=='Pending'?'selected':''; ?>>Pending</option>
                                            <option value="Fulfilled" <?php echo $req['Status']=='Fulfilled'?'selected':''; ?>>Fulfilled</option>
                                            <option value="Cancelled" <?php echo $req['Status']=='Cancelled'?'selected':''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="color: var(--text-light); text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px;">No blood requests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tabName, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>
