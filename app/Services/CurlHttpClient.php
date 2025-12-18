<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * HTTP client service using cURL for making HTTP requests.
 * Provides a simple interface for GET and POST requests with custom headers.
 */
class CurlHttpClient
{
    /**
     * Default timeout for requests in seconds
     */
    protected int $timeout = 240;

    /**
     * Default connection timeout in seconds
     */
    protected int $connectTimeout = 30;

    /**
     * Set request timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set connection timeout
     *
     * @param int $timeout Connection timeout in seconds
     * @return self
     */
    public function setConnectTimeout(int $timeout): self
    {
        $this->connectTimeout = $timeout;
        return $this;
    }

    /**
     * Make a POST request using cURL
     *
     * @param string $url Request URL
     * @param array $headers Associative array of headers ['Header-Name' => 'value']
     * @param array|string $body Request body (array will be JSON encoded)
     * @param bool $returnHeaders Whether to return response headers
     *
     * @return array [
     *   'status_code' => int,
     *   'body' => string,
     *   'headers' => array (if $returnHeaders is true)
     * ]
     *
     * @throws \Exception on cURL errors
     */
    public function post(string $url, array $headers = [], $body = null, bool $returnHeaders = false): array
    {
        $ch = curl_init();

        // Prepare body
        $bodyData = is_array($body) ? json_encode($body) : $body;

        // Build headers array for cURL
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        // Configure cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $bodyData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_HEADER => $returnHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Execute request
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        // Check for cURL errors
        if ($errno !== 0) {
            Log::error('cURL Error', [
                'url' => $url,
                'error' => $error,
                'errno' => $errno,
            ]);
            throw new \Exception("cURL error ({$errno}): {$error}");
        }

        // Parse response if headers are requested
        $result = [
            'status_code' => $statusCode,
            'body' => $response,
        ];

        if ($returnHeaders && $response !== false) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerString = substr($response, 0, $headerSize);
            $result['body'] = substr($response, $headerSize);
            $result['headers'] = $this->parseHeaders($headerString);
        }

        return $result;
    }

    /**
     * Make a GET request using cURL
     *
     * @param string $url Request URL
     * @param array $headers Associative array of headers ['Header-Name' => 'value']
     * @param bool $returnHeaders Whether to return response headers
     *
     * @return array [
     *   'status_code' => int,
     *   'body' => string,
     *   'headers' => array (if $returnHeaders is true)
     * ]
     *
     * @throws \Exception on cURL errors
     */
    public function get(string $url, array $headers = [], bool $returnHeaders = false): array
    {
        $ch = curl_init();

        // Build headers array for cURL
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        // Configure cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_HEADER => $returnHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Execute request
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        // Check for cURL errors
        if ($errno !== 0) {
            Log::error('cURL Error', [
                'url' => $url,
                'error' => $error,
                'errno' => $errno,
            ]);
            throw new \Exception("cURL error ({$errno}): {$error}");
        }

        // Parse response if headers are requested
        $result = [
            'status_code' => $statusCode,
            'body' => $response,
        ];

        if ($returnHeaders && $response !== false) {
            $result['body'] = substr($response, $headerSize);
            $result['headers'] = $this->parseHeaders(substr($response, 0, $headerSize));
        }

        return $result;
    }

    /**
     * Parse HTTP headers from header string
     *
     * @param string $headerString Raw header string
     * @return array Associative array of headers
     */
    protected function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
