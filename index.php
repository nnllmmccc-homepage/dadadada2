<?php
// index.php
// Single-file ImageShare (upload + OGP view) — put on your domain root.
// Requirements: PHP 7+, write permission to ./uploads/ (this script will create it if missing).
// Save as index.php

// --- Configuration ---
$uploadDir = __DIR__ . '/uploads';       // relative uploads folder
$maxSize   = 5 * 1024 * 1024;            // 5 MB
$allowedMime = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];
// ----------------------

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Helper: absolute URL base (scheme + host + path to script directory)
function base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME']; // e.g. /index.php
    $dir = rtrim(dirname($script), '/\\');
    return $scheme . '://' . $host . $dir . '/';
}

// Handle file upload (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    // Basic error handling
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
        exit;
    }

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File too large. Max 5 MB.']);
        exit;
    }

    // Detect MIME reliably
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, $allowedMime)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported file type.']);
        exit;
    }

    $ext = $allowedMime[$mime];
    // Generate ID and filename
    $id = bin2hex(random_bytes(6)); // 12 hex chars
    $filename = $id . '.' . $ext;
    $dest = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
        exit;
    }

    // Optionally set restrictive perms
    @chmod($dest, 0644);

    // Create a short view URL (OGP-enabled)
    $viewUrl = base_url() . '?id=' . $id;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'id' => $id, 'view' => $viewUrl]);
    exit;
}

// If ?id=... is provided, return OGP view page for Discord (server-side meta tags)
if (isset($_GET['id'])) {
    $id = preg_replace('/[^a-f0-9]/', '', $_GET['id']);
    if ($id === '') {
        http_response_code(400);
        echo "Invalid id";
        exit;
    }
    // find file matching id.*
    $matches = glob($uploadDir . '/' . $id . '.*');
    if (!$matches) {
        http_response_code(404);
        echo "Not found";
        exit;
    }
    $filepath = $matches[0];
    $basename = basename($filepath);
    $imageUrl = base_url() . 'uploads/' . rawurlencode($basename);

    // Serve an HTML page with static meta tags (Discord will read this)
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>ImageShare - 共有画像</title>

      <!-- OGP: Discord/Twitter will read these from server-side HTML -->
      <meta property="og:type" content="article">
      <meta property="og:title" content="共有画像 - ImageShare">
      <meta property="og:description" content="ImageShareで共有された画像">
      <meta property="og:image" content="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>">
      <meta property="og:url" content="<?php echo htmlspecialchars(base_url() . '?id=' . $id, ENT_QUOTES); ?>">
      <meta name="twitter:card" content="summary_large_image">
      <meta name="twitter:image" content="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>">

      <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin:0; background:#f3f4f6; display:flex; align-items:center; justify-content:center; height:100vh; }
        .card { background:#fff; padding:18px; border-radius:12px; box-shadow:0 8px 30px rgba(2,6,23,0.08); text-align:center; max-width:90vw; }
        img { max-width:90vw; max-height:70vh; border-radius:10px; display:block; margin:0 auto 12px; }
        a { color:#2563eb; text-decoration:none; font-weight:600; }
      </style>
    </head>
    <body>
      <div class="card">
        <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>" alt="共有画像">
        <div>
          <p>画像を表示しています。共有リンク： <a href="<?php echo htmlspecialchars(base_url() . '?id=' . $id, ENT_QUOTES); ?>"><?php echo htmlspecialchars(base_url() . '?id=' . $id, ENT_QUOTES); ?></a></p>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Otherwise show main UI (upload form + JS)
// This page handles uploading the image to this same script (index.php POST) and shows the returned view link.
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ImageShare - Upload</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    :root{--bg:#eef2ff;--card:#fff;--accent:#4f46e5}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;margin:0;background:linear-gradient(135deg,var(--bg),#e9d5ff);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
    .wrap{width:100%;max-width:920px;}
    .card{background:var(--card);border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(2,6,23,0.08);}
    h1{margin:0 0 8px;font-size:22px;color:#0f172a;}
    p.lead{margin:0 0 18px;color:#475569;}
    .drop{border:2px dashed #c7d2fe;border-radius:12px;padding:34px;text-align:center;cursor:pointer;}
    .drop input{display:none;}
    .btn{display:inline-block;background:var(--accent);color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;border:none;cursor:pointer;}
    .result{margin-top:18px;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #e6eefb;word-break:break-all;}
    img.preview{max-height:240px;display:block;margin:12px auto;border-radius:10px;box-shadow:0 8px 20px rgba(2,6,23,0.06);}
    .small{font-size:13px;color:#475569}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>ImageShare</h1>
      <p class="lead">画像をアップロードして、Discordに貼れる共有リンクを作成します。画像はサーバーの /uploads に保存され、実URLは公開されますが、共有は短い view URL (？id=) を使用します。</p>

      <label class="drop" id="dropLabel">
        <input type="file" id="fileInput" accept="image/*">
        <div>
          <strong>ここをクリックして画像を選択</strong>
          <p class="small">PNG / JPG / GIF / WEBP（最大 5MB）</p>
        </div>
      </label>

      <div id="previewArea" class="result" style="display:none;">
        <img id="previewImg" class="preview" src="#" alt="preview">
        <div id="status" class="small">アップロード待ち</div>
        <div id="linkBox" style="display:none;margin-top:10px;">
          <div class="small">共有リンク（Discordに貼ってください）</div>
          <div id="shareLink" style="margin-top:6px;color:#2563eb;font-weight:600"></div>
          <button id="copyBtn" class="btn" style="margin-top:10px;">コピー</button>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const input = document.getElementById('fileInput');
  const drop = document.getElementById('dropLabel');
  const previewArea = document.getElementById('previewArea');
  const previewImg = document.getElementById('previewImg');
  const status = document.getElementById('status');
  const linkBox = document.getElementById('linkBox');
  const shareLink = document.getElementById('shareLink');
  const copyBtn = document.getElementById('copyBtn');

  drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor = '#a78bfa'; });
  drop.addEventListener('dragleave', e => { e.preventDefault(); drop.style.borderColor = '#c7d2fe'; });
  drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.style.borderColor = '#c7d2fe';
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) handleFile(f);
  });

  input.addEventListener('change', e => {
    const f = e.target.files[0];
    if (f) handleFile(f);
  });

  function handleFile(file) {
    if (!file.type.startsWith('image/')) { alert('画像ファイルを選択してください'); return; }
    if (file.size > <?php echo $maxSize; ?>) { alert('5MB以下のファイルを選択してください'); return; }

    // preview
    const url = URL.createObjectURL(file);
    previewImg.src = url;
    previewArea.style.display = 'block';
    status.textContent = 'アップロード中...';
    linkBox.style.display = 'none';

    // upload via fetch to this same script
    const fd = new FormData();
    fd.append('image', file);

    fetch(location.pathname, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) {
          const view = json.view;
          status.textContent = 'アップロード完了';
          shareLink.textContent = view;
          shareLink.href = view;
          linkBox.style.display = 'block';
          // revoke preview object URL
          setTimeout(() => URL.revokeObjectURL(url), 1000);
        } else {
          status.textContent = 'アップロード失敗';
          alert((json && json.error) ? json.error : 'Upload failed');
        }
      })
      .catch(err => { status.textContent = 'アップロード失敗'; alert('Upload error: ' + err); });
  }

  copyBtn.addEventListener('click', () => {
    const txt = shareLink.textContent;
    if (!txt) return;
    navigator.clipboard.writeText(txt).then(() => { alert('共有リンクをコピーしました'); });
  });
})();
</script>
</body>
</html>
