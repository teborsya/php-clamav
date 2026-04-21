# php-clamav
ClamAV Integration for Native PHP and Laravel

1. Overview
This project explains how to integrate ClamAV into a PHP-based application so uploaded files can be scanned before they are accepted or stored.

ClamAV is an open-source antivirus toolkit. It includes a scanning engine, a command-line scanner, a daemon for faster scanning, and FreshClam for automatic signature updates.

2. Recommended Architecture
For web applications, the recommended flow is:

User uploads a file
PHP temporarily receives the file
Application validates type and size
Application scans the temporary file with ClamAV
If clean, store the file
If infected, reject or quarantine it

Using clamd is usually better than repeatedly calling clamscan, because clamd is a multi-threaded daemon designed for scanning through a local or TCP socket.

3. ClamAV Components
{freshclam}

Updates ClamAV’s official signature databases.

clamd

Daemon process that loads the signatures and listens for scan commands over a Unix socket or TCP socket.

clamscan

Standalone command-line scanner. It does not require clamd, but for web uploads it is usually less efficient than daemon-based scanning.

clamdscan

Client that sends scan requests to the clamd daemon.

4. Server Preparation
Ubuntu / Debian example

sudo apt update
sudo apt install clamav clamav-daemon -y
sudo systemctl stop clamav-freshclam
sudo freshclam
sudo systemctl start clamav-freshclam
sudo systemctl enable clamav-freshclam
sudo systemctl enable clamav-daemon
sudo systemctl start clamav-daemon

After installation, make sure signatures are updated and clamd is running. ClamAV documentation notes that you need a valid configuration and signatures before using freshclam, clamscan, or clamdscan.

Check versions

clamscan --version
freshclam --version

Check daemon/socket configuration
clamd listens on the socket configured in clamd.conf, either via LocalSocket or TCP settings.
common socket paths:
sudo find / -name "clamd.ctl" 2>/dev/null
sudo grep -E "^(LocalSocket|TCPSocket|TCPAddr)" /etc/clamav/clamd.conf

5. Native PHP Integration

There are two common ways:
Option A: Call clamdscan from PHP

This is the simplest approach on the same server.

Option B: Connect directly to the clamd socket
This is more advanced but cleaner for larger systems.

For many PHP projects, Option A is the easiest to maintain.

6. Native PHP Example Using clamdscan
Simple scanner class
   php-native/ClamAvScanner.php

Native PHP upload handler example
php-native/upload.php

7. Laravel Integration

Laravel already provides request validation and convenient uploaded-file storage methods. You can validate first, scan the temp file, then store the file only when it passes the scan. Laravel’s validation and filesystem features are documented in the official docs, and uploaded files can be stored using methods like store() or putFile().

.env
CLAMAV_ENABLED=true
CLAMAV_BINARY=/usr/bin/clamdscan
CLAMAV_ARGS="--no-summary"
CLAMAV_FAIL_CLOSED=true

config/clamav.php
laravel-example/config/clamav.php

Service class
app/Services/ClamAvService.php
laravel-example/app/Services/ClamAvServices.php

Controller example
laravel-example/app/Http/Controllers/UploadController.php

Route

use App\Http\Controllers\UploadController;

Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

8. Quarantine Option

Instead of fully rejecting infected files, you can move them to a protected quarantine folder outside the public web root.

Example:
$quarantinePath = storage_path('app/quarantine');
if (!is_dir($quarantinePath)) {
    mkdir($quarantinePath, 0755, true);
}

$file->move($quarantinePath, uniqid('infected_', true) . '_' . $file->getClientOriginalName());

Best practice is that quarantined files should not be publicly accessible.

9. Recommended Security Rules
Validate file type and size before scanning. Laravel supports upload validation and MIME / extension checks in its validation system.
Scan the uploaded temporary file before permanent storage.
Store accepted files outside the public directory whenever possible.
Keep freshclam updating signatures regularly, because the malware database is what powers detections.
Prefer clamd for production upload scanning.
Log scanner failures and decide whether your app should be fail-closed or fail-open.
Fail-closed: if scanner is down, block uploads
Fail-open: if scanner is down, allow uploads but log the risk

10. Testing the Integration

You can test with the standard EICAR antivirus test file. ClamAV documentation shows it being detected as Win.Test.EICAR_HDB-1, which makes it useful for safe testing without using real malware.

Example EICAR string

Create a file named eicar.com.txt with this exact single line:
X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*

Then scan it:
clamscan eicar.com.txt

Expected result: ClamAV should flag it.

11. Health Check Commands

Useful commands for server monitoring:
systemctl status clamav-daemon
systemctl status clamav-freshclam
freshclam --version
clamscan --version
sudo tail -f /var/log/clamav/freshclam.log
sudo grep -E "^(LocalSocket|TCPSocket|TCPAddr)" /etc/clamav/clamd.conf

ClamAV also provides clamconf to inspect configuration and environment details.

12. Production Notes
Same-server setup

Best for Laravel or native PHP hosted on the same Linux server as ClamAV.

Remote scanner setup

Possible using TCP socket with clamd, but keep it on a private network or firewall-restricted interface. clamd supports both Unix local sockets and TCP sockets.

Docker option

ClamAV also provides official Docker images, including daemon-based use cases.
