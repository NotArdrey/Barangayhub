
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../styles/change_pass.css">
</head>
<body>
    <div class="reset-password-container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Password Reset Form -->
        <?php if (empty($success)): ?>
            <form action="../functions/change_pass.php" method="post">
                <!-- Hidden fields to pass email and token -->
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="new_password" placeholder="Enter new password" required>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                <input type="submit" value="Change Password">
            </form>
            
            <!-- Resend Email Form -->
            <form action="../functions/change_pass.php" method="post" class="resend-form">
                <!-- The 'resend' field indicates this POST is for re-sending the email -->
                <input type="hidden" name="resend" value="1">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit">Resend Reset Email</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php
    // Optional: Display any session alerts if needed.
    if(isset($_SESSION['alert'])) {
        echo $_SESSION['alert'];
        unset($_SESSION['alert']);
    }
    ?>
</body>
</html>