<?php
/**
 * Email Verification Helper Functions
 * Handles sending verification emails and managing verification tokens
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate a secure verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Create email verification record in database
 */
function createEmailVerification($user_id, $email, $token) {
    global $conn;
    
    // Delete any existing tokens for this user
    $stmt = $conn->prepare("DELETE FROM email_verifications WHERE user_id = ? AND email = ?");
    $stmt->bind_param("is", $user_id, $email);
    $stmt->execute();
    $stmt->close();
    
    // Create new verification record (expires in 24 hours)
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $email, $token, $expires_at);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $firstName, $token) {
    $config = require __DIR__ . '/../config/email.php';
    
    // Simulate email sending for development
    if ($config['provider'] === 'simulate') {
        // Create a simple log file for development
        $verification_url = $config['templates']['base_url'] . "/verify-email.php?token=" . urlencode($token);
        $logMessage = "\n=== EMAIL SIMULATION ===\n";
        $logMessage .= "To: {$email}\n";
        $logMessage .= "Subject: {$config['templates']['verification_subject']}\n";
        $logMessage .= "Verification URL: {$verification_url}\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "=======================\n";
        
        // Log to file (create logs directory if it doesn't exist)
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logDir . '/email_simulation.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        // For immediate development feedback, also store in session
        $_SESSION['simulated_verification_url'] = $verification_url;
        $_SESSION['simulated_verification_email'] = $email;
        
        return ['success' => true, 'simulation' => true];
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Get provider-specific configuration
        $provider = $config['provider'];
        if (!isset($config[$provider])) {
            throw new Exception("Email provider '{$provider}' not configured");
        }
        
        $providerConfig = $config[$provider];
        
        // Validate required configuration
        if (empty($providerConfig['from_email'])) {
            throw new Exception("From email address not configured for provider '{$provider}'");
        }
        
        // Gmail-specific validation
        if ($provider === 'gmail') {
            if (empty($providerConfig['password'])) {
                throw new Exception("Gmail App Password is required. Please generate an App Password in your Google Account settings.");
            }
            if (!filter_var($providerConfig['username'], FILTER_VALIDATE_EMAIL) || 
                !str_ends_with($providerConfig['username'], '@gmail.com')) {
                throw new Exception("Gmail username must be a valid @gmail.com email address.");
            }
            if (strlen($providerConfig['password']) !== 16 || strpos($providerConfig['password'], ' ') !== false) {
                throw new Exception("Gmail App Password should be 16 characters without spaces. Make sure you're using an App Password, not your regular password.");
            }
        }
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $providerConfig['host'];
        $mail->SMTPAuth = $providerConfig['auth'];
        $mail->Port = $providerConfig['port'];
        
        if ($providerConfig['auth']) {
            if (empty($providerConfig['username']) || empty($providerConfig['password'])) {
                throw new Exception("SMTP username/password not configured for provider '{$provider}'");
            }
            $mail->Username = $providerConfig['username'];
            $mail->Password = $providerConfig['password'];
        }
        
        if ($providerConfig['security'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($providerConfig['security'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Enable debug output if configured
        if ($config['debug']['enabled']) {
            $mail->SMTPDebug = $config['debug']['level'];
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug Level $level: $str");
            };
        }
        
        // Recipients
        $mail->setFrom($providerConfig['from_email'], $providerConfig['from_name']);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $config['templates']['verification_subject'];
        
        $verification_url = $config['templates']['base_url'] . "/verify-email.php?token=" . urlencode($token);
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #10b981; margin: 0;'>TPLearn</h1>
            </div>
            
            <h2 style='color: #333; margin-bottom: 20px;'>Verify Your Email Address</h2>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                Hi {$firstName},
            </p>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>
                Thank you for registering with TPLearn! To complete your registration and activate your account, 
                please verify your email address by clicking the button below.
            </p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verification_url}' 
                   style='background-color: #10b981; color: white; padding: 15px 30px; text-decoration: none; 
                          border-radius: 5px; font-weight: bold; display: inline-block;'>
                    Verify Email Address
                </a>
            </div>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                If the button doesn't work, you can copy and paste this link into your browser:
            </p>
            
            <p style='color: #10b981; word-break: break-all; margin-bottom: 25px;'>
                {$verification_url}
            </p>
            
            <p style='color: #999; font-size: 14px; margin-bottom: 10px;'>
                This verification link will expire in 24 hours.
            </p>
            
            <p style='color: #999; font-size: 14px;'>
                If you didn't create an account with TPLearn, please ignore this email.
            </p>
        </div>
        ";
        
        $mail->AltBody = "
        Hi {$firstName},
        
        Thank you for registering with TPLearn! To complete your registration, please verify your email address by visiting this link:
        
        {$verification_url}
        
        This link will expire in 24 hours.
        
        If you didn't create an account with TPLearn, please ignore this email.
        ";
        
        $mail->send();
        
        // Log successful email if logging is enabled
        if ($config['debug']['log_emails']) {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logMessage = date('Y-m-d H:i:s') . " - Email sent successfully to: {$email} via {$provider}\n";
            file_put_contents($logDir . '/email_success.log', $logMessage, FILE_APPEND | LOCK_EX);
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $errorMessage = "Email sending failed: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage();
        error_log($errorMessage);
        
        // Gmail-specific error guidance
        $userErrorMessage = 'Failed to send verification email. Please try again.';
        if ($config['debug']['enabled'] || $provider === 'gmail') {
            if (strpos($errorMessage, 'Could not authenticate') !== false || 
                strpos($errorMessage, 'Username and Password not accepted') !== false) {
                if ($provider === 'gmail') {
                    $userErrorMessage = 'Gmail authentication failed. Please check: 1) You\'re using an App Password (not your regular password), 2) 2-Factor Authentication is enabled on your Gmail account, 3) The App Password is correct (16 characters).';
                } else {
                    $userErrorMessage = 'SMTP authentication failed. Please check your username and password.';
                }
            } elseif (strpos($errorMessage, 'Connection refused') !== false) {
                $userErrorMessage = 'Could not connect to email server. Please check your internet connection and firewall settings.';
            } else {
                $userErrorMessage = $errorMessage;
            }
        }
        
        // Log failed email attempts if logging is enabled
        if ($config['debug']['log_emails']) {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logMessage = date('Y-m-d H:i:s') . " - Email failed to: {$email} via {$provider} - Error: {$errorMessage}\n";
            file_put_contents($logDir . '/email_errors.log', $logMessage, FILE_APPEND | LOCK_EX);
        }
        
        return [
            'success' => false, 
            'error' => $userErrorMessage
        ];
    }
}

/**
 * Verify email token and activate user account
 */
function verifyEmailToken($token) {
    global $conn;
    
    // Check if token exists and is not expired
    $stmt = $conn->prepare("
        SELECT ev.user_id, ev.email, ev.verified_at, u.id as user_exists
        FROM email_verifications ev
        LEFT JOIN users u ON ev.user_id = u.id
        WHERE ev.token = ? AND ev.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $verification = $result->fetch_assoc();
    $stmt->close();
    
    if (!$verification) {
        return ['success' => false, 'error' => 'Invalid or expired verification token.'];
    }
    
    if (!$verification['user_exists']) {
        return ['success' => false, 'error' => 'User account not found.'];
    }
    
    if ($verification['verified_at']) {
        return ['success' => false, 'error' => 'Email address has already been verified.'];
    }
    
    // Mark email as verified
    $conn->begin_transaction();
    
    try {
        // Update verification record
        $stmt = $conn->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        
        // Update user account
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $verification['user_id']);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        return [
            'success' => true, 
            'message' => 'Email verified successfully! You can now log in to your account.',
            'user_id' => $verification['user_id']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Email verification failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to verify email. Please try again.'];
    }
}

/**
 * Check if user's email is verified
 */
function isEmailVerified($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? (bool)$user['email_verified'] : false;
}

/**
 * Resend verification email for a user
 */
function resendVerificationEmail($email) {
    global $conn;
    
    // Get user details
    $stmt = $conn->prepare("
        SELECT u.id, u.email_verified, p.first_name 
        FROM users u 
        LEFT JOIN profiles p ON u.id = p.user_id 
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        return ['success' => false, 'error' => 'No account found with this email address.'];
    }
    
    if ($user['email_verified']) {
        return ['success' => false, 'error' => 'This email address is already verified.'];
    }
    
    // Generate new token and send email
    $token = generateVerificationToken();
    $firstName = $user['first_name'] ?: 'Student';
    
    if (createEmailVerification($user['id'], $email, $token)) {
        $result = sendVerificationEmail($email, $firstName, $token);
        if ($result['success']) {
            return ['success' => true, 'message' => 'Verification email sent successfully.'];
        } else {
            return $result;
        }
    } else {
        return ['success' => false, 'error' => 'Failed to create verification record.'];
    }
}

/**
 * Clean up expired verification tokens (run periodically)
 */
function cleanupExpiredTokens() {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM email_verifications WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    return $deleted;
}

/**
 * Generate a 6-digit verification code
 */
function generateVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Initiate email change process with verification
 */
function initiateEmailChange($user_id, $current_email, $new_email, $first_name) {
    global $conn;
    
    try {
        // Check if new email is already in use
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return ['success' => false, 'error' => 'This email address is already in use by another account.'];
        }
        $stmt->close();
        
        // Generate verification code
        $verification_code = generateVerificationCode();
        
        // Delete any existing pending email changes for this user
        $stmt = $conn->prepare("DELETE FROM pending_email_changes WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Store pending email change (expires in 15 minutes)
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $stmt = $conn->prepare("INSERT INTO pending_email_changes (user_id, current_email, new_email, verification_code, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $current_email, $new_email, $verification_code, $expires_at);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to store pending email change");
        }
        $stmt->close();
        
        // Send verification code to new email
        $result = sendEmailChangeVerification($new_email, $first_name, $verification_code);
        
        if ($result['success']) {
            return [
                'success' => true, 
                'message' => 'Verification code sent to your new email address. Please check your inbox and enter the code to complete the email change.',
                'new_email' => $new_email
            ];
        } else {
            // Delete the pending change if email failed
            $stmt = $conn->prepare("DELETE FROM pending_email_changes WHERE user_id = ? AND new_email = ?");
            $stmt->bind_param("is", $user_id, $new_email);
            $stmt->execute();
            $stmt->close();
            
            return $result;
        }
        
    } catch (Exception $e) {
        error_log("Email change initiation failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to initiate email change. Please try again.'];
    }
}

/**
 * Send email change verification code
 */
function sendEmailChangeVerification($email, $firstName, $verification_code) {
    $config = require __DIR__ . '/../config/email.php';
    
    // Simulate email sending for development
    if ($config['provider'] === 'simulate') {
        error_log("=== SIMULATED EMAIL CHANGE VERIFICATION ===");
        error_log("To: {$email}");
        error_log("Subject: TPLearn - Verify Your New Email Address");
        error_log("Verification Code: {$verification_code}");
        error_log("===========================================");
        
        return ['success' => true];
    }
    
    try {
        $mail = new PHPMailer(true);
        $provider = $config['provider'];
        $providerConfig = $config['providers'][$provider];
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $providerConfig['host'];
        $mail->SMTPAuth = $providerConfig['auth'];
        $mail->Port = $providerConfig['port'];
        
        if ($providerConfig['auth']) {
            $mail->Username = $providerConfig['username'];
            $mail->Password = $providerConfig['password'];
        }
        
        if ($providerConfig['security'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($providerConfig['security'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Recipients
        $mail->setFrom($providerConfig['from_email'], $providerConfig['from_name']);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'TPLearn - Verify Your New Email Address';
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #10b981; margin: 0;'>TPLearn</h1>
            </div>
            
            <h2 style='color: #333; margin-bottom: 20px;'>Verify Your New Email Address</h2>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                Hi {$firstName},
            </p>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>
                You requested to change your email address on TPLearn. To complete this change, 
                please enter the verification code below:
            </p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <div style='background-color: #f3f4f6; padding: 20px; border-radius: 8px; display: inline-block;'>
                    <span style='font-size: 32px; font-weight: bold; color: #10b981; letter-spacing: 8px;'>
                        {$verification_code}
                    </span>
                </div>
            </div>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                Enter this code in your profile settings to confirm the email change.
            </p>
            
            <p style='color: #999; font-size: 14px; margin-bottom: 10px;'>
                This verification code will expire in 15 minutes.
            </p>
            
            <p style='color: #999; font-size: 14px;'>
                If you didn't request this email change, please ignore this email or contact support.
            </p>
        </div>
        ";
        
        $mail->AltBody = "
        Hi {$firstName},
        
        You requested to change your email address on TPLearn. 
        
        Your verification code is: {$verification_code}
        
        Enter this code in your profile settings to confirm the email change.
        
        This code will expire in 15 minutes.
        
        If you didn't request this change, please ignore this email.
        ";
        
        $mail->send();
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Email change verification failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to send verification email. Please try again.'];
    }
}

/**
 * Verify email change code and complete the change
 */
function verifyEmailChange($user_id, $verification_code) {
    global $conn;
    
    try {
        // Find pending email change
        $stmt = $conn->prepare("
            SELECT * FROM pending_email_changes 
            WHERE user_id = ? AND verification_code = ? AND expires_at > NOW()
        ");
        $stmt->bind_param("is", $user_id, $verification_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_change = $result->fetch_assoc();
        $stmt->close();
        
        if (!$pending_change) {
            return ['success' => false, 'error' => 'Invalid or expired verification code.'];
        }
        
        $conn->begin_transaction();
        
        // Update user email
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $pending_change['new_email'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete the pending change
        $stmt = $conn->prepare("DELETE FROM pending_email_changes WHERE id = ?");
        $stmt->bind_param("i", $pending_change['id']);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        return [
            'success' => true, 
            'message' => 'Email address updated successfully!',
            'new_email' => $pending_change['new_email']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Email change verification failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to verify email change. Please try again.'];
    }
}

/**
 * Get pending email change for user
 */
function getPendingEmailChange($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT new_email, expires_at FROM pending_email_changes 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = $result->fetch_assoc();
    $stmt->close();
    
    return $pending;
}

/**
 * Cancel pending email change
 */
function cancelEmailChange($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM pending_email_changes WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>