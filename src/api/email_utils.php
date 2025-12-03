<?php

/**
 * Email utility for sending emails via Gmail SMTP
 */

require_once __DIR__ . '/config.php';

function sanitize_email_header(string $value): string {
	// Remove CRLF sequences to prevent email header injection
	return str_replace(["\r", "\n", "%0a", "%0d", "\x00"], '', $value);
}

function send_email(string $to, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
	$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
	$smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
	$smtpUsername = getenv('SMTP_USERNAME');
	$smtpPassword = getenv('SMTP_PASSWORD');
	$fromEmail = getenv('SMTP_FROM_EMAIL') ?: $smtpUsername;
	$fromName = getenv('SMTP_FROM_NAME') ?: 'Koneko CTF';

	if (!$smtpUsername || !$smtpPassword) {
		error_log('[EMAIL] SMTP credentials not configured');
		return false;
	}

	error_log("[EMAIL] Attempting to send email to: $to");
	error_log("[EMAIL] Using SMTP: $smtpHost:$smtpPort with username: $smtpUsername");

	try {
		// Create SMTP connection
		error_log("[EMAIL] Connecting to SMTP server...");
		$smtp = fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);
		if (!$smtp) {
			error_log("[EMAIL] SMTP connection failed: $errstr ($errno)");
			return false;
		}

		// Helper function to read and log SMTP responses
		$readResponse = function() use ($smtp, &$lastResponse) {
			$response = fgets($smtp, 512);
			$lastResponse = $response;
			error_log("[EMAIL] SMTP Response: " . trim($response));
			return $response;
		};

		// Read server greeting
		error_log("[EMAIL] Reading server greeting...");
		$readResponse();

		// Send EHLO
		error_log("[EMAIL] Sending EHLO...");
		fputs($smtp, "EHLO " . gethostname() . "\r\n");
		// Read all EHLO responses until we get a line that doesn't start with 250-
		do {
			$response = $readResponse();
		} while (strpos($response, '250-') === 0);

		// Start TLS
		error_log("[EMAIL] Starting TLS...");
		fputs($smtp, "STARTTLS\r\n");
		$tlsResponse = $readResponse();
		
		if (strpos($tlsResponse, '220') === false) {
			error_log("[EMAIL] STARTTLS failed: $tlsResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Enabling TLS encryption...");
		$cryptoResult = stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if (!$cryptoResult) {
			error_log("[EMAIL] Failed to enable TLS encryption");
			fclose($smtp);
			return false;
		}

		// Send EHLO again after TLS
		error_log("[EMAIL] Sending EHLO after TLS...");
		fputs($smtp, "EHLO " . gethostname() . "\r\n");
		do {
			$response = $readResponse();
		} while (strpos($response, '250-') === 0);

		// Authenticate
		error_log("[EMAIL] Starting authentication...");
		fputs($smtp, "AUTH LOGIN\r\n");
		$authInitResponse = $readResponse();
		
		if (strpos($authInitResponse, '334') === false) {
			error_log("[EMAIL] AUTH LOGIN failed: $authInitResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Sending username...");
		fputs($smtp, base64_encode($smtpUsername) . "\r\n");
		$usernameResponse = $readResponse();
		
		if (strpos($usernameResponse, '334') === false) {
			error_log("[EMAIL] Username submission failed: $usernameResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Sending password...");
		fputs($smtp, base64_encode($smtpPassword) . "\r\n");
		$authResponse = $readResponse();
		
		if (strpos($authResponse, '235') === false) {
			error_log("[EMAIL] SMTP authentication failed: $authResponse");
			error_log("[EMAIL] This usually means:");
			error_log("[EMAIL]   1. Invalid Gmail App Password");
			error_log("[EMAIL]   2. 2-Step Verification not enabled on Gmail account");
			error_log("[EMAIL]   3. App Password expired or revoked");
			error_log("[EMAIL] Generate new App Password at: https://myaccount.google.com/apppasswords");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Authentication successful!");

		// Send email
		error_log("[EMAIL] Sending MAIL FROM...");
		fputs($smtp, "MAIL FROM: <$fromEmail>\r\n");
		$mailFromResponse = $readResponse();
		
		if (strpos($mailFromResponse, '250') === false) {
			error_log("[EMAIL] MAIL FROM failed: $mailFromResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Sending RCPT TO...");
		fputs($smtp, "RCPT TO: <$to>\r\n");
		$rcptResponse = $readResponse();
		
		if (strpos($rcptResponse, '250') === false) {
			error_log("[EMAIL] RCPT TO failed: $rcptResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Sending DATA command...");
		fputs($smtp, "DATA\r\n");
		$dataResponse = $readResponse();
		
		if (strpos($dataResponse, '354') === false) {
			error_log("[EMAIL] DATA command failed: $dataResponse");
			fclose($smtp);
			return false;
		}

		// Email headers and body
		$boundary = md5(uniqid(time()));
		$safeFromName = sanitize_email_header($fromName);
		$headers = "From: $safeFromName <$fromEmail>\r\n";
		$safeToName = sanitize_email_header($toName);
		$headers .= "To: $safeToName <$to>\r\n";
		$safeSubject = sanitize_email_header($subject);
		$headers .= "Subject: $safeSubject\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
		$headers .= "\r\n";

		$body = "--$boundary\r\n";
		if ($textBody) {
			$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
			$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$body .= $textBody . "\r\n\r\n";
			$body .= "--$boundary\r\n";
		}
		$body .= "Content-Type: text/html; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$body .= $htmlBody . "\r\n\r\n";
		$body .= "--$boundary--\r\n";

		error_log("[EMAIL] Sending email content...");
		fputs($smtp, $headers . $body);
		fputs($smtp, ".\r\n");
		$sendResponse = $readResponse();
		
		if (strpos($sendResponse, '250') === false) {
			error_log("[EMAIL] Email sending failed: $sendResponse");
			fclose($smtp);
			return false;
		}

		error_log("[EMAIL] Email sent successfully!");

		// Quit
		fputs($smtp, "QUIT\r\n");
		$readResponse();
		fclose($smtp);

		return true;
	} catch (Throwable $e) {
		error_log('[EMAIL] Exception during email sending: ' . $e->getMessage());
		error_log('[EMAIL] Stack trace: ' . $e->getTraceAsString());
		return false;
	}
}

function send_password_reset_email(string $email, string $fullName, string $resetToken): bool {
	$resetUrl = APP_URL . '/#reset-password?token=' . urlencode($resetToken);
	$expiryMinutes = ((int)(getenv('PASSWORD_RESET_EXPIRY') ?: 3600)) / 60;

	$htmlBody = '
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
		.container { max-width: 600px; margin: 0 auto; padding: 20px; }
		.header { background: linear-gradient(135deg, #00ffff 0%, #0066ff 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
		.content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
		.button { display: inline-block; padding: 12px 30px; background: #00ffff; color: #0a0a0f; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
		.footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
		.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>🔐 Password Reset Request</h1>
		</div>
		<div class="content">
			<p>Hello <strong>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
			
			<p>We received a request to reset your password for your Koneko CTF account.</p>
			
			<p>Click the button below to reset your password:</p>
			
			<div style="text-align: center;">
				<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" class="button">Reset Password</a>
			</div>
			
			<p>Or copy and paste this link into your browser:</p>
			<p style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; word-break: break-all;">
				' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '
			</p>
			
			<div class="warning">
				<strong>⚠️ Important:</strong> This link will expire in ' . $expiryMinutes . ' minutes.
			</div>
			
			<p><strong>Didn\'t request this?</strong><br>
			If you didn\'t request a password reset, you can safely ignore this email. Your password will not be changed.</p>
		</div>
		<div class="footer">
			<p>This is an automated email from Koneko CTF. Please do not reply to this email.</p>
			<p>&copy; ' . date('Y') . ' Koneko CTF. All rights reserved.</p>
		</div>
	</div>
</body>
</html>';

	$textBody = "Hello $fullName,\n\n";
	$textBody .= "We received a request to reset your password for your Koneko CTF account.\n\n";
	$textBody .= "Click this link to reset your password:\n";
	$textBody .= "$resetUrl\n\n";
	$textBody .= "This link will expire in $expiryMinutes minutes.\n\n";
	$textBody .= "If you didn't request a password reset, you can safely ignore this email.\n\n";
	$textBody .= "-- Koneko CTF Team";

	return send_email(
		$email,
		$fullName,
		'Password Reset Request - Koneko CTF',
		$htmlBody,
		$textBody
	);
}
