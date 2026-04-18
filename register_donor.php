<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $bloodGroup = $_POST['blood_group'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $street = $_POST['street'] ?? '';
    $city = $_POST['city'] ?? '';

    if (empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($dob) || empty($bloodGroup)) {
        $error = "Please fill all required fields.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // 1. Insert into User
            $stmt = $pdo->prepare("INSERT INTO User (Email, PasswordHash, Role) VALUES (?, ?, 'Donor')");
            $stmt->execute([$email, $hashedPassword]);
            $userId = $pdo->lastInsertId();

            // 2. Insert into Person
            $stmt = $pdo->prepare("INSERT INTO Person (UserID, FirstName, LastName, DateOfBirth, BloodGroup) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $firstName, $lastName, $dob, $bloodGroup]);

            // 3. Insert into Donor (Pending approval by default)
            $stmt = $pdo->prepare("INSERT INTO Donor (UserID, AvailabilityStatus, ApprovalStatus) VALUES (?, FALSE, 'Pending')");
            $stmt->execute([$userId]);

            // 4. Insert Contact (Multivalued demo)
            if (!empty($contact)) {
                $stmt = $pdo->prepare("INSERT INTO UserContact (UserID, ContactNumber) VALUES (?, ?)");
                $stmt->execute([$userId, $contact]);
            }

            // 5. Insert Address (Composite mapped)
            if (!empty($street) || !empty($city)) {
                $stmt = $pdo->prepare("INSERT INTO UserAddress (UserID, Street, City, ZIP) VALUES (?, ?, ?, '')");
                $stmt->execute([$userId, $street, $city]);
            }

            $pdo->commit();
            $success = "Registration successful! Your account is pending admin approval. You will be visible to others once approved.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) { // Integrity constraint violation (email exists)
                $error = "Email is already registered.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Become a Donor</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="search_blood.php">Search Blood</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="form-container">
            <h2 style="color: var(--primary-red); margin-bottom: 2rem; text-align: center;">Register as a Donor</h2>
            
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

            <form action="" method="POST">
                <!-- Account details -->
                <div class="grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                </div>

                <!-- Personal details -->
                <div class="grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                </div>

                <div class="grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="dob">Date of Birth *</label>
                        <input type="date" id="dob" name="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="blood_group">Blood Group *</label>
                        <select id="blood_group" name="blood_group" class="form-control" required>
                            <option value="">Select</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>

                <!-- Contact & Address -->
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact" class="form-control" placeholder="01XXX-XXXXXX">
                </div>

                <div class="grid" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="street">Street Address</label>
                        <input type="text" id="street" name="street" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Register Now</button>
            </form>
            <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
                Already have an account? <a href="login.php" style="color: var(--primary-red);">Login here</a>
            </p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
