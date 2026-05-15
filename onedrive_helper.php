<?php
// ─── onedrive_helper.php ─────────────────────────────────────────────
// Drop this file alongside your other includes.
// Requires: DB_HOST etc. already defined via db_hosted.php

class OneDriveHelper {

    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $accessToken  = '';
    private int    $tokenExpires = 0;

    public function __construct() {
        $this->clientId     = ONEDRIVE_CLIENT_ID;
        $this->clientSecret = ONEDRIVE_CLIENT_SECRET;
        $this->refreshToken = ONEDRIVE_REFRESH_TOKEN;
    }

    // ── Token management ──────────────────────────────────────────────

    private function getAccessToken(): string {
        if ($this->accessToken && time() < $this->tokenExpires - 60) {
            return $this->accessToken;
        }

        $response = $this->httpPost(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type'    => 'refresh_token',
                'scope'         => 'Files.ReadWrite offline_access',
            ]
        );

        if (empty($response['access_token'])) {
            throw new RuntimeException('OneDrive token refresh failed: ' . json_encode($response));
        }

        $this->accessToken  = $response['access_token'];
        $this->tokenExpires = time() + ($response['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    // ── Public API ────────────────────────────────────────────────────

    /**
     * List all photos and videos in a folder.
     * Returns array of items with: id, name, type (photo|video), thumbnail, downloadUrl
     */
    public function listMedia(string $folderId): array {
        $token = $this->getAccessToken();
        $url   = "https://graph.microsoft.com/v1.0/me/drive/items/{$folderId}/children"
               . '?$expand=thumbnails'
               . '&$top=200';

        $raw   = $this->httpGet($url, $token);
        $items = $raw['value'] ?? [];
        $media = [];

        foreach ($items as $item) {
            $mime = $item['file']['mimeType'] ?? '';
            if (str_starts_with($mime, 'image/')) {
                $type = 'photo';
            } elseif (str_starts_with($mime, 'video/')) {
                $type = 'video';
            } else {
                continue; // skip non-media files
            }

            $thumb = $item['thumbnails'][0]['large']['url']
                  ?? $item['thumbnails'][0]['medium']['url']
                  ?? null;

            $media[] = [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'type'        => $type,
                'mime'        => $mime,
                'thumbnail'   => $thumb,
                'downloadUrl' => $item['@microsoft.graph.downloadUrl'] ?? null,
            ];
        }

        return $media;
    }

    /**
     * Upload a file into a OneDrive folder.
     * $localPath  = temp path of the uploaded file ($_FILES['file']['tmp_name'])
     * $fileName   = desired file name in OneDrive
     * $folderId   = destination folder ID
     */
    public function uploadFile(string $localPath, string $fileName, string $folderId): array {
        $token    = $this->getAccessToken();
        $fileSize = filesize($localPath);

        if ($fileSize <= 4 * 1024 * 1024) {
            // Simple upload for files ≤ 4 MB
            return $this->simpleUpload($localPath, $fileName, $folderId, $token);
        }

        // Resumable upload session for larger files
        return $this->resumableUpload($localPath, $fileName, $folderId, $fileSize, $token);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function simpleUpload(string $localPath, string $fileName, string $folderId, string $token): array {
        $encodedName = rawurlencode($fileName);
        $url         = "https://graph.microsoft.com/v1.0/me/drive/items/{$folderId}:/{$encodedName}:/content";
        $content     = file_get_contents($localPath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/octet-stream',
            ],
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return json_decode($body, true) ?? [];
    }

    private function resumableUpload(
        string $localPath,
        string $fileName,
        string $folderId,
        int    $fileSize,
        string $token
    ): array {
        // 1. Create upload session
        $encodedName = rawurlencode($fileName);
        $sessionUrl  = "https://graph.microsoft.com/v1.0/me/drive/items/{$folderId}:/{$encodedName}:/createUploadSession";
        $session     = $this->httpPostJson($sessionUrl, ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']], $token);

        if (empty($session['uploadUrl'])) {
            throw new RuntimeException('Could not create upload session');
        }

        $uploadUrl  = $session['uploadUrl'];
        $chunkSize  = 5 * 1024 * 1024; // 5 MB chunks
        $handle     = fopen($localPath, 'rb');
        $offset     = 0;
        $result     = [];

        while ($offset < $fileSize) {
            $chunk     = fread($handle, $chunkSize);
            $chunkLen  = strlen($chunk);
            $rangeEnd  = $offset + $chunkLen - 1;

            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => $chunk,
                CURLOPT_HTTPHEADER     => [
                    "Content-Length: {$chunkLen}",
                    "Content-Range: bytes {$offset}-{$rangeEnd}/{$fileSize}",
                ],
            ]);

            $body   = curl_exec($ch);
            $result = json_decode($body, true) ?? [];
            curl_close($ch);

            $offset += $chunkLen;
        }

        fclose($handle);
        return $result;
    }

    private function httpGet(string $url, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?? [];
    }

    private function httpPost(string $url, array $fields): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?? [];
    }

    private function httpPostJson(string $url, array $data, string $token): array {
        $json = json_encode($data);
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?? [];
    }
}