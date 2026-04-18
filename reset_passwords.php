<?php
// One-time password reset. Delete this file after running.
require_once 'db.php';

$newHash = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE User SET PasswordHash = ?");
$stmt->execute([$newHash]);
$count = $stmt->rowCount();
?>
<!DOCTYPE html>
<html><head><title>Password Reset</title>
<link rel="stylesheet" href="index.css">
</head><body>
<main>
<div class="form-container" style="max-width:550px;margin-top:3rem;text-align:center;">
    <h2 style="color:var(--primary-red);">Password Reset</h2>
    <div style="background:#e8f5e9;color:#2e7d32;padding:1.5rem;border-radius:8px;margin-top:1rem;">
        <strong>✓ Done! <?php echo $count; ?> accounts updated.</strong><br><br>
        All accounts now use password: <strong>password123</strong><br><br>
        <a href="login.php" class="btn btn-primary" style="display:inline-block;margin-top:0.5rem;">Go to Login →</a>
    </div>
    <p style="color:#999;font-size:0.85rem;margin-top:1.5rem;">Delete <code>reset_passwords.php</code> after use.</p>
</div>
</main>
</body></html>
