<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT UserID, PasswordHash, Role FROM User WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['PasswordHash'])) {
                $_SESSION['bb_user_id'] = $user['UserID'];
                $_SESSION['bb_role'] = $user['Role'];
                
                // Fetch Name
                $userName = "User";
                if (in_array($user['Role'], ['Donor', 'Patient'])) {
                    $pStmt = $pdo->prepare("SELECT FirstName, LastName FROM Person WHERE UserID = ?");
                    $pStmt->execute([$user['UserID']]);
                    if ($person = $pStmt->fetch(PDO::FETCH_ASSOC)) {
                        $userName = $person['FirstName'] . ' ' . $person['LastName'];
                    }
                } elseif (in_array($user['Role'], ['Hospital', 'BloodBank'])) {
                    $oStmt = $pdo->prepare("SELECT OrgName FROM Organization WHERE UserID = ?");
                    $oStmt->execute([$user['UserID']]);
                    if ($org = $oStmt->fetch(PDO::FETCH_ASSOC)) {
                        $userName = $org['OrgName'];
                    }
                } elseif ($user['Role'] == 'Admin') {
                    $userName = 'Administrator';
                }
                $_SESSION['bb_user_name'] = $userName;

                // Redirect based on role
                if ($user['Role'] == 'Admin') {
                    header("Location: admin_dashboard.php");
                } elseif (in_array($user['Role'], ['Hospital', 'BloodBank'])) {
                    header("Location: org_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System | Login</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 80px); /* header height approx */
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:white;text-decoration:none;">BloodBank</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="search_blood.php">Search Blood</a></li>
            </ul>
        </nav>
    </header>

    <main class="login-wrapper">
        <div class="form-container" style="width: 100%; max-width: 450px;">
            <h2 style="color: var(--primary-red); margin-bottom: 2rem; text-align: center;">Welcome Back</h2>
            
            <?php if ($error): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
                Don't have an account? <a href="register_donor.php" style="color: var(--primary-red);">Register here</a>
            </p>
        </div>
    </main>
</body>
</html>
