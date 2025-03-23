# Gemina API – Quick Implementation Guide – PHP

This guide shows how to integrate with the Gemina API using **PHP**. It covers:

1. Environment setup (Ubuntu/WSL, PHP, cURL).
2. Configuration and credentials.
3. Uploading an invoice.
4. Polling the API for invoice predictions.

---

## 1. Environment Setup

Below is a brief outline for installing and running PHP on Ubuntu or WSL.  
*(If you already have PHP and cURL, you can skip to Step 2.)*

1. **Update and Upgrade (Optional)**

    sudo apt update
    sudo apt upgrade

2. **Install PHP CLI and cURL extension**  

    sudo apt install php-cli php-curl

3. **Verify PHP version**  

    php -v

You should see PHP 7.x or 8.x. Make sure cURL is enabled (installed above).

---

## 2. Configuration & Script

Copy (or create) a single file named **`main.php`** in your project directory (for example, `gemina_project`). Make sure your API credentials, client info, and the invoice file path or URL are updated.

Here is a **sample single-file PHP script**:

    <?php
    /****************************************************
     * Gemina API Example in PHP (single file)
     * 
     * This script performs the following logic:
     *   1. Upload an invoice (local or remote).
     *   2. Poll until the prediction is ready or fails.
     ****************************************************/

    /**
     * Generate a UUIDv4 for the external_id
     */
    function generate_uuid(): string {
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
     * @param string $method  The HTTP method (GET, POST, etc.)
     * @param string $url     The endpoint URL
     * @param array  $headers HTTP headers
     * @param mixed  $body    The request body (JSON-encoded)
     *
     * @return array  ['http_code' => int, 'response' => string]
     */
    function doRequest(string $method, string $url, array $headers = [], $body = null): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

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
    $API_KEY  = '== YOUR API KEY ==';    // e.g. "abcdef123456"
    $CLIENT_ID = '== YOUR CLIENT KEY ==';// e.g. "abc123"
    $INVOICE_URL = '== YOUR INVOICE URL =='; // e.g. "https://example.com/invoice.png"

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
        $url     = $apiUrl . $uploadPath;
        $token   = "Basic " . $apiKey;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token
        ];

        // Read and encode local file (e.g., invoice.png)
        $imageBase64 = base64_encode(file_get_contents('./invoice.png'));

        $payload = json_encode([
            'external_id' => $imageId,
            'client_id'   => $clientId,
            'use_llm'     => true, // optional
            'file'        => $imageBase64
        ]);

        $res = doRequest('POST', $url, $headers, $payload);
        $httpCode = $res['http_code'];

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Error! status: $httpCode - " . $res['response']);
        }

        $result = json_decode($res['response'], true);
        echo "Upload result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        return [$result, $httpCode];
    }

    /****************************************************
     * (Alternative) 1) Upload image from remote URL
     ****************************************************/
    function uploadWebImage(string $apiKey, string $clientId, string $apiUrl, string $uploadPath, string $imageId, string $invoiceUrl): array
    {
        $url     = $apiUrl . $uploadPath;
        $token   = "Basic " . $apiKey;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token
        ];

        $payload = json_encode([
            'external_id' => $imageId,
            'client_id'   => $clientId,
            'use_llm'     => true,
            'url'         => $invoiceUrl
        ]);

        $res = doRequest('POST', $url, $headers, $payload);
        $httpCode = $res['http_code'];

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Error! status: $httpCode - " . $res['response']);
        }

        $result = json_decode($res['response'], true);
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

        $res      = doRequest('GET', $url, $headers, null);
        $httpCode = $res['http_code'];

        if (!in_array($httpCode, [200, 202, 404])) {
            throw new Exception("Error! status: $httpCode - " . $res['response']);
        }

        $result = json_decode($res['response'], true);
        echo "Prediction result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        return [$result, $httpCode];
    }

    /****************************************************
     * Main execution flow
     ****************************************************/
    try {
        // STEP 1: Upload Image (choose one)
        list($uploadResult, $uploadStatus) = uploadImage($API_KEY, $CLIENT_ID, $GEMINA_API_URL, $UPLOAD_URL, $IMAGE_ID);

        // Or for remote URL:
        // list($uploadResult, $uploadStatus) = uploadWebImage($API_KEY, $CLIENT_ID, $GEMINA_API_URL, $WEB_UPLOAD_URL, $IMAGE_ID, $INVOICE_URL);

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
                    echo "Can't find image. Let's wait 1 second and try again...\n";
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

---

## 3. Running the Script

1. **Place `main.php`** and any local file (e.g. `invoice.png`) in the same directory.  
2. **Update** `$API_KEY`, `$CLIENT_ID`, and `$INVOICE_URL` with your credentials and invoice link.  
3. **Run**:

    php main.php

Observe the output in your terminal. You should see messages like:

- **Upload result** with status code `201` or `202`.
- **Prediction result** repeated until the status is `200`.
- Final invoice fields (`total_amount`, `document_number`, etc.) printed out once done.

---

## 4. Example Output

Below is a **sample** final output (your numbers may differ):

    Upload result:
    {
        "external_id": "d218fd39-bfd8-4d7f-a8ad-12771d872ae0",
        "use_llm": true,
        "created": "2025-03-23T17:47:22.081649",
        "timestamp": 1742752042.081649
    }
    Parsed upload result:
    Array
    (
        [external_id] => d218fd39-bfd8-4d7f-a8ad-12771d872ae0
        [use_llm] => 1
        [created] => 2025-03-23T17:47:22.081649
        [timestamp] => 1742752042.0816
    )
    Status code: 201
    Uploaded Successfully.

    Prediction result:
    {
        "external_id": "d218fd39-bfd8-4d7f-a8ad-12771d872ae0",
        "created": "2025-03-23T17:47:22.345005",
        "timestamp": 1742752042.345005
    }
    Image is still being processed. Sleeping 1 second before the next attempt...

    ...
    (More polling steps here)
    ...

    Prediction result:
    {
        "external_id": "d218fd39-bfd8-4d7f-a8ad-12771d872ae0",
        "total_amount": { "value": 1572, "confidence": "high", "coordinates": ... },
        "net_amount": { "value": 1343.59, "confidence": "high", "coordinates": ... },
        "vat_amount": { "value": 228.41, "confidence": "high", "coordinates": ... },
        "issue_date": { "value": "31/08/2020", "confidence": "high", "coordinates": ... },
        "document_number": { "value": 7890, "confidence": "high", "coordinates": ... },
        "document_type": { "value": "invoice", "confidence": "high", ... },
        "primary_document_type": { "value": "invoice", "confidence": "high", ... },
        "business_number": { "value": 514713288, "confidence": "high", ... },
        "supplier_name": { "value": "חמשת הפסים קלין בע\"מ", "confidence": "high", ... },
        "currency": { "value": "ils", "confidence": "low" },
        "expense_type": { "value": "other", "confidence": "medium" },
        "payment_method": { "value": "cheque", "confidence": "medium" },
        "timestamp": 1742752042.68111
    }

    The Prediction Object Data has been successfully retrieved.
    Parsed prediction result:
    Array
    (
        [external_id] => d218fd39-bfd8-4d7f-a8ad-12771d872ae0
        ...
        [supplier_name] => Array
            (
                [value] => חמשת הפסים קלין בע"מ
                [confidence] => high
                [coordinates] => 
            )
        ...
        [payment_method] => Array
            (
                [value] => cheque
                [confidence] => medium
            )
        ...
    )
    Status code: 200

---

## 5. More Resources

- **Response Types**  
  https://github.com/tommyil/gemina-examples/blob/master/response_types.md

- **Data Loop**  
  https://github.com/tommyil/gemina-examples/blob/master/data_loop.md

- **LLM Integration**  
  https://github.com/tommyil/gemina-examples/blob/master/llm_integration.md

- **Python Implementation**  
  https://github.com/tommyil/gemina-examples

- **C# Implementation**  
  https://github.com/tommyil/gemina-examples-cs

- **Java Implementation**  
  https://github.com/tommyil/gemina-examples-java

The full PHP single-file example is adapted from the above references.  
For more details, refer to the [Gemina API Swagger Documentation](https://api.gemina.co.il/swagger/) or contact the team at [info@gemina.co.il](mailto:info@gemina.co.il).
