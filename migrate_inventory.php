<?php
require_once 'db.php';

try {
    // 1. Create BloodInventory table
    $sql = "
    CREATE TABLE IF NOT EXISTS BloodInventory (
        InventoryID INT AUTO_INCREMENT PRIMARY KEY,
        OrgID INT NOT NULL,
        BloodGroup ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
        Units INT NOT NULL DEFAULT 0,
        LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_org_blood (OrgID, BloodGroup),
        FOREIGN KEY (OrgID) REFERENCES Organization(UserID) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "BloodInventory table created or already exists.<br>";

    // 2. Seed initial data for known organizations
    $orgs = $pdo->query("SELECT UserID, OrgName FROM Organization")->fetchAll(PDO::FETCH_ASSOC);
    $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    foreach ($orgs as $org) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO BloodInventory (OrgID, BloodGroup, Units) VALUES (?, ?, ?)");
        foreach ($bloodGroups as $bg) {
            // Initial random-ish units for demonstration
            $units = rand(5, 20);
            $stmt->execute([$org['UserID'], $bg, $units]);
        }
        echo "Seeded inventory for " . htmlspecialchars($org['OrgName']) . ".<br>";
    }

    echo "Migration completed successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
