<?php

namespace App\Services;

class ClamAvService
{
    public function scan(string $filePath): array
    {
        if (!config('clamav.enabled')) {
            return [
                'ok' => true,
                'infected' => false,
                'message' => 'ClamAV disabled',
                'raw_output' => '',
                'exit_code' => 0,
            ];
        }

        $binary = config('clamav.binary');
        $args = config('clamav.args', ['--no-summary']);

        if (!is_file($filePath)) {
            return [
                'ok' => false,
                'infected' => false,
                'message' => 'File not found.',
                'raw_output' => '',
                'exit_code' => 1,
            ];
        }

        $command = array_merge([$binary], $args, [$filePath]);

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
                'message' => 'Failed to start scanner.',
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

        return match (true) {
            $exitCode === 0 => [
                'ok' => true,
                'infected' => false,
                'message' => 'Clean',
                'raw_output' => $output,
                'exit_code' => 0,
            ],
            $exitCode === 1 => [
                'ok' => true,
                'infected' => true,
                'message' => 'Infected',
                'raw_output' => $output,
                'exit_code' => 1,
            ],
            default => [
                'ok' => false,
                'infected' => false,
                'message' => 'Scanner error',
                'raw_output' => $output,
                'exit_code' => $exitCode,
            ],
        };
    }
}
