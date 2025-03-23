<?php
/****************************************************
 * Gemina API Example in PHP (single file)
 * 
 * This script performs the following logic:
 *   1. Upload an image (either from disk or via URL).
 *   2. Repeatedly poll the API for prediction results
 *      until a final status (200) or an error occurs.
 ****************************************************/

/**
 * Generate a UUIDv4 for the external_id
 */
function generate_uuid(): string {
    // Generates a pseudo-random UUID (RFC 4122 version 4)
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * cURL helper to make HTTP requests
 *
 * @param string $method  The HTTP method ("GET", "POST", "PUT", "DELETE", etc.)
 * @param string $url     The endpoint URL
 * @param array  $headers An array of HTTP headers
 * @param mixed  $body    The request body (JSON-encoded string for Gemina)
 *
 * @return array  ['http_code' => int, 'response' => string]
 */
function doRequest(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Setup headers
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // If we have a POST/PUT body
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception('Request Error: ' . curl_error($ch));
    }

    curl_close($ch);

    return [
        'http_code' => $http_code,
        'response'  => $response
    ];
}

/****************************************************
 * Configuration
 ****************************************************/
$API_KEY  = '== YOUR API KEY ==';     // e.g. "abcdef123456"
$CLIENT_ID = '== YOUR CLIENT KEY =='; // e.g. "123"
$INVOICE_URI = 'invoice.png'; // e.g. "invoice.png" or "https://example.com/invoice.png"

// Gemina Endpoints
$GEMINA_API_URL         = 'https://api.gemina.co.il/v1';
$UPLOAD_URL             = '/uploads';
$WEB_UPLOAD_URL         = '/uploads/web';
$BUSINESS_DOCUMENTS_URL = '/business_documents';

// Generate unique ID for your file
$IMAGE_ID = generate_uuid();

/****************************************************
 * 1) Upload image from local disk
 ****************************************************/
function uploadImage(string $apiKey, string $clientId, string $apiUrl, string $uploadPath, string $imageId): array
{
    $url       = $apiUrl . $uploadPath;
    $token     = "Basic " . $apiKey; // "Basic <API_KEY>"
    $headers   = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: ' . $token
    ];

    // Read and encode local file (invoice.png)
    $imageBase64 = base64_encode(file_get_contents('./invoice.png'));

    $payload = json_encode([
        "external_id" => $imageId,
        "client_id"   => $clientId,
        "use_llm"     => true, // optional param
        "file"        => $imageBase64
    ]);

    $response = doRequest('POST', $url, $headers, $payload);
    $httpCode = $response['http_code'];

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Error! status: $httpCode - " . $response['response']);
    }

    $result = json_decode($response['response'], true);
    echo "Upload result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    return [$result, $httpCode];
}

/****************************************************
 * (Alternative) 1) Upload image from remote URL
 ****************************************************/
function uploadWebImage(string $apiKey, string $clientId, string $apiUrl, string $uploadPath, string $imageId, string $invoiceUrl): array
{
    $url     = $apiUrl . $uploadPath;
    $token   = "Basic " . $apiKey; // "Basic <API_KEY>"
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: ' . $token
    ];

    $payload = json_encode([
        "external_id" => $imageId,
        "client_id"   => $clientId,
        "use_llm"     => true, // optional param
        "url"         => $invoiceUrl
    ]);

    $response = doRequest('POST', $url, $headers, $payload);
    $httpCode = $response['http_code'];

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Error! status: $httpCode - " . $response['response']);
    }

    $result = json_decode($response['response'], true);
    echo "Upload (Web) result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    return [$result, $httpCode];
}

/****************************************************
 * 2) Get Prediction (polling)
 ****************************************************/
function getPrediction(string $apiKey, string $apiUrl, string $businessDocsPath, string $imageId): array
{
    $url   = $apiUrl . $businessDocsPath . '/' . $imageId;
    $token = "Basic " . $apiKey;
    $headers = [
        'Accept: application/json',
        'Authorization: ' . $token
    ];

    $response = doRequest('GET', $url, $headers, null);
    $httpCode = $response['http_code'];

    // Acceptable: 200, 202, 404
    if (!in_array($httpCode, [200, 202, 404])) {
        throw new Exception("Error! status: $httpCode - " . $response['response']);
    }

    $result = json_decode($response['response'], true);
    echo "Prediction result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    return [$result, $httpCode];
}

/****************************************************
 * Main execution flow
 ****************************************************/
try {
    // STEP 1: Upload Image (choose one)
    // --------- Local Image Upload -----------
    list($uploadResult, $uploadStatus) = uploadImage($API_KEY, $CLIENT_ID, $GEMINA_API_URL, $UPLOAD_URL, $IMAGE_ID);

    // --------- Web Image Upload ------------
    // list($uploadResult, $uploadStatus) = uploadWebImage($API_KEY, $CLIENT_ID, $GEMINA_API_URL, $WEB_UPLOAD_URL, $IMAGE_ID, $INVOICE_URI);

    echo "Parsed upload result:\n";
    print_r($uploadResult);
    echo "Status code: " . $uploadStatus . "\n\n";

    switch ($uploadStatus) {
        case 201:
            echo "Uploaded Successfully.\n";
            break;
        case 202:
            echo "Image is already being processed. No need to upload again.\n";
            break;
    }

    // STEP 2: Poll until the status is final (200) or not found (404) or an error
    do {
        list($predictionResult, $predictionStatus) = getPrediction($API_KEY, $GEMINA_API_URL, $BUSINESS_DOCUMENTS_URL, $IMAGE_ID);

        switch ($predictionStatus) {
            case 202:
                echo "Image is still being processed. Sleeping 1 second before the next attempt...\n";
                sleep(1);
                break;
            case 404:
                echo "Can't find image. Let's wait 1 second to create before we try again...\n";
                sleep(1);
                break;
            case 200:
                echo "The Prediction Object Data has been successfully retrieved.\n";
                break;
        }
    } while ($predictionStatus === 202 || $predictionStatus === 404);

    echo "Parsed prediction result:\n";
    print_r($predictionResult);
    echo "Status code: " . $predictionStatus . "\n\n";

} catch (Exception $e) {
    echo "Error message: " . $e->getMessage() . "\n";
    exit(1);
}

