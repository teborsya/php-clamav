<?php

class ClamAvScanner
{
    protected string $primaryCommand;
    protected string $fallbackCommand;

    public function __construct(
        string $primaryCommand = 'clamdscan',
        string $fallbackCommand = 'clamscan'
    ) {
        $this->primaryCommand = $primaryCommand;
        $this->fallbackCommand = $fallbackCommand;
    }

    public function scan(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'status' => 'error',
                'message' => 'File does not exist.',
                'virus' => null,
                'command' => null,
                'output' => [],
                'exit_code' => null,
            ];
        }

        $result = $this->runScan($this->primaryCommand, $filePath);

        if ($result['status'] === 'error' && $this->fallbackCommand !== '') {
            $fallbackResult = $this->runScan($this->fallbackCommand, $filePath);

            if ($fallbackResult['status'] !== 'error') {
                return $fallbackResult;
            }

            return [
                'status' => 'error',
                'message' => 'Both primary and fallback scan commands failed.',
                'virus' => null,
                'command' => $this->primaryCommand . ' / ' . $this->fallbackCommand,
                'output' => array_merge($result['output'], ['--- fallback ---'], $fallbackResult['output']),
                'exit_code' => $fallbackResult['exit_code'],
            ];
        }

        return $result;
    }

    public function healthCheck(): array
    {
        $checks = [];

        $checks['clamdscan'] = $this->checkCommandVersion('clamdscan --version');
        $checks['clamscan']  = $this->checkCommandVersion('clamscan --version');
        $checks['freshclam'] = $this->checkCommandVersion('freshclam --version');

        $databasePaths = [
            '/var/lib/clamav',
            'C:\\Program Files\\ClamAV\\database',
            'C:\\ClamAV\\database',
        ];

        $databaseInfo = $this->findDatabaseInfo($databasePaths);

        $primaryUsable = $checks['clamdscan']['available'] || $checks['clamscan']['available'];
        $signatureUsable = $databaseInfo['exists'];

        $overallStatus = ($primaryUsable && $signatureUsable) ? 'healthy' : 'unhealthy';

        return [
            'status' => $overallStatus,
            'message' => $overallStatus === 'healthy'
                ? 'ClamAV appears ready for scanning.'
                : 'ClamAV is not fully ready. Please check installation, daemon, and signatures.',
            'checks' => $checks,
            'database' => $databaseInfo,
        ];
    }

    protected function runScan(string $command, string $filePath): array
    {
        $escapedFile = escapeshellarg($filePath);

        if ($command === 'clamdscan') {
            $fullCommand = "clamdscan --no-summary $escapedFile 2>&1";
        } elseif ($command === 'clamscan') {
            $fullCommand = "clamscan --no-summary $escapedFile 2>&1";
        } else {
            $fullCommand = $command . " " . $escapedFile . " 2>&1";
        }

        $output = [];
        $exitCode = null;

        exec($fullCommand, $output, $exitCode);

        $joinedOutput = implode("\n", $output);

        if ($exitCode === 0) {
            return [
                'status' => 'clean',
                'message' => 'File is clean.',
                'virus' => null,
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode,
            ];
        }

        if ($exitCode === 1) {
            $virusName = $this->extractVirusName($joinedOutput);

            return [
                'status' => 'infected',
                'message' => 'Virus detected.',
                'virus' => $virusName,
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode,
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Scanner command failed.',
            'virus' => null,
            'command' => $command,
            'output' => $output,
            'exit_code' => $exitCode,
        ];
    }

    protected function extractVirusName(string $output): ?string
    {
        if (preg_match('/:\s(.+)\sFOUND/', $output, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function checkCommandVersion(string $command): array
    {
        $output = [];
        $exitCode = null;

        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'available' => $exitCode === 0,
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }

    protected function findDatabaseInfo(array $paths): array
    {
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = [
                $path . DIRECTORY_SEPARATOR . 'main.cvd',
                $path . DIRECTORY_SEPARATOR . 'main.cld',
                $path . DIRECTORY_SEPARATOR . 'daily.cvd',
                $path . DIRECTORY_SEPARATOR . 'daily.cld',
                $path . DIRECTORY_SEPARATOR . 'bytecode.cvd',
                $path . DIRECTORY_SEPARATOR . 'bytecode.cld',
            ];

            $foundFiles = [];
            $latestMtime = null;

            foreach ($files as $file) {
                if (file_exists($file)) {
                    $foundFiles[] = $file;
                    $mtime = filemtime($file);

                    if ($mtime !== false && ($latestMtime === null || $mtime > $latestMtime)) {
                        $latestMtime = $mtime;
                    }
                }
            }

            if (!empty($foundFiles)) {
                return [
                    'exists' => true,
                    'path' => $path,
                    'files' => $foundFiles,
                    'last_updated' => $latestMtime ? date('Y-m-d H:i:s', $latestMtime) : null,
                ];
            }
        }

        return [
            'exists' => false,
            'path' => null,
            'files' => [],
            'last_updated' => null,
        ];
    }
}
