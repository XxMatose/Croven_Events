<?php
/**
 * test_upload.php
 * Drop this file in the same folder as upload_helper.php
 * Visit it in your browser to test your B2 connection
 */

require 'upload_helper.php';

$result  = null;
$error   = null;
$tests   = [];

// ── Run connection test (no file needed) ──────────────────────────────────────
function testB2Connection(): array
{
    $endpoint = B2_ENDPOINT;
    $bucket   = B2_BUCKET;
    $region   = B2_REGION;
    $keyId    = B2_KEY_ID;
    $appKey   = B2_APP_KEY;
    $host     = parse_url($endpoint, PHP_URL_HOST);

    $dateTime    = gmdate('Ymd\THis\Z');
    $date        = gmdate('Ymd');
    $payloadHash = hash('sha256', '');

    $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$dateTime\n";
    $signedHeaders    = 'host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "HEAD\n/$bucket/\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
    $credentialScope  = "$date/$region/s3/aws4_request";
    $stringToSign     = "AWS4-HMAC-SHA256\n$dateTime\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $signingKey = hash_hmac('sha256', 'aws4_request',
                    hash_hmac('sha256', 's3',
                      hash_hmac('sha256', $region,
                        hash_hmac('sha256', $date, 'AWS4' . $appKey, true),
                      true),
                    true),
                  true);

    $signature  = hash_hmac('sha256', $stringToSign, $signingKey);
    $authHeader = "AWS4-HMAC-SHA256 Credential=$keyId/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $ch = curl_init("$endpoint/$bucket/");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'HEAD',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: $authHeader",
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $dateTime",
        ],
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'message' => 'curl error: ' . $curlErr];
    }
    if ($httpCode === 200 || $httpCode === 301 || $httpCode === 302) {
        return ['ok' => true, 'message' => "Connected successfully (HTTP $httpCode)"];
    }
    return ['ok' => false, 'message' => "Unexpected response: HTTP $httpCode — check your Key ID, App Key, Bucket, and Endpoint in upload_helper.php"];
}

// ── Check PHP environment ─────────────────────────────────────────────────────
$tests['php_version'] = [
    'label'   => 'PHP Version',
    'ok'      => version_compare(PHP_VERSION, '8.0', '>='),
    'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.0', '>=') ? ' ✓ (8.0+ required)' : ' ✗ — upgrade to PHP 8.0+'),
];

$tests['curl_enabled'] = [
    'label'   => 'curl Extension',
    'ok'      => function_exists('curl_init'),
    'message' => function_exists('curl_init') ? 'Enabled ✓' : 'Not available — enable curl in php.ini',
];

$tests['upload_limit'] = [
    'label'   => 'upload_max_filesize',
    'ok'      => true,
    'message' => ini_get('upload_max_filesize') . ' (increase to 512M for video in cPanel PHP settings)',
];

$tests['post_limit'] = [
    'label'   => 'post_max_size',
    'ok'      => true,
    'message' => ini_get('post_max_size'),
];

$tests['max_execution'] = [
    'label'   => 'max_execution_time',
    'ok'      => (int)ini_get('max_execution_time') >= 60 || (int)ini_get('max_execution_time') === 0,
    'message' => ini_get('max_execution_time') . 's' . ((int)ini_get('max_execution_time') < 60 && (int)ini_get('max_execution_time') !== 0 ? ' — increase to 300 for large video uploads' : ' ✓'),
];

$tests['config_filled'] = [
    'label'   => 'Config Values',
    'ok'      => B2_KEY_ID !== 'your_keyID_here' && B2_APP_KEY !== 'your_applicationKey_here',
    'message' => (B2_KEY_ID === 'your_keyID_here' || B2_APP_KEY === 'your_applicationKey_here')
                    ? 'Placeholder values detected — fill in upload_helper.php config block'
                    : 'Key ID and App Key are set ✓',
];

// ── B2 connection test ────────────────────────────────────────────────────────
if (B2_KEY_ID !== 'your_keyID_here') {
    $tests['b2_connection'] = array_merge(['label' => 'B2 Connection'], testB2Connection());
} else {
    $tests['b2_connection'] = ['label' => 'B2 Connection', 'ok' => false, 'message' => 'Skipped — fill in config values first'];
}

// ── Handle file upload test ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error code: ' . $file['error'];
    } else {
        try {
            $url    = uploadToB2($file['tmp_name'], $file['name'], $file['type'], 0);
            $result = $url;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$allSystemOk = $tests['php_version']['ok'] && $tests['curl_enabled']['ok'] && $tests['config_filled']['ok'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>B2 Upload Test — <?= htmlspecialchars(B2_BUCKET) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:       #0a0a0a;
    --surface:  #111111;
    --border:   #222222;
    --accent:   #e8ff47;
    --red:      #ff4747;
    --green:    #47ff8f;
    --muted:    #555555;
    --text:     #e0e0e0;
    --mono:     'DM Mono', monospace;
    --display:  'Bebas Neue', sans-serif;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--mono);
    min-height: 100vh;
    padding: 48px 24px;
    background-image:
      repeating-linear-gradient(0deg, transparent, transparent 39px, #ffffff04 39px, #ffffff04 40px),
      repeating-linear-gradient(90deg, transparent, transparent 39px, #ffffff04 39px, #ffffff04 40px);
  }

  .container {
    max-width: 760px;
    margin: 0 auto;
    animation: fadeUp .5s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* ── Header ── */
  .header {
    border-left: 4px solid var(--accent);
    padding-left: 20px;
    margin-bottom: 48px;
  }
  .header h1 {
    font-family: var(--display);
    font-size: clamp(2.4rem, 6vw, 4rem);
    letter-spacing: .04em;
    color: #fff;
    line-height: 1;
  }
  .header h1 span { color: var(--accent); }
  .header p {
    margin-top: 8px;
    font-size: .78rem;
    color: var(--muted);
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  /* ── Section title ── */
  .section-title {
    font-size: .68rem;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 12px;
  }

  /* ── Test grid ── */
  .tests {
    display: grid;
    gap: 2px;
    margin-bottom: 40px;
  }
  .test-row {
    display: grid;
    grid-template-columns: 180px 1fr auto;
    align-items: center;
    gap: 16px;
    background: var(--surface);
    padding: 14px 18px;
    border: 1px solid var(--border);
    transition: border-color .2s;
  }
  .test-row:hover { border-color: #333; }
  .test-label {
    font-size: .75rem;
    color: var(--muted);
    letter-spacing: .06em;
    text-transform: uppercase;
  }
  .test-message {
    font-size: .82rem;
    color: var(--text);
  }
  .badge {
    font-size: .65rem;
    font-weight: 500;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 2px;
    white-space: nowrap;
  }
  .badge-ok   { background: #47ff8f18; color: var(--green); border: 1px solid #47ff8f40; }
  .badge-fail { background: #ff474718; color: var(--red);   border: 1px solid #ff474740; }
  .badge-info { background: #e8ff4718; color: var(--accent);border: 1px solid #e8ff4740; }

  /* ── Upload area ── */
  .upload-card {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 32px;
    margin-bottom: 40px;
  }

  .drop-zone {
    border: 2px dashed var(--border);
    padding: 40px 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    margin-bottom: 20px;
  }
  .drop-zone:hover,
  .drop-zone.dragover {
    border-color: var(--accent);
    background: #e8ff4706;
  }
  .drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }
  .drop-zone-icon {
    font-size: 2rem;
    margin-bottom: 12px;
    display: block;
  }
  .drop-zone-label {
    font-size: .85rem;
    color: var(--muted);
  }
  .drop-zone-label strong {
    color: var(--accent);
    font-weight: 400;
  }
  #file-name {
    margin-top: 10px;
    font-size: .78rem;
    color: var(--accent);
    min-height: 1em;
  }

  .btn {
    display: inline-block;
    background: var(--accent);
    color: #0a0a0a;
    font-family: var(--display);
    font-size: 1.1rem;
    letter-spacing: .1em;
    padding: 14px 36px;
    border: none;
    cursor: pointer;
    width: 100%;
    transition: opacity .15s, transform .1s;
  }
  .btn:hover   { opacity: .88; }
  .btn:active  { transform: scale(.99); }
  .btn:disabled { opacity: .4; cursor: not-allowed; }

  /* ── Result / Error ── */
  .result-box {
    padding: 20px 24px;
    border-left: 4px solid var(--green);
    background: #47ff8f08;
    margin-bottom: 32px;
    animation: fadeUp .3s ease both;
  }
  .result-box .result-title {
    font-size: .68rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--green);
    margin-bottom: 10px;
  }
  .result-box a {
    color: var(--accent);
    font-size: .82rem;
    word-break: break-all;
    text-decoration: none;
    border-bottom: 1px solid #e8ff4740;
  }
  .result-box a:hover { border-bottom-color: var(--accent); }

  .preview-wrap {
    margin-top: 16px;
    background: #000;
    border: 1px solid var(--border);
    max-width: 100%;
    overflow: hidden;
  }
  .preview-wrap img,
  .preview-wrap video {
    max-width: 100%;
    display: block;
    max-height: 320px;
    object-fit: contain;
    margin: 0 auto;
  }

  .error-box {
    padding: 20px 24px;
    border-left: 4px solid var(--red);
    background: #ff474708;
    margin-bottom: 32px;
    animation: fadeUp .3s ease both;
  }
  .error-box .error-title {
    font-size: .68rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--red);
    margin-bottom: 8px;
  }
  .error-box p {
    font-size: .82rem;
    color: var(--text);
  }

  /* ── Footer ── */
  footer {
    font-size: .7rem;
    color: var(--muted);
    text-align: center;
    letter-spacing: .08em;
    padding-top: 24px;
    border-top: 1px solid var(--border);
  }

  /* ── Progress bar ── */
  #progress-wrap {
    display: none;
    margin-top: 16px;
  }
  #progress-wrap label {
    font-size: .72rem;
    color: var(--muted);
    letter-spacing: .08em;
    text-transform: uppercase;
    display: block;
    margin-bottom: 6px;
  }
  .progress-bar-bg {
    background: var(--border);
    height: 4px;
    width: 100%;
  }
  .progress-bar-fill {
    background: var(--accent);
    height: 4px;
    width: 0%;
    transition: width .3s ease;
  }
  #progress-pct {
    font-size: .72rem;
    color: var(--accent);
    margin-top: 4px;
  }

  @media (max-width: 540px) {
    .test-row { grid-template-columns: 1fr; gap: 6px; }
    .badge    { justify-self: start; }
  }
</style>
</head>
<body>
<div class="container">

  <!-- Header -->
  <div class="header">
    <h1>B2 Upload <span>Test</span></h1>
    <p>Bucket: <?= htmlspecialchars(B2_BUCKET) ?> &nbsp;·&nbsp; <?= htmlspecialchars(B2_ENDPOINT) ?></p>
  </div>

  <!-- System checks -->
  <p class="section-title">System &amp; Config Checks</p>
  <div class="tests">
    <?php foreach ($tests as $test): ?>
    <div class="test-row">
      <span class="test-label"><?= htmlspecialchars($test['label']) ?></span>
      <span class="test-message"><?= htmlspecialchars($test['message']) ?></span>
      <?php if ($test['label'] === 'upload_max_filesize' || $test['label'] === 'post_max_size' || $test['label'] === 'max_execution_time'): ?>
        <span class="badge badge-info">INFO</span>
      <?php elseif ($test['ok']): ?>
        <span class="badge badge-ok">PASS</span>
      <?php else: ?>
        <span class="badge badge-fail">FAIL</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Result -->
  <?php if ($result): ?>
  <div class="result-box">
    <p class="result-title">✓ Upload successful</p>
    <a href="<?= htmlspecialchars($result) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($result) ?></a>
    <div class="preview-wrap" id="preview-wrap"></div>
  </div>
  <script>
    (function(){
      const url  = <?= json_encode($result) ?>;
      const wrap = document.getElementById('preview-wrap');
      if (/\.(jpg|jpeg|png|gif|webp)$/i.test(url)) {
        const img = document.createElement('img');
        img.src = url; img.alt = 'Uploaded image preview';
        wrap.appendChild(img);
      } else if (/\.(mp4|mov)$/i.test(url)) {
        const vid = document.createElement('video');
        vid.src = url; vid.controls = true; vid.preload = 'metadata';
        wrap.appendChild(vid);
      }
    })();
  </script>
  <?php endif; ?>

  <!-- Error -->
  <?php if ($error): ?>
  <div class="error-box">
    <p class="error-title">✗ Upload failed</p>
    <p><?= htmlspecialchars($error) ?></p>
  </div>
  <?php endif; ?>

  <!-- Upload form -->
  <p class="section-title">Test File Upload</p>
  <div class="upload-card">
    <form method="POST" enctype="multipart/form-data" id="upload-form">
      <div class="drop-zone" id="drop-zone">
        <input type="file" name="test_file" id="test_file"
               accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime"
               required>
        <span class="drop-zone-icon">📂</span>
        <p class="drop-zone-label">
          Drop a file here or <strong>click to browse</strong><br>
          <small>JPG · PNG · WEBP · GIF · MP4 · MOV &nbsp;|&nbsp; Max 20MB image / 500MB video</small>
        </p>
        <p id="file-name"></p>
      </div>

      <div id="progress-wrap">
        <label>Uploading…</label>
        <div class="progress-bar-bg"><div class="progress-bar-fill" id="progress-fill"></div></div>
        <p id="progress-pct">0%</p>
      </div>

      <button type="submit" class="btn" id="submit-btn"
              <?= !$allSystemOk ? 'disabled title="Fix the failing checks above first"' : '' ?>>
        UPLOAD TO B2
      </button>
    </form>
  </div>

  <footer>
    test_upload.php &nbsp;·&nbsp; Remove this file from your server before going live
  </footer>
</div>

<script>
// Show selected filename
document.getElementById('test_file').addEventListener('change', function () {
  const name = this.files[0] ? this.files[0].name : '';
  document.getElementById('file-name').textContent = name;
});

// Drag-over highlight
const dz = document.getElementById('drop-zone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', ()  => dz.classList.remove('dragover'));
dz.addEventListener('drop',      e => { e.preventDefault(); dz.classList.remove('dragover'); });

// Upload progress via XHR
document.getElementById('upload-form').addEventListener('submit', function (e) {
  const file = document.getElementById('test_file').files[0];
  if (!file) return;

  e.preventDefault();

  const btn  = document.getElementById('submit-btn');
  const wrap = document.getElementById('progress-wrap');
  const fill = document.getElementById('progress-fill');
  const pct  = document.getElementById('progress-pct');

  btn.disabled  = true;
  btn.textContent = 'UPLOADING…';
  wrap.style.display = 'block';

  const fd = new FormData(this);
  const xhr = new XMLHttpRequest();

  xhr.upload.addEventListener('progress', function (ev) {
    if (ev.lengthComputable) {
      const p = Math.round((ev.loaded / ev.total) * 100);
      fill.style.width = p + '%';
      pct.textContent  = p + '%';
    }
  });

  xhr.addEventListener('load', function () {
    // Reload page to show PHP result
    document.open();
    document.write(xhr.responseText);
    document.close();
  });

  xhr.addEventListener('error', function () {
    btn.disabled    = false;
    btn.textContent = 'UPLOAD TO B2';
    wrap.style.display = 'none';
    alert('Network error — check your connection.');
  });

  xhr.open('POST', window.location.href);
  xhr.send(fd);
});
</script>
</body>
</html>
