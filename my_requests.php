<?php
session_start();
require_once 'db.php';

// Must be a logged-in Donor
if (!isset($_SESSION['bb_user_id']) || $_SESSION['bb_role'] !== 'Donor') {
    header("Location: login.php");
    exit;
}

$donorId = $_SESSION['bb_user_id'];
$error = '';
$success = '';

// Check if donor is approved
$stmt = $pdo->prepare("SELECT ApprovalStatus, AvailabilityStatus FROM Donor WHERE UserID = ?");
$stmt->execute([$donorId]);
$donorInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$isApproved = $donorInfo && $donorInfo['ApprovalStatus'] === 'Approved';

// Handle Accept/Reject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $isApproved) {
    $requestId = $_POST['request_id'] ?? null;
    $response  = $_POST['response']   ?? null;

    if ($requestId && in_array($response, ['Accepted', 'Rejected'])) {
        try {
            // Insert or update the donor's response
            $stmt = $pdo->prepare("
                INSERT INTO DonorResponse (DonorID, RequestID, Response)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE Response = ?, ResponseDate = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$donorId, $requestId, $response, $response]);

            // If accepted, mark request as Fulfilled AND update donor's donation status
            if ($response === 'Accepted') {
                // Fetch request details to populate DonationArrangement
                $reqStmt = $pdo->prepare("
                    SELECT br.RequesterID, br.HospitalID, br.Quantity, br.RequiredBloodGroup, u.Role 
                    FROM BloodRequest br 
                    JOIN User u ON br.RequesterID = u.UserID 
                    WHERE br.RequestID = ?
                ");
                $reqStmt->execute([$requestId]);
                $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqData) {
                    $patientId = ($reqData['Role'] === 'Patient') ? $reqData['RequesterID'] : null;
                    $orgId = $reqData['HospitalID'] ?: (($reqData['Role'] === 'Hospital' || $reqData['Role'] === 'BloodBank') ? $reqData['RequesterID'] : null);
                    
                    $insArr = $pdo->prepare("
                        INSERT INTO DonationArrangement (DonorID, PatientID, OrgID, DonationDate, QuantityDonated) 
                        VALUES (?, ?, ?, CURRENT_DATE, ?)
                    ");
                    $insArr->execute([$donorId, $patientId, $orgId, $reqData['Quantity']]);
                }

                $upd = $pdo->prepare("UPDATE BloodRequest SET Status = 'Fulfilled' WHERE RequestID = ?");
                $upd->execute([$requestId]);

                // Update donor info: mark as not available and set last donation date
                $updDonor = $pdo->prepare("UPDATE Donor SET LastDonationDate = CURRENT_DATE, AvailabilityStatus = FALSE WHERE UserID = ?");
                $updDonor->execute([$donorId]);
            }
            $success = "Your response has been recorded. Thank you for your donation!";
        } catch (PDOException $e) {
            $error = "Error saving response: " . $e->getMessage();
        }
        header("Location: my_requests.php?msg=" . urlencode($success ?: $error));
        exit;
    }
}

// Get the donor's blood group
$stmt = $pdo->prepare("SELECT BloodGroup FROM Person WHERE UserID = ?");
$stmt->execute([$donorId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);
$myBloodGroup = $person['BloodGroup'] ?? '';

// Fetch blood requests matching the donor's blood group (that are still Pending)
$requests = [];
if ($isApproved && $myBloodGroup) {
    try {
        $stmt = $pdo->prepare("
            SELECT br.RequestID, br.RequiredBloodGroup, br.Quantity, br.UrgencyLevel, br.Status, br.RequestDate,
                   u.Email AS RequesterEmail,
                   dr.Response AS MyResponse
            FROM BloodRequest br
            JOIN User u ON br.RequesterID = u.UserID
            LEFT JOIN DonorResponse dr ON dr.RequestID = br.RequestID AND dr.DonorID = ?
            WHERE br.RequiredBloodGroup = ? 
              AND br.Status = 'Pending'
              AND (br.TargetDonorID IS NULL OR br.TargetDonorID = ?)
            ORDER BY FIELD(br.UrgencyLevel,'Critical','High','Medium','Low'), br.RequestDate ASC
        ");
        $stmt->execute([$donorId, $myBloodGroup, $donorId]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching requests: " . $e->getMessage();
    }
}

$msgFromRedirect = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Blood Requests For You</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .request-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; transition: box-shadow 0.3s; }
        .request-card:hover { box-shadow: 0 8px 20px rgba(211,47,47,0.1); border-color: var(--secondary-red); }
        .urgency-Critical { border-left: 5px solid #B71C1C; }
        .urgency-High     { border-left: 5px solid #E53935; }
        .urgency-Medium   { border-left: 5px solid #FB8C00; }
        .urgency-Low      { border-left: 5px solid #43A047; }
        .request-actions { display: flex; gap: 1rem; margin-top: 1rem; }
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
        <div class="form-container" style="max-width: 800px; padding: 2rem;">
            <h2 style="color: var(--primary-red); margin-bottom: 0.5rem; text-align: center;">Blood Requests For You</h2>
            <p style="color: var(--text-light); text-align: center; margin-bottom: 2rem;">
                Showing requests for blood group: <strong><?php echo htmlspecialchars($myBloodGroup ?: '—'); ?></strong>
            </p>

            <?php if ($msgFromRedirect): ?>
                <div style="background:#e8f5e9;color:#2e7d32;padding:1rem;border-radius:8px;margin-bottom:1.5rem;"><?php echo htmlspecialchars($msgFromRedirect); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#ffebee;color:#c62828;padding:1rem;border-radius:8px;margin-bottom:1.5rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$isApproved): ?>
                <div style="background:#fff3e0;color:#e65100;padding:1.5rem;border-radius:8px;text-align:center;">
                    <strong>Your donor account is <?php echo htmlspecialchars($donorInfo['ApprovalStatus'] ?? 'Pending'); ?>.</strong><br>
                    You can see and respond to blood requests once an admin approves your account.
                </div>
            <?php elseif (empty($requests)): ?>
                <p style="color:var(--text-light);text-align:center;padding:2rem;background:#f9f9f9;border-radius:8px;">
                    No pending blood requests for your blood group right now. Check back later!
                </p>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                    <?php $urgencyClass = 'urgency-' . $req['UrgencyLevel']; ?>
                    <div class="request-card <?php echo $urgencyClass; ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <span class="badge badge-fulfilled" style="font-size:1.1rem;"><?php echo htmlspecialchars($req['RequiredBloodGroup']); ?></span>
                                <span class="badge <?php echo ['Critical'=>'badge-cancelled','High'=>'badge-cancelled','Medium'=>'badge-pending','Low'=>'badge-fulfilled'][$req['UrgencyLevel']]; ?>" style="margin-left:0.5rem;">
                                    <?php echo htmlspecialchars($req['UrgencyLevel']); ?> Urgency
                                </span>
                            </div>
                            <small style="color:var(--text-light);"><?php echo date('M d, Y', strtotime($req['RequestDate'])); ?></small>
                        </div>
                        <p style="margin:0.8rem 0;"><strong>Units needed:</strong> <?php echo $req['Quantity']; ?></p>
                        <p style="margin:0;color:var(--text-light);font-size:0.9rem;">Requested by: <?php echo htmlspecialchars($req['RequesterEmail']); ?></p>

                        <?php if ($req['MyResponse']): ?>
                            <div style="margin-top:1rem;">
                                <span class="badge <?php echo $req['MyResponse']==='Accepted'?'badge-fulfilled':'badge-cancelled'; ?>">
                                    You: <?php echo $req['MyResponse']; ?>
                                </span>
                                <span style="color:var(--text-light);font-size:0.85rem;margin-left:0.5rem;">You already responded to this request.</span>
                            </div>
                        <?php else: ?>
                            <div class="request-actions">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $req['RequestID']; ?>">
                                    <input type="hidden" name="response" value="Accepted">
                                    <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem;">✓ Accept</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $req['RequestID']; ?>">
                                    <input type="hidden" name="response" value="Rejected">
                                    <button type="submit" class="btn btn-secondary" style="padding:0.6rem 1.5rem;">✗ Reject</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
