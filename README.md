## 1. Overview

ClamAV is an open-source antivirus toolkit commonly used on Linux servers to detect malicious files. It is a practical choice for web applications that accept uploads such as documents, images, archives, and other attachments.

A secure upload flow usually looks like this:

1. The user uploads a file
2. PHP receives the file temporarily
3. The application validates the file type and size
4. The file is scanned with ClamAV
5. If the file is clean, it is stored
6. If the file is infected, it is rejected or quarantined

This approach helps protect your application, server, and users from unsafe uploads.

---

## 2. Recommended Architecture

For web-based systems, the recommended upload scanning flow is:

- Validate file type and size first
- Scan the temporary uploaded file before permanent storage
- Store only clean files
- Reject or quarantine suspicious files
- Log all scan failures and detections

For production systems, using **`clamd`** is usually better than repeatedly calling **`clamscan`**, because `clamd` is a daemon designed for faster and more efficient scanning through a Unix socket or TCP socket.

---

## 3. ClamAV Components

### `freshclam`
Updates ClamAV’s official virus signature databases.

### `clamd`
A background daemon that loads signatures into memory and listens for scan requests over a Unix socket or TCP socket.

### `clamscan`
A standalone command-line scanner. It does not require `clamd`, but it is usually less efficient for web uploads because it loads the engine repeatedly.

### `clamdscan`
A lightweight client that sends scan requests to the running `clamd` daemon.

---

## 4. Server Preparation

### Ubuntu / Debian Example

```bash
sudo apt update
sudo apt install clamav clamav-daemon -y

sudo systemctl stop clamav-freshclam
sudo freshclam
sudo systemctl start clamav-freshclam

sudo systemctl enable clamav-freshclam
sudo systemctl enable clamav-daemon
sudo systemctl start clamav-daemon
```

After installation, make sure the signatures are updated and that `clamd` is running properly.

ClamAV requires a valid configuration and updated signature database before tools such as `freshclam`, `clamscan`, or `clamdscan` can work correctly.

---

## 5. Check Installed Versions

```bash
clamscan --version
freshclam --version
```

---

## 6. Check Daemon / Socket Configuration

`clamd` listens on the socket configured in `clamd.conf`, either through a local Unix socket or a TCP socket.

### Find common socket paths

```bash
sudo find / -name "clamd.ctl" 2>/dev/null
```

### Check socket settings in `clamd.conf`

```bash
sudo grep -E "^(LocalSocket|TCPSocket|TCPAddr)" /etc/clamav/clamd.conf
```

---

## 7. Native PHP Integration

There are two common approaches for integrating ClamAV in native PHP:

### Option A: Call `clamdscan` from PHP
This is the simplest and most common approach when PHP and ClamAV are on the same server.

### Option B: Connect directly to the `clamd` socket
This is a more advanced approach that can be cleaner for larger or more customized systems.

For many PHP projects, **Option A** is the easiest to implement and maintain.

---

## 8. Native PHP Example Using `clamdscan`

### Example scanner class

```text
php-native/ClamAvScanner.php
```

### Example upload handler

```text
php-native/upload.php
```

A typical flow is:

- Receive uploaded file
- Validate type and size
- Run `clamdscan` on the temporary file
- Accept only clean files
- Reject or quarantine infected files

---

## 9. Laravel Integration

Laravel provides convenient tools for file validation and storage, making it a good fit for ClamAV integration.

The recommended Laravel flow is:

- Validate the uploaded file using Laravel validation rules
- Scan the temporary uploaded file
- Store the file only if it passes the scan
- Reject or quarantine infected files

Laravel supports uploaded file handling through methods such as `store()` and `putFile()`.

### `.env`

```env
CLAMAV_ENABLED=true
CLAMAV_BINARY=/usr/bin/clamdscan
CLAMAV_ARGS="--no-summary"
CLAMAV_FAIL_CLOSED=true
```

### Config file

```text
config/clamav.php
```

Example path:

```text
laravel-example/config/clamav.php
```

### Service class

```text
app/Services/ClamAvService.php
```

Example path:

```text
laravel-example/app/Services/ClamAvService.php
```

### Controller example

```text
laravel-example/app/Http/Controllers/UploadController.php
```

### Route example

```php
use App\Http\Controllers\UploadController;

Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
```

---

## 10. Quarantine Option

Instead of immediately deleting infected files, you may move them into a protected quarantine directory outside the public web root.

### Example

```php
$quarantinePath = storage_path('app/quarantine');

if (!is_dir($quarantinePath)) {
    mkdir($quarantinePath, 0755, true);
}

$file->move(
    $quarantinePath,
    uniqid('infected_', true) . '_' . $file->getClientOriginalName()
);
```

### Best Practices for Quarantine

- Keep quarantined files outside the public directory
- Do not allow direct public access
- Log the detection event
- Restrict access to administrators only
- Consider scheduled cleanup of old quarantined files

---

## 11. Recommended Security Rules

For safer file upload handling, follow these practices:

- Validate file type and file size before scanning
- Scan the temporary uploaded file before permanent storage
- Store accepted files outside the public directory whenever possible
- Keep virus signatures updated regularly with `freshclam`
- Prefer `clamd` for production upload scanning
- Log scanner failures and malware detections
- Decide whether the application should be fail-closed or fail-open

### Fail-closed

If the scanner is unavailable, block uploads.

### Fail-open

If the scanner is unavailable, allow uploads but log the risk.

For most systems handling sensitive uploads, **fail-closed** is the safer choice.

---

## 12. Testing the Integration

You can safely test ClamAV using the standard **EICAR** antivirus test file. This is not real malware, but antivirus tools should detect it as a test threat.

### Example EICAR string

Create a file named `eicar.com.txt` containing this exact single line:

```txt
X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*
```

### Scan it manually

```bash
clamscan eicar.com.txt
```

### Expected Result

ClamAV should detect and flag the file.

> Note: Use only the standard EICAR test string for safe testing. Do not use real malware for upload tests.
