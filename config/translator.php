<?php

return [
    'python_binary' => env('PYTHON_BINARY', PHP_OS_FAMILY === 'Windows'
        ? base_path('../.venv/Scripts/python.exe')
        : 'python3'),
    'timeout' => (int) env('TRANSLATOR_TIMEOUT', 900),
    'parallel_workers' => max(1, min(4, (int) env('TRANSLATOR_PARALLEL_WORKERS', 1))),
    'max_upload_kb' => 25 * 1024,
];
