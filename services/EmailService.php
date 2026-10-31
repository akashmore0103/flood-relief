<?php
/**
 * Emergency Alert Email Dispatch Service
 * Geofenced Emergency Notification & Resilient Socket SMTP Dispatch Pipeline
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/core/Database.php';

class EmailService {
    private static ?array $config = null;

    /**
     * Load environment configuration from System Environment (Render/Docker) or .env file
     */
public static function loadConfig(bool $forceReload = false): array {
        if (self::$config !== null && !$forceReload) {
            return self::$config;
        }

        // थेट कार्यरत Gmail SMTP कॉन्फिगरेशन
        $config = [
            'smtp_host'   => 'smtp.gmail.com',
            'smtp_port'   => 587,
            'smtp_user'   => 'Akashmore6381@gmail.com',
            'smtp_pass'   => 'tumcha_16_digit_app_password', // <-- इथे तुमचा 16-अक्षरी Gmail App Password टाका (उदा. abcd efgh ijkl mnop)
            'smtp_secure' => 'tls',
            'from_email'  => 'Akashmore6381@gmail.com',
            'from_name'   => 'FloodRelief Emergency Operations Command',
            'timeout'     => 15,
            'env_loaded'  => true,
            'env_source'  => 'hardcoded_gmail_production'
        ];

        // जर Render Environment मध्ये व्हॅल्यूज असतील तर त्या वाचणे
        $envHost = getenv('MAIL_HOST') ?: getenv('SMTP_HOST');
        $envPort = getenv('MAIL_PORT') ?: getenv('SMTP_PORT');
        $envUser = getenv('MAIL_USERNAME') ?: getenv('SMTP_USER');
        $envPass = getenv('MAIL_PASSWORD') ?: getenv('SMTP_PASS');

        if (!empty($envHost) && $envHost !== 'localhost') $config['smtp_host'] = $envHost;
        if (!empty($envPort) && (int)$envPort !== 1025)   $config['smtp_port'] = (int)$envPort;
        if (!empty($envUser))                             $config['smtp_user'] = $envUser;
        if (!empty($envPass))                             $config['smtp_pass'] = $envPass;

        self::$config = $config;
        return self::$config;
    }

    /**
     * Retrieve active configuration (masking secret keys for safe inspection)
     */
    public static function getConfig(): array {
        $c = self::loadConfig();
        $safe = $c;
        if (!empty($safe['smtp_pass'])) {
            $safe['smtp_pass'] = str_repeat('*', min(8, strlen($safe['smtp_pass'])));
        }
        return $safe;
    }

    /**
     * Dispatch High-Priority Emergency Flood Alert Email
     */
    public static function sendEmergencyAlert(array $recipient, array $alertData, ?array $shelterData = null, ?array $overrideConfig = null): array {
        $config = $overrideConfig ? array_merge(self::loadConfig(), $overrideConfig) : self::loadConfig();

        $toEmail = trim($recipient['email'] ?? '');
        $toName = trim($recipient['name'] ?? ($recipient['full_name'] ?? 'Citizen'));

        // Skip sending to dummy demo addresses like '@example.com' to prevent socket failure
        if (str_ends_with(strtolower($toEmail), '@example.com')) {
            $result = [
                'sent' => false,
                'error' => "Skipped demo/placeholder address: '{$toEmail}'",
                'attempted_to' => $toEmail,
                'smtp_host_used' => "{$config['smtp_host']}:{$config['smtp_port']} (" . strtoupper($config['smtp_secure']) . ")",
                'transcript' => ["DEMO_RECIPIENT_SKIPPED: Skipped non-routable demo address '{$toEmail}'"]
            ];
            self::logToEmailLog('SKIPPED', $toEmail, $toName, $alertData['title'] ?? 'Emergency Warning', $config, $result['error'], $result['transcript']);
            return $result;
        }

        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $result = [
                'sent' => false,
                'error' => "Invalid or empty recipient email address: '{$toEmail}'",
                'attempted_to' => $toEmail,
                'smtp_host_used' => "{$config['smtp_host']}:{$config['smtp_port']} (" . strtoupper($config['smtp_secure']) . ")",
                'transcript' => ["VALIDATION_FAILED: Invalid recipient email address '{$toEmail}'"]
            ];

            self::logToEmailLog('FAILED', $toEmail, $toName, $alertData['title'] ?? 'Emergency Warning', $config, $result['error'], $result['transcript']);
            return $result;
        }

        $subject = "URGENT: " . ($alertData['severity'] ?? 'Red Alert') . " - " . ($alertData['title'] ?? 'Flood Evacuation Advisory');
        $htmlBody = self::buildHtmlTemplate($toName, $alertData, $shelterData);
        $plainText = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $htmlBody));

        // Direct low-level socket SMTP delivery
        $result = self::sendViaSocketSmtp($config, $toEmail, $toName, $subject, $htmlBody, $plainText);

        // Hard-log full transcript and error reason to storage/logs/email.log
        self::logToEmailLog(
            $result['sent'] ? 'SUCCESS' : 'FAILED',
            $toEmail,
            $toName,
            $subject,
            $config,
            $result['error'],
            $result['transcript']
        );

        return $result;
    }

    /**
     * Hard-log SMTP delivery attempts and transcripts to storage/logs/email.log
     */
    public static function logToEmailLog(string $status, string $toEmail, string $toName, string $subject, array $config, ?string $error, array $transcript): void {
        try {
            $logDir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $logFile = $logDir . '/email.log';

            $now = date('Y-m-d H:i:s');
            $secUpper = strtoupper($config['smtp_secure'] ?? 'NONE');
            $hostTarget = "{$config['smtp_host']}:{$config['smtp_port']} (Security: {$secUpper}, Timeout: {$config['timeout']}s)";
            $sender = "{$config['from_name']} <{$config['from_email']}>";

            $entry = "================================================================================\n";
            $entry .= "TIMESTAMP:   {$now}\n";
            $entry .= "STATUS:      {$status}\n";
            $entry .= "RECIPIENT:   {$toEmail} (" . (!empty($toName) ? $toName : 'Citizen') . ")\n";
            $entry .= "SUBJECT:     {$subject}\n";
            $entry .= "SMTP TARGET: {$hostTarget}\n";
            $entry .= "SENDER:      {$sender}\n";
            $entry .= "AUTH USER:   " . (!empty($config['smtp_user']) ? $config['smtp_user'] : 'None (Anonymous)') . "\n";
            $entry .= "ERROR:       " . ($error ?: "None (Delivered successfully)") . "\n";
            $entry .= "--- SMTP CONVERSATION TRANSCRIPT ---\n";
            
            if (empty($transcript)) {
                $entry .= "[No socket communication occurred]\n";
            } else {
                foreach ($transcript as $line) {
                    $entry .= "  {$line}\n";
                }
            }
            
            $entry .= "================================================================================\n\n";

            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            error_log("Failed writing to email.log: " . $e->getMessage());
        }
    }

    /**
     * Record dispatch entry in the database `alert_delivery_logs` table
     */
    public static function logDelivery(int $alertId, string $channel, string $recipient, string $status, ?string $details = null): bool {
        try {
            $db = Database::getInstance();
            $db->insert('alert_delivery_logs', [
                'alert_id' => $alertId,
                'channel' => $channel,
                'recipient_target' => $recipient,
                'delivery_status' => in_array($status, ['Sent', 'Delivered', 'Failed']) ? $status : 'Sent',
                'details' => $details
            ]);
            return true;
        } catch (Throwable $e) {
            error_log("Failed to log alert delivery to database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Low-level Socket SMTP Engine supporting SSL (465) and STARTTLS (587)
     */
    private static function sendViaSocketSmtp(array $config, string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText): array {
        $host = $config['smtp_host'];
        $port = (int)$config['smtp_port'];
        $secure = strtolower($config['smtp_secure'] ?? 'none');
        $timeout = (int)($config['timeout'] ?? 10);
        $clientHost = gethostname() ?: 'localhost';

        $hostLabel = "{$host}:{$port} (" . strtoupper($secure) . ")";
        $transcript = [];
        $socket = null;

        try {
            // Step 1: Open Socket Connection
            if ($secure === 'ssl' || $port === 465) {
                $streamTarget = "ssl://{$host}:{$port}";
                $transcript[] = "SYSTEM: Initiating SSL socket connection to {$streamTarget} (timeout: {$timeout}s)...";

                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                    ]
                ]);

                $socket = @stream_socket_client($streamTarget, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            } else {
                $streamTarget = "tcp://{$host}:{$port}";
                $transcript[] = "SYSTEM: Initiating TCP connection to {$streamTarget} (timeout: {$timeout}s)...";

                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ]);

                $socket = @stream_socket_client($streamTarget, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            }

            if (!$socket) {
                throw new Exception("Connection to {$host}:{$port} failed: ({$errno}) {$errstr}");
            }

            stream_set_timeout($socket, $timeout);

            // Response reader helper (handles multiline replies RFC 5321)
            $readResponse = function() use ($socket, &$transcript, $host, $port) {
                $fullResponse = "";
                $code = 0;
                while (!feof($socket)) {
                    $line = fgets($socket, 1024);
                    if ($line === false) break;
                    $fullResponse .= $line;
                    $transcript[] = "SERVER: " . rtrim($line, "\r\n");

                    if (preg_match('/^([0-9]{3})([ -])(.*)$/', $line, $matches)) {
                        $code = (int)$matches[1];
                        if ($matches[2] === ' ') {
                            break;
                        }
                    }
                }
                if ($code === 0 && empty($fullResponse)) {
                    throw new Exception("Connection dropped or timed out waiting for response from {$host}:{$port}");
                }
                return ['code' => $code, 'raw' => trim($fullResponse)];
            };

            // Command writer helper
            $sendCommand = function(string $cmd, bool $maskInTranscript = false) use ($socket, &$transcript) {
                fputs($socket, $cmd . "\r\n");
                if ($maskInTranscript) {
                    $transcript[] = "CLIENT: [CREDENTIAL_DATA_PROTECTED]";
                } else {
                    $transcript[] = "CLIENT: " . $cmd;
                }
            };

            // Step 2: Read initial SMTP server greeting (Expect 220)
            $greet = $readResponse();
            if ($greet['code'] !== 220) {
                throw new Exception("Invalid SMTP greeting from {$host}: " . $greet['raw']);
            }

            // Step 3: Send initial EHLO
            $sendCommand("EHLO " . $clientHost);
            $ehloResp = $readResponse();
            if ($ehloResp['code'] !== 250) {
                $sendCommand("HELO " . $clientHost);
                $heloResp = $readResponse();
                if ($heloResp['code'] !== 250) {
                    throw new Exception("Handshake (EHLO/HELO) rejected by {$host}: " . $heloResp['raw']);
                }
            }

            // Step 4: Handle STARTTLS on Port 587
            if ($secure === 'tls' || $port === 587) {
                $transcript[] = "SYSTEM: Requesting STARTTLS encryption negotiation...";
                $sendCommand("STARTTLS");
                $tlsResp = $readResponse();
                if ($tlsResp['code'] !== 220) {
                    throw new Exception("STARTTLS command rejected by {$host}: " . $tlsResp['raw']);
                }

                $transcript[] = "SYSTEM: Enabling stream crypto (TLSv1.2 / TLSv1.3)...";
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                if ($cryptoOk !== true) {
                    throw new Exception("TLS cryptographic handshake failed with {$host}:{$port}. Please check OpenSSL stream configuration.");
                }

                $transcript[] = "SYSTEM: TLS handshake successful. Stream is now encrypted.";

                // RFC 3207: Must re-issue EHLO after STARTTLS
                $sendCommand("EHLO " . $clientHost);
                $ehloSecureResp = $readResponse();
                if ($ehloSecureResp['code'] !== 250) {
                    throw new Exception("Post-TLS EHLO rejected by {$host}: " . $ehloSecureResp['raw']);
                }
            }

            // Step 5: SMTP Authentication (AUTH LOGIN)
            if (!empty($config['smtp_user']) && !empty($config['smtp_pass'])) {
                $transcript[] = "SYSTEM: Initiating AUTH LOGIN for user '{$config['smtp_user']}'...";
                $sendCommand("AUTH LOGIN");
                $authResp = $readResponse();
                if ($authResp['code'] !== 334) {
                    throw new Exception("AUTH LOGIN rejected by {$host}: " . $authResp['raw']);
                }

                $sendCommand(base64_encode($config['smtp_user']));
                $userResp = $readResponse();
                if ($userResp['code'] !== 334) {
                    throw new Exception("SMTP Username rejected by {$host}: " . $userResp['raw']);
                }

                $sendCommand(base64_encode($config['smtp_pass']), true);
                $passResp = $readResponse();
                if ($passResp['code'] !== 235) {
                    throw new Exception("SMTP Authentication failed for user '{$config['smtp_user']}': " . $passResp['raw']);
                }

                $transcript[] = "SYSTEM: Authentication verified successfully (235).";
            }

            // Step 6: Envelope Sender (MAIL FROM)
            $fromAddr = $config['from_email'];
            $sendCommand("MAIL FROM: <{$fromAddr}>");
            $fromResp = $readResponse();
            if ($fromResp['code'] !== 250) {
                throw new Exception("MAIL FROM <{$fromAddr}> rejected by {$host}: " . $fromResp['raw']);
            }

            // Step 7: Envelope Recipient (RCPT TO)
            $sendCommand("RCPT TO: <{$toEmail}>");
            $rcptResp = $readResponse();
            if ($rcptResp['code'] !== 250 && $rcptResp['code'] !== 251) {
                throw new Exception("Recipient <{$toEmail}> rejected by {$host}: " . $rcptResp['raw']);
            }

            // Step 8: Initiate Message Data (DATA)
            $sendCommand("DATA");
            $dataResp = $readResponse();
            if ($dataResp['code'] !== 354) {
                throw new Exception("DATA command rejected by {$host}: " . $dataResp['raw']);
            }

            // Step 9: Assemble & Transmit MIME Multipart Message
            $boundary = "==_Multipart_Boundary_x" . md5(microtime(true) . $toEmail);

            // Step 9: Assemble & Transmit MIME Multipart Message
            $boundary = "==_Multipart_Boundary_x" . md5(microtime(true) . $toEmail);

            $headers = [
                "From: =?UTF-8?B?" . base64_encode($config['from_name']) . "?= <{$fromAddr}>",
                "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
                "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
                "Date: " . date('r'),
                "MIME-Version: 1.0",
                "X-Priority: 1 (Highest)",
                "X-MSMail-Priority: High",
                "Importance: High",
                "X-Mailer: FloodRelief-DSS/4.0-SMTP",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\""
            ];

            $payload = implode("\r\n", $headers) . "\r\n\r\n";

            // Plain text part
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($plainText)) . "\r\n";

            // HTML part
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            $payload .= "--{$boundary}--\r\n";
            $payload .= ".";

            fputs($socket, $payload . "\r\n");
            $transcript[] = "CLIENT: [MIME multipart payload dispatched, size: " . strlen($payload) . " bytes]";

            $sendResp = $readResponse();
            if ($sendResp['code'] !== 250) {
                throw new Exception("Message transmission rejected by {$host}: " . $sendResp['raw']);
            }

            // Step 10: Clean Disconnect (QUIT)
            $sendCommand("QUIT");
            try { $readResponse(); } catch (Throwable) {}
            @fclose($socket);

            return [
                'sent' => true,
                'error' => null,
                'attempted_to' => $toEmail,
                'smtp_host_used' => $hostLabel,
                'transcript' => $transcript
            ];

        } catch (Throwable $e) {
            if ($socket && is_resource($socket)) {
                try {
                    fputs($socket, "QUIT\r\n");
                } catch (Throwable) {}
                @fclose($socket);
            }

            $transcript[] = "ERROR: " . $e->getMessage();

            return [
                'sent' => false,
                'error' => $e->getMessage(),
                'attempted_to' => $toEmail,
                'smtp_host_used' => $hostLabel,
                'transcript' => $transcript
            ];
        }
    }

    /**
     * Responsive Emergency Flood Warning HTML Template
     */
    private static function buildHtmlTemplate(string $name, array $alertData, ?array $shelterData = null): string {
        $severity = strtoupper($alertData['severity'] ?? 'RED ALERT');
        $title = htmlspecialchars($alertData['title'] ?? 'Severe Flood Inundation Warning');
        $message = nl2br(htmlspecialchars($alertData['message'] ?? 'Immediate evacuation advisory issued. Dam water discharge has crossed critical safety limits.'));
        $damName = htmlspecialchars($alertData['dam_name'] ?? 'Regional Dam');
        $basinName = htmlspecialchars($alertData['basin_name'] ?? 'River Basin');
        $timestamp = date('d M Y, h:i A T');

        $shelterName = htmlspecialchars($shelterData['display_name'] ?? ($shelterData['name'] ?? 'Designated Municipal Relief Shelter'));
        $shelterAddress = htmlspecialchars($shelterData['address'] ?? 'Safe Highland Municipal Grounds');
        $shelterDistrict = htmlspecialchars($shelterData['district'] ?? 'Disaster Safe Zone');
        $availableBeds = (int)($shelterData['available_beds'] ?? 350);
        $totalCapacity = (int)($shelterData['total_capacity'] ?? 500);
        $supplyStatus = htmlspecialchars($shelterData['supply_status'] ?? 'Sufficient');
        $contactPerson = htmlspecialchars($shelterData['contact_person'] ?? 'District Disaster Control Cell');
        $contactPhone = htmlspecialchars($shelterData['contact_phone'] ?? '+91 94231 88765');
        
        $lat = $shelterData['latitude'] ?? '19.9985';
        $lng = $shelterData['longitude'] ?? '73.7250';
        $directionsUrl = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$severity}: Flood Emergency Notification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #0f172a; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 620px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    
                    <!-- Top Emergency Red Banner -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 30px 35px; text-align: center;">
                            <div style="display: inline-block; background-color: rgba(255,255,255,0.2); color: #ffffff; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; margin-bottom: 12px;">
                                &#9888; CRITICAL DISASTER BROADCAST
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; line-height: 1.3;">
                                {$severity} WARNING
                            </h1>
                            <p style="margin: 8px 0 0 0; color: #fecaca; font-size: 13px; font-weight: 500;">
                                Issued for {$damName} &bull; {$basinName}
                            </p>
                        </td>
                    </tr>

                    <!-- Main Body Content -->
                    <tr>
                        <td style="padding: 35px 35px 25px 35px;">
                            <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.5; color: #0f172a;">
                                <strong>Namaste {$name},</strong>
                            </p>
                            
                            <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 18px 20px; border-radius: 8px; margin-bottom: 25px;">
                                <h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 700; color: #991b1b;">
                                    {$title}
                                </h3>
                                <div style="font-size: 14px; line-height: 1.6; color: #7f1d1d;">
                                    {$message}
                                </div>
                                <div style="margin-top: 12px; font-size: 12px; color: #b91c1c; font-weight: 600;">
                                    &#128340; Broadcast Timestamp: {$timestamp}
                                </div>
                            </div>

                            <!-- Designated Relief Shelter Card -->
                            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; margin-bottom: 25px;">
                                <div style="color: #2563eb; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
                                    &#127968; YOUR DESIGNATED EMERGENCY SHELTER
                                </div>
                                <h2 style="margin: 0 0 6px 0; font-size: 19px; font-weight: 700; color: #0f172a;">
                                    {$shelterName}
                                </h2>
                                <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; line-height: 1.4;">
                                    {$shelterAddress}, {$shelterDistrict}
                                </p>

                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 18px;">
                                    <tr>
                                        <td width="33%" style="background-color: #ffffff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                                            <div style="font-size: 11px; color: #64748b; font-weight: 600;">AVAILABLE BEDS</div>
                                            <div style="font-size: 18px; color: #16a34a; font-weight: 800; margin-top: 3px;">{$availableBeds} / {$totalCapacity}</div>
                                        </td>
                                        <td width="5%"></td>
                                        <td width="62%" style="background-color: #ffffff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: left;">
                                            <div style="font-size: 11px; color: #64748b; font-weight: 600;">SHELTER OFFICER / PHONE</div>
                                            <div style="font-size: 13px; color: #0f172a; font-weight: 700; margin-top: 3px;">
                                                {$contactPerson}<br>
                                                <a href="tel:{$contactPhone}" style="color: #2563eb; text-decoration: none;">{$contactPhone}</a>
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Google Maps Direct Navigation Button -->
                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td align="center">
                                            <a href="{$directionsUrl}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #dc2626; color: #ffffff; text-decoration: none; text-align: center; padding: 14px 20px; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);">
                                                &#10148; Get Directions to Safe Shelter (Google Maps)
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Emergency Helplines Callout Box -->
                            <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 16px; margin-bottom: 25px;">
                                <div style="font-size: 12px; font-weight: 700; color: #92400e; text-transform: uppercase; margin-bottom: 8px;">
                                    &#9742; 24/7 Rapid Emergency Helplines:
                                </div>
                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="25%" style="font-size: 12px; color: #78350f;">
                                            National SOS: <strong>112</strong>
                                        </td>
                                        <td width="35%" style="font-size: 12px; color: #78350f;">
                                            Disaster Cell: <strong>1077 / 1070</strong>
                                        </td>
                                        <td width="20%" style="font-size: 12px; color: #78350f;">
                                            Ambulance: <strong>108</strong>
                                        </td>
                                        <td width="20%" style="font-size: 12px; color: #78350f;">
                                            Fire/Rescue: <strong>101</strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                                This emergency alert is automatically targeted to citizens registered within the <strong>{$damName}</strong> flood vulnerability polygon. If you need urgent rescue or medical evacuation, submit a request on the FloodRelief portal or dial 112 immediately.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 20px 35px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; font-size: 11px; color: #64748b;">
                                FloodRelief DSS &bull; State Disaster Management Authority &bull; Hydrological Early Warning System
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}