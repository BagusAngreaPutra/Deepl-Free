<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class PythonWorker
{
    public function run(string $operation, array $arguments): array
    {
        $python = config('translator.python_binary', 'python');
        $worker = base_path('python/worker.py');
        $command = array_merge([$python, $worker, $operation], $arguments);
        $process = new Process($command, base_path(), null, null, config('translator.timeout', 900));
        $process->run();

        $output = trim($process->getOutput());
        $payload = json_decode($output, true);

        if (!$process->isSuccessful() || !is_array($payload) || !($payload['ok'] ?? false)) {
            $message = $payload['error'] ?? trim($process->getErrorOutput()) ?: 'Worker Python gagal dijalankan.';
            throw new RuntimeException($message);
        }

        return $payload;
    }
}
