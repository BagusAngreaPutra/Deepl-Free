<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PythonWorker
{
    public function run(
        string $operation,
        array $arguments,
        ?string $requestId = null,
        ?callable $onProgress = null,
    ): array {
        $python = config('translator.python_binary', 'python');
        $worker = base_path('python/worker.py');
        $command = array_merge([$python, $worker, $operation], $arguments);
        $timeout = max(1, (int) config('translator.timeout', 900));
        $startedAt = microtime(true);

        // The Symfony process has its own timeout, but PHP's web SAPI may still
        // terminate this request first (commonly after 60 seconds). Large DOCX
        // files legitimately need longer, so keep PHP alive for at least as
        // long as the worker plus a small amount of response/cleanup time.
        $this->extendPhpExecutionTime($timeout);

        Log::info('python_worker.started', [
            'request_id' => $requestId,
            'operation' => $operation,
            'python_binary' => $python,
            'python_exists' => is_file($python),
            'timeout_seconds' => $timeout,
        ]);

        try {
            $progressBuffer = '';
            $process = new Process($command, base_path(), [
                'TRANSLATOR_PARALLEL_WORKERS' => (string) config('translator.parallel_workers', 1),
            ], null, $timeout);
            $this->stopWorkerOnPhpShutdown($process, $requestId, $operation);
            $process->run(function (string $type, string $buffer) use (
                $requestId, $operation, $onProgress, &$progressBuffer
            ): void {
                if ($type !== Process::ERR) {
                    return;
                }
                $progressBuffer .= $buffer;
                $lines = preg_split('/\R/', $progressBuffer);
                $progressBuffer = array_pop($lines) ?? '';
                foreach ($lines as $line) {
                    if (! str_starts_with($line, 'JDS_PROGRESS ')) {
                        continue;
                    }
                    $progress = json_decode(substr($line, strlen('JDS_PROGRESS ')), true);
                    Log::info('python_worker.progress', [
                        'request_id' => $requestId,
                        'operation' => $operation,
                        ...(is_array($progress) ? $progress : ['message' => $line]),
                    ]);
                    if (is_array($progress) && $onProgress !== null) {
                        $onProgress($progress);
                    }
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

        if (! $process->isSuccessful() || ! is_array($payload) || ! ($payload['ok'] ?? false)) {
            $message = $payload['error'] ?? trim($process->getErrorOutput()) ?: 'Worker Python gagal dijalankan.';
            Log::error('python_worker.failed', [
                'request_id' => $requestId,
                'operation' => $operation,
                'exit_code' => $process->getExitCode(),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'stderr' => mb_substr(trim($process->getErrorOutput()), 0, 4000),
                'worker_error' => $message,
                'worker_causes' => $payload['causes'] ?? null,
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

    private function extendPhpExecutionTime(int $workerTimeout): void
    {
        if (! function_exists('set_time_limit')) {
            return;
        }

        // Some shared hosts disable set_time_limit. In that case Symfony's
        // worker timeout remains the best limit available to the application.
        @set_time_limit($workerTimeout + 30);
    }

    private function stopWorkerOnPhpShutdown(Process $process, ?string $requestId, string $operation): void
    {
        register_shutdown_function(static function () use ($process, $requestId, $operation): void {
            if (! $process->isRunning()) {
                return;
            }

            // A fatal PHP/web-server timeout bypasses the surrounding catch.
            // Stop the complete process tree so it cannot retain the document
            // lock and reject every later upload as "still being processed".
            $process->stop(1);
            Log::warning('python_worker.stopped_during_php_shutdown', [
                'request_id' => $requestId,
                'operation' => $operation,
            ]);
        });
    }
}
