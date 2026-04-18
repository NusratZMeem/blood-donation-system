<?php
require_once 'db.php';

try {
    // 1. Update Red Cross Blood Center to Bangladesh Blood Bank and Transfusion Center
    // First, find the UserID for 'Red Cross Blood Center'
    $stmt = $pdo->prepare("SELECT UserID FROM Organization WHERE OrgName = 'Red Cross Blood Center'");
    $stmt->execute();
    $redCross = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($redCross) {
        $uid = $redCross['UserID'];
        
        // Update OrgName
        $pdo->prepare("UPDATE Organization SET OrgName = 'Bangladesh Blood Bank and Transfusion Center' WHERE UserID = ?")->execute([$uid]);
        
        // Update Email in User table
        $pdo->prepare("UPDATE User SET Email = 'bloodbank@gmail.com' WHERE UserID = ?")->execute([$uid]);
        
        // Update Address
        $pdo->prepare("UPDATE UserAddress SET Street = '12,, 22 Babar Rd', City = 'Dhaka' WHERE UserID = ?")->execute([$uid]);
        
        // Update Contact
        $pdo->prepare("UPDATE UserContact SET ContactNumber = '01850-077185' WHERE UserID = ?")->execute([$uid]);
        
        echo "Updated Red Cross Blood Center. <br>";
    }

    // 2. Update City General Hospital
    $stmt = $pdo->prepare("SELECT UserID FROM Organization WHERE OrgName = 'City General Hospital'");
    $stmt->execute();
    $cityHospital = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cityHospital) {
        $uid = $cityHospital['UserID'];
        
        // Update Address
        $pdo->prepare("UPDATE UserAddress SET Street = 'House 1, Road 1, Mirpur 2', City = 'Dhaka' WHERE UserID = ?")->execute([$uid]);
        
        // Update Contact
        $pdo->prepare("UPDATE UserContact SET ContactNumber = '01711111111' WHERE UserID = ?")->execute([$uid]);
        
        echo "Updated City General Hospital. <br>";
    }

    echo "Successfully updated all information!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
