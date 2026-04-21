<?php

class ClamAvScanner
{
    private string $binary;
    private array $extraArgs;

    public function __construct(
        string $binary = '/usr/bin/clamdscan',
        array $extraArgs = ['--no-summary']
    ) {
        $this->binary = $binary;
        $this->extraArgs = $extraArgs;
    }

    public function scanFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [
                'ok' => false,
                'infected' => false,
                'message' => 'File does not exist.',
                'raw_output' => '',
                'exit_code' => 1,
            ];
        }

        $command = array_merge([$this->binary], $this->extraArgs, [$filePath]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return [
                'ok' => false,
                'infected' => false,
                'message' => 'Failed to start ClamAV process.',
                'raw_output' => '',
                'exit_code' => 1,
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = trim($stdout . "\n" . $stderr);

        // Common behavior:
        // 0 = clean
        // 1 = infected
        // >1 = error
        if ($exitCode === 0) {
            return [
                'ok' => true,
                'infected' => false,
                'message' => 'File is clean.',
                'raw_output' => $output,
                'exit_code' => $exitCode,
            ];
        }

        if ($exitCode === 1) {
            return [
                'ok' => true,
                'infected' => true,
                'message' => 'Malware detected.',
                'raw_output' => $output,
                'exit_code' => $exitCode,
            ];
        }

        return [
            'ok' => false,
            'infected' => false,
            'message' => 'Scanner error.',
            'raw_output' => $output,
            'exit_code' => $exitCode,
        ];
    }
}
