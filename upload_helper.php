<?php
/**
 * Backblaze B2 Upload Helper
 * No Composer or vendor folder required — uses native PHP curl
 * 
 * Usage:
 *   require 'upload_helper.php';
 *   $url = uploadToB2($fileTmpPath, $fileName, $mimeType);
 */

// -------------------------------------------------------
// Configuration — replace these with your actual values
// -------------------------------------------------------
define('B2_KEY_ID',      '0058a6d42679e0c0000000001');
define('B2_APP_KEY',     'K005dAeBp8ZMGLEM9sy4XbGcU/pmGrw');
define('B2_BUCKET',      'events-memories');
define('B2_ENDPOINT',    'https://s3.us-east-005.backblazeb2.com'); // your B2 endpoint
define('B2_REGION',      'us-east-005');                            // your B2 region
define('MEDIA_BASE_URL', 'https://media.crovenlabs.com');           // your Cloudflare subdomain

// -------------------------------------------------------
// Allowed file types and max sizes
// -------------------------------------------------------
const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'video/mp4',
    'video/quicktime',
];
const MAX_IMAGE_SIZE = 20  * 1024 * 1024;   // 20MB
const MAX_VIDEO_SIZE = 500 * 1024 * 1024;   // 500MB


// -------------------------------------------------------
// Main upload function
// -------------------------------------------------------
/**
 * Uploads a file to Backblaze B2 and returns the public Cloudflare URL.
 *
 * @param  string $fileTmpPath  Temp path from $_FILES['media']['tmp_name']
 * @param  string $fileName     Original filename from $_FILES['media']['name']
 * @param  string $mimeType     MIME type from $_FILES['media']['type']
 * @param  int    $concertId    Concert ID for folder organisation
 * @return string               Public Cloudflare URL of the uploaded file
 * @throws Exception            On validation failure or upload error
 */
function uploadToB2(string $fileTmpPath, string $fileName, string $mimeType, int $concertId = 0): string
{
    // --- Validate ---
    validateFile($fileTmpPath, $mimeType);

    // --- Build storage key (path inside bucket) ---
    $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
    $folder       = 'concerts/' . $concertId . '/' . date('Y-m');
    $key          = $folder . '/' . uniqid() . '_' . $safeFileName;

    // --- Read file ---
    $fileContent = file_get_contents($fileTmpPath);
    if ($fileContent === false) {
        throw new Exception('Could not read uploaded file.');
    }

    // --- Build signed request and upload ---
    $url = b2PutObject($key, $fileContent, $mimeType);

    return $url;
}


// -------------------------------------------------------
// Validation
// -------------------------------------------------------
function validateFile(string $fileTmpPath, string $mimeType): void
{
    // Check allowed type
    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        throw new Exception('File type not allowed: ' . $mimeType);
    }

    // Verify it is actually a real uploaded file
    if (!is_uploaded_file($fileTmpPath)) {
        throw new Exception('Invalid file upload.');
    }

    // Check file size based on type
    $fileSize = filesize($fileTmpPath);
    $maxSize  = str_starts_with($mimeType, 'video') ? MAX_VIDEO_SIZE : MAX_IMAGE_SIZE;

    if ($fileSize > $maxSize) {
        $maxMB = $maxSize / 1024 / 1024;
        throw new Exception("File exceeds maximum allowed size of {$maxMB}MB.");
    }
}


// -------------------------------------------------------
// AWS Signature Version 4 signing + curl PUT to B2
// -------------------------------------------------------
function b2PutObject(string $key, string $fileContent, string $mimeType): string
{
    $bucket     = B2_BUCKET;
    $endpoint   = B2_ENDPOINT;
    $region     = B2_REGION;
    $keyId      = B2_KEY_ID;
    $appKey     = B2_APP_KEY;

    $host        = parse_url($endpoint, PHP_URL_HOST);
    $dateTime    = gmdate('Ymd\THis\Z');
    $date        = gmdate('Ymd');
    $payloadHash = hash('sha256', $fileContent);

    // Canonical headers (must be sorted alphabetically)
    $canonicalHeaders = implode("\n", [
        "content-type:$mimeType",
        "host:$host",
        "x-amz-acl:public-read",
        "x-amz-content-sha256:$payloadHash",
        "x-amz-date:$dateTime",
    ]) . "\n";

    $signedHeaders = 'content-type;host;x-amz-acl;x-amz-content-sha256;x-amz-date';

    // Canonical request
    $canonicalRequest = implode("\n", [
        'PUT',
        '/' . $bucket . '/' . $key,
        '', // no query string
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash,
    ]);

    // Credential scope
    $credentialScope = "$date/$region/s3/aws4_request";

    // String to sign
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $dateTime,
        $credentialScope,
        hash('sha256', $canonicalRequest),
    ]);

    // Signing key (derived from app key)
    $signingKey = hash_hmac('sha256', 'aws4_request',
                    hash_hmac('sha256', 's3',
                      hash_hmac('sha256', $region,
                        hash_hmac('sha256', $date, 'AWS4' . $appKey, true),
                      true),
                    true),
                  true);

    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    // Authorization header
    $authHeader = "AWS4-HMAC-SHA256 Credential=$keyId/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    // Send PUT request via curl
    $uploadUrl = "$endpoint/$bucket/$key";

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $fileContent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: $authHeader",
            "Content-Type: $mimeType",
            "x-amz-acl: public-read",
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $dateTime",
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception('curl error during upload: ' . $curlErr);
    }

    if ($httpCode !== 200) {
        throw new Exception("B2 upload failed (HTTP $httpCode): $response");
    }

    // Return public Cloudflare URL
    return MEDIA_BASE_URL . '/' . $key;
}


// -------------------------------------------------------
// Optional: Delete a file from B2 by its key
// -------------------------------------------------------
/**
 * Deletes a file from B2.
 *
 * @param  string $key  The file key (path inside bucket), e.g. concerts/1/2026-05/abc_photo.jpg
 * @return bool
 */
function deleteFromB2(string $key): bool
{
    $bucket   = B2_BUCKET;
    $endpoint = B2_ENDPOINT;
    $region   = B2_REGION;
    $keyId    = B2_KEY_ID;
    $appKey   = B2_APP_KEY;

    $host        = parse_url($endpoint, PHP_URL_HOST);
    $dateTime    = gmdate('Ymd\THis\Z');
    $date        = gmdate('Ymd');
    $payloadHash = hash('sha256', '');

    $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$dateTime\n";
    $signedHeaders    = 'host;x-amz-content-sha256;x-amz-date';

    $canonicalRequest = implode("\n", [
        'DELETE',
        '/' . $bucket . '/' . $key,
        '',
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash,
    ]);

    $credentialScope = "$date/$region/s3/aws4_request";
    $stringToSign    = "AWS4-HMAC-SHA256\n$dateTime\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $signingKey = hash_hmac('sha256', 'aws4_request',
                    hash_hmac('sha256', 's3',
                      hash_hmac('sha256', $region,
                        hash_hmac('sha256', $date, 'AWS4' . $appKey, true),
                      true),
                    true),
                  true);

    $signature  = hash_hmac('sha256', $stringToSign, $signingKey);
    $authHeader = "AWS4-HMAC-SHA256 Credential=$keyId/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $ch = curl_init("$endpoint/$bucket/$key");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: $authHeader",
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $dateTime",
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 204;
}
