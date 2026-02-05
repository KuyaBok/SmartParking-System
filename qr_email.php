<?php
// Reusable helper to send QR code emails. Uses PHPMailer if available, otherwise falls back to PHP mail().
require_once __DIR__ . '/config.php';

function sendQrEmail($to, $owner, $file) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure, $smtp_from_email, $smtp_from_name;

    $subject = 'Your Parking QR Code';
    $bodyText = "Hello " . $owner . ",\n\nAttached is your parking QR code. Present this at the gate for entry/exit.\n\nRegards,\nParking Admin";

    // Try Composer/modern PHPMailer first
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    // Also support older non-namespaced PHPMailer v4 placed under /PHPMailer/_lib/
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/PHPMailer/_lib/class.phpmailer.php')) {
        require_once __DIR__ . '/PHPMailer/_lib/class.phpmailer.php';
        if (file_exists(__DIR__ . '/PHPMailer/_lib/class.smtp.php')) {
            require_once __DIR__ . '/PHPMailer/_lib/class.smtp.php';
        }
    }

    // Use modern namespaced PHPMailer if available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            if (!empty($smtp_host) && !empty($smtp_user)) {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_pass;
                $mail->SMTPSecure = $smtp_secure;
                $mail->Port = $smtp_port;
            }
            $mail->setFrom($smtp_from_email ?: 'no-reply@localhost', $smtp_from_name ?: 'SmartPark');
            $mail->addAddress($to, $owner);
            $mail->Subject = $subject;
            $mail->Body = $bodyText;

            // Attach file only if it exists
            if ($file && file_exists($file)) {
                $mail->addAttachment($file);
            } else {
                return ['ok' => false, 'msg' => 'QR file not found for attachment.'];
            }

            $mail->send();
            return ['ok' => true, 'msg' => 'Email sent successfully (PHPMailer).'];
        } catch (Exception $e) {
            return ['ok' => false, 'msg' => 'PHPMailer error: ' . $e->getMessage()];
        }
    }

    // Support legacy PHPMailer v4 (class name: PHPMailer)
    if (class_exists('PHPMailer')) {
        try {
            $mail = new PHPMailer(true);
            if (!empty($smtp_host) && !empty($smtp_user)) {
                $mail->IsSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_pass;
                // v4 uses SMTPSecure as well
                if (!empty($smtp_secure)) $mail->SMTPSecure = $smtp_secure;
                $mail->Port = $smtp_port;
            }
            $mail->SetFrom($smtp_from_email ?: 'no-reply@localhost', $smtp_from_name ?: 'SmartPark');
            $mail->AddAddress($to, $owner);
            $mail->Subject = $subject;
            $mail->Body = $bodyText;

            if ($file && file_exists($file)) {
                $mail->AddAttachment($file);
            } else {
                return ['ok' => false, 'msg' => 'QR file not found for attachment.'];
            }

            if ($mail->Send()) {
                return ['ok' => true, 'msg' => 'Email sent successfully (PHPMailer v4).'];
            }
            return ['ok' => false, 'msg' => 'PHPMailer v4 failed to send.'];
        } catch (Exception $e) {
            return ['ok' => false, 'msg' => 'PHPMailer v4 error: ' . $e->getMessage()];
        }
    }

    // Fallback to PHP mail multipart
    if (!$to || !$file || !file_exists($file)) {
        return ['ok' => false, 'msg' => 'Invalid email or QR file not found.'];
    }

    $separator = md5(time());
    $eol = PHP_EOL;
    $filename = basename($file);
    $attachment = chunk_split(base64_encode(file_get_contents($file)));

    // Headers
    $headers = "From: " . ($smtp_from_email ?: 'no-reply@localhost') . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol . $eol;

    // Body
    $body = "--" . $separator . $eol;
    $body .= "Content-Type: text/plain; charset=iso-8859-1" . $eol;
    $body .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
    $body .= $bodyText . $eol . $eol;

    $body .= "--" . $separator . $eol;
    $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
    $body .= "Content-Transfer-Encoding: base64" . $eol;
    $body .= "Content-Disposition: attachment; filename=\"" . $filename . "\"" . $eol . $eol;
    $body .= $attachment . $eol;
    $body .= "--" . $separator . "--";

    $sent = @mail($to, $subject, $body, $headers);
    if ($sent) {
        return ['ok' => true, 'msg' => 'Email sent successfully (mail).'];
    }
    return ['ok' => false, 'msg' => 'Failed to send email. Please ensure your server is configured to send mail (SMTP).'];
}
