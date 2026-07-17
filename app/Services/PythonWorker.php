<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PythonWorker
{
    public function run(string $operation, array $arguments, ?string $requestId = null): array
    {
        $python = config('translator.python_binary', 'python');
        $worker = base_path('python/worker.py');
        $command = array_merge([$python, $worker, $operation], $arguments);
        $timeout = config('translator.timeout', 900);
        $startedAt = microtime(true);

        Log::info('python_worker.started', [
            'request_id' => $requestId,
            'operation' => $operation,
            'python_binary' => $python,
            'python_exists' => is_file($python),
            'timeout_seconds' => $timeout,
        ]);

        try {
            $process = new Process($command, base_path(), null, null, $timeout);
            $process->run(function (string $type, string $buffer) use ($requestId, $operation): void {
                if ($type !== Process::ERR) return;
                foreach (preg_split('/\R/', trim($buffer)) as $line) {
                    if (!str_starts_with($line, 'JDS_PROGRESS ')) continue;
                    $progress = json_decode(substr($line, strlen('JDS_PROGRESS ')), true);
                    Log::info('python_worker.progress', [
                        'request_id' => $requestId,
                        'operation' => $operation,
                        ...(is_array($progress) ? $progress : ['message' => $line]),
                    ]);
                }
            });
        } catch (Throwable $e) {
            Log::error('python_worker.could_not_start', [
                'request_id' => $requestId,
                'operation' => $operation,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'exception' => $e,
            ]);
            throw $e;
        }

        $output = trim($process->getOutput());
        $payload = json_decode($output, true);

        if (!$process->isSuccessful() || !is_array($payload) || !($payload['ok'] ?? false)) {
            $message = $payload['error'] ?? trim($process->getErrorOutput()) ?: 'Worker Python gagal dijalankan.';
            Log::error('python_worker.failed', [
                'request_id' => $requestId,
                'operation' => $operation,
                'exit_code' => $process->getExitCode(),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'stderr' => mb_substr(trim($process->getErrorOutput()), 0, 4000),
                'worker_error' => $message,
                'output_is_json' => is_array($payload),
            ]);
            throw new RuntimeException($message);
        }

        Log::info('python_worker.completed', [
            'request_id' => $requestId,
            'operation' => $operation,
            'exit_code' => $process->getExitCode(),
            'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
        ]);

        return $payload;
    }
}
