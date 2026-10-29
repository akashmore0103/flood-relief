<?php
/**
 * Standardized Response Formatter (JSON and XML)
 * Flood Risk Prediction & Relief Camp Locator
 */

class Response {
    /**
     * Send a standardized JSON response and terminate execution
     */
    public static function json(bool $success, string $message = '', mixed $data = null, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c'),
            'data' => $data
        ];

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send JSON success response
     */
    public static function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): void {
        self::json(true, $message, $data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): void {
        self::json(false, $message, $errors, $statusCode);
    }

    /**
     * Send an XML response (satisfying PRD requirement for XML formats)
     */
    public static function xml(string $rootElement, array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/xml; charset=utf-8');

        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}></{$rootElement}>");
        self::arrayToXml($data, $xml);

        echo $xml->asXML();
        exit;
    }

    /**
     * Recursive helper to convert nested associative array to XML elements
     */
    private static function arrayToXml(array $data, SimpleXMLElement &$xmlElement): void {
        foreach ($data as $key => $value) {
            $key = is_numeric($key) ? 'item' . $key : preg_replace('/[^a-z0-9_]/i', '', $key);
            if (is_array($value)) {
                $subnode = $xmlElement->addChild($key);
                self::arrayToXml($value, $subnode);
            } else {
                $xmlElement->addChild($key, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
            }
        }
    }
}
