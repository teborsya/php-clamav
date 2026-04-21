<?php

require_once 'ClamAvScanner.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Upload failed.');
    }

    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        die('Invalid file type.');
    }

    if ($file['size'] > $maxSize) {
        die('File too large.');
    }

    $scanner = new ClamAvScanner('/usr/bin/clamdscan');
    $result = $scanner->scanFile($file['tmp_name']);

    if (!$result['ok']) {
        error_log('ClamAV error: ' . $result['raw_output']);
        die('Scanner unavailable. Please try again later.');
    }

    if ($result['infected']) {
        error_log('Infected upload blocked: ' . $result['raw_output']);
        die('Upload rejected. Malware detected.');
    }

    $targetDir = __DIR__ . '/uploads';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        die('Failed to save file.');
    }

    echo 'Upload successful.';
}
