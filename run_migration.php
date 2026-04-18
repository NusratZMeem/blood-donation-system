<?php
// One-time migration script. Delete this file after running.
require_once 'db.php';

$sqls = [
    // Add ApprovalStatus column if it doesn't exist
    "ALTER TABLE Donor ADD COLUMN IF NOT EXISTS ApprovalStatus ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'",
    
    // Create DonorResponse table if it doesn't exist
    "CREATE TABLE IF NOT EXISTS DonorResponse (
        ResponseID INT AUTO_INCREMENT PRIMARY KEY,
        DonorID INT NOT NULL,
        RequestID INT NOT NULL,
        Response ENUM('Accepted','Rejected') NOT NULL,
        ResponseDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_donor_request (DonorID, RequestID),
        FOREIGN KEY (DonorID) REFERENCES Donor(UserID) ON DELETE CASCADE,
        FOREIGN KEY (RequestID) REFERENCES BloodRequest(RequestID) ON DELETE CASCADE
    )",
    
    // Mark all pre-existing donors as Approved and available
    "UPDATE Donor SET ApprovalStatus = 'Approved', AvailabilityStatus = TRUE WHERE ApprovalStatus = 'Pending'"
];

$errors = [];
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html><head><title>Migration</title>
<link rel="stylesheet" href="index.css">
</head><body>
<main>
<div class="form-container" style="max-width:600px;margin-top:3rem;">
    <h2 style="color:var(--primary-red);text-align:center;">Database Migration</h2>
    <?php if (empty($errors)): ?>
        <div style="background:#e8f5e9;color:#2e7d32;padding:1.5rem;border-radius:8px;text-align:center;">
            <strong>✓ Migration completed successfully!</strong><br><br>
            The following have been applied:
            <ul style="text-align:left;margin-top:1rem;">
                <li>Added <code>ApprovalStatus</code> column to Donor table</li>
                <li>Created <code>DonorResponse</code> table for donor accept/reject</li>
                <li>Existing donors marked as <strong>Approved</strong></li>
            </ul>
            <a href="index.php" class="btn btn-primary" style="margin-top:1rem;display:inline-block;">Go to Home →</a>
        </div>
        <p style="color:#999;text-align:center;font-size:0.85rem;margin-top:1.5rem;">
            You can now delete this file: <code>run_migration.php</code>
        </p>
    <?php else: ?>
        <div style="background:#ffebee;color:#c62828;padding:1.5rem;border-radius:8px;">
            <strong>Some errors occurred:</strong>
            <ul style="margin-top:0.5rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
</main>
</body></html>
