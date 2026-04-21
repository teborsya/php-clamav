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
freshclam

Updates ClamAV’s official signature databases.

clamd

Daemon process that loads the signatures and listens for scan commands over a Unix socket or TCP socket.

clamscan

Standalone command-line scanner. It does not require clamd, but for web uploads it is usually less efficient than daemon-based scanning.

clamdscan

Client that sends scan requests to the clamd daemon.

4. Server Preparation
