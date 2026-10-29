<?php
/**
 * Global Utility & Helper Functions
 * Flood Risk Prediction & Relief Camp Locator
 */

class Helper {
    /**
     * Escape output for HTML (XSS prevention)
     */
    public static function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Recursively sanitize input data
     */
    public static function sanitize(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        if (is_string($data)) {
            return trim(strip_tags($data));
        }
        return $data;
    }

    /**
     * Generate unique Emergency Help Request Reference Code
     * Format: REQ-YYYYMMDD-XXXX (e.g., REQ-20260905-9482)
     */
    public static function generateReferenceCode(): string {
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return "REQ-{$datePart}-{$randomPart}";
    }

    /**
     * Format standard datetime to human readable string
     */
    public static function formatDateTime(?string $datetime, string $format = 'd M Y, h:i A'): string {
        if (!$datetime) return 'N/A';
        try {
            $dt = new DateTime($datetime);
            return $dt->format($format);
        } catch (Exception $e) {
            return $datetime;
        }
    }

    /**
     * Human-friendly relative time (e.g., "5 mins ago", "2 hours ago")
     */
    public static function timeAgo(?string $datetime): string {
        if (!$datetime) return 'Never';
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) return "Just now";
        if ($diff < 3600) return floor($diff / 60) . " mins ago";
        if ($diff < 86400) return floor($diff / 3600) . " hours ago";
        if ($diff < 2592000) return floor($diff / 86400) . " days ago";
        return date('d M Y', $timestamp);
    }

    /**
     * Return Bootstrap color badge class based on risk category
     */
    public static function getRiskBadgeClass(?string $category): string {
        return match (strtoupper($category ?? '')) {
            'CRITICAL' => 'danger',
            'HIGH'     => 'warning text-dark',
            'MODERATE' => 'info text-dark',
            'LOW'      => 'success',
            default    => 'secondary'
        };
    }

    /**
     * Return Bootstrap color badge class based on request urgency
     */
    public static function getUrgencyBadgeClass(?string $urgency): string {
        return match (strtoupper($urgency ?? '')) {
            'CRITICAL' => 'danger',
            'HIGH'     => 'warning text-dark',
            'MEDIUM'   => 'info text-dark',
            'LOW'      => 'secondary',
            default    => 'light text-dark'
        };
    }

    /**
     * Return Bootstrap color badge class based on help request status
     */
    public static function getStatusBadgeClass(?string $status): string {
        return match (strtoupper($status ?? '')) {
            'NEW'          => 'primary',
            'ACKNOWLEDGED' => 'info text-dark',
            'IN PROGRESS'  => 'warning text-dark',
            'RESOLVED'     => 'success',
            'CLOSED'       => 'secondary',
            default        => 'light text-dark'
        };
    }

    /**
     * Set a session flash message
     */
    public static function setFlash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_messages'][] = [
            'type' => $type, // success, danger, warning, info
            'message' => $message
        ];
    }

    /**
     * Retrieve and clear flash messages
     */
    public static function getFlash(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    /**
     * Check if flash messages exist
     */
    public static function hasFlash(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['flash_messages']);
    }

    /**
     * Safe redirect helper
     */
    public static function redirect(string $path): void {
        $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
            ? $path
            : rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        header("Location: {$url}");
        exit;
    }
}
