<?php

require_once __DIR__ . '/ClamAvScanner.php';

$scanner = new ClamAvScanner('clamdscan', 'clamscan');
$health = $scanner->healthCheck();

$message = '';
$messageType = '';
$scanDetails = null;

$uploadDir = __DIR__ . '/uploads';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($health['status'] !== 'healthy') {
        $message = 'Upload blocked because ClamAV is not healthy or not fully configured.';
        $messageType = 'error';
    } elseif (!isset($_FILES['file'])) {
        $message = 'No file uploaded.';
        $messageType = 'error';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload failed with error code: ' . $_FILES['file']['error'];
        $messageType = 'error';
    } else {
        $originalName = $_FILES['file']['name'];
        $tmpPath = $_FILES['file']['tmp_name'];
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($originalName));
        $destination = $uploadDir . '/' . $safeName;

        $scanResult = $scanner->scan($tmpPath);
        $scanDetails = $scanResult;

        if ($scanResult['status'] === 'clean') {
            if (move_uploaded_file($tmpPath, $destination)) {
                $message = 'Upload successful. File is clean and saved as: ' . htmlspecialchars($safeName);
                $messageType = 'success';
            } else {
                $message = 'File is clean but failed to move into uploads folder.';
                $messageType = 'error';
            }
        } elseif ($scanResult['status'] === 'infected') {
            $message = 'Upload blocked. Virus detected: ' . htmlspecialchars($scanResult['virus'] ?? 'Unknown virus');
            $messageType = 'infected';
        } else {
            $message = 'Scanner error: ' . htmlspecialchars($scanResult['message']);
            $messageType = 'error';
        }
    }
}

function yesNo(bool $value): string
{
    return $value ? 'Yes' : 'No';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClamAV Upload Test with Health Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            padding: 40px;
            color: #1e293b;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        h1, h2, h3, h4 {
            margin-top: 0;
        }
        .msg {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #e8f8ee;
            color: #166534;
        }
        .infected {
            background: #fdecec;
            color: #b42318;
        }
        .error {
            background: #fff4e5;
            color: #92400e;
        }
        .healthy {
            background: #ecfdf5;
            color: #065f46;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .unhealthy {
            background: #fef2f2;
            color: #991b1b;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section {
            background: #f8fafc;
            border: 1px solid #dbe3ea;
            padding: 18px;
            margin-top: 18px;
            border-radius: 10px;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #0f172a;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            overflow-x: auto;
        }
        input[type="file"] {
            margin-bottom: 15px;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover {
            background: #1d4ed8;
        }
        code {
            background: #eef2f7;
            padding: 2px 6px;
            border-radius: 4px;
        }
        ul {
            margin-top: 8px;
        }
        .small {
            font-size: 14px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ClamAV File Upload Test</h1>
        <p class="small">This page scans the uploaded file first before saving it.</p>

        <div class="section">
            <h2>Before You Upload</h2>
            <ul>
                <li>Make sure <strong>ClamAV is installed</strong>.</li>
                <li>Make sure <strong>signature databases are downloaded</strong>.</li>
                <li>Make sure <strong>clamd is running</strong> if you want to use <code>clamdscan</code>.</li>
                <li>If <code>clamdscan</code> is unavailable, this sample will try <code>clamscan</code> as fallback.</li>
                <li>On Linux, ClamAV is commonly installed using the package manager and signatures are updated with <code>freshclam</code>.</li>
                <li>If you are on Windows and do not want a native setup, you can use <strong>VMware or VirtualBox</strong>, install Ubuntu Linux inside it, then install and run ClamAV there.</li>
                <li>You may also use the official Windows build of <a href="https://www.clamav.net/downloads#collapseWindowsproduction">ClamAV</a> if you prefer native Windows testing.</li>
            </ul>

            <h3>Example Linux Commands</h3>
            <pre>sudo apt update
sudo apt install clamav clamav-daemon -y
sudo systemctl stop clamav-freshclam
sudo freshclam
sudo systemctl start clamav-freshclam
sudo systemctl enable clamav-daemon
sudo systemctl start clamav-daemon</pre>

            <h3>Manual Checks</h3>
            <pre>clamdscan --version
clamscan --version
freshclam --version</pre>
        </div>

        <div class="<?= $health['status'] === 'healthy' ? 'healthy' : 'unhealthy' ?>">
            <strong>ClamAV Health:</strong> <?= htmlspecialchars(strtoupper($health['status'])) ?><br>
            <?= htmlspecialchars($health['message']) ?>
        </div>

        <div class="section">
            <h2>Health Details</h2>
            <p><strong>clamdscan available:</strong> <?= yesNo($health['checks']['clamdscan']['available']) ?></p>
            <p><strong>clamscan available:</strong> <?= yesNo($health['checks']['clamscan']['available']) ?></p>
            <p><strong>freshclam available:</strong> <?= yesNo($health['checks']['freshclam']['available']) ?></p>
            <p><strong>Signature database found:</strong> <?= yesNo($health['database']['exists']) ?></p>
            <p><strong>Database path:</strong> <?= htmlspecialchars($health['database']['path'] ?? 'Not found') ?></p>
            <p><strong>Last signature update:</strong> <?= htmlspecialchars($health['database']['last_updated'] ?? 'Unknown') ?></p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="msg <?= htmlspecialchars($messageType) ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Upload File</h2>
            <?php if ($health['status'] !== 'healthy'): ?>
                <p><strong>Upload is disabled</strong> until ClamAV becomes healthy.</p>
            <?php else: ?>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="file" name="file" required>
                    <br>
                    <button type="submit">Upload and Scan</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($scanDetails): ?>
            <div class="section">
                <h2>Scan Details</h2>
                <p><strong>Status:</strong> <?= htmlspecialchars($scanDetails['status']) ?></p>
                <p><strong>Message:</strong> <?= htmlspecialchars($scanDetails['message']) ?></p>
                <p><strong>Virus:</strong> <?= htmlspecialchars($scanDetails['virus'] ?? 'None') ?></p>
                <p><strong>Command Used:</strong> <?= htmlspecialchars($scanDetails['command'] ?? 'N/A') ?></p>
                <p><strong>Exit Code:</strong> <?= htmlspecialchars((string)($scanDetails['exit_code'] ?? 'N/A')) ?></p>

                <h3>Raw Output</h3>
                <pre><?= htmlspecialchars(implode("\n", $scanDetails['output'] ?? [])) ?></pre>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Safe Test File</h2>
            <p>Create a harmless antivirus test file named <code>eicar.com</code> with this content:</p>
            <pre>X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*</pre>
            <p>Then upload it. ClamAV should mark it as infected.</p>
        </div>

        <div class="section">
            <h2>Folder Structure</h2>
            <pre>your-project/
├── ClamAvScanner.php
├── upload.php
└── uploads/</pre>
        </div>
    </div>
</body>
</html>
