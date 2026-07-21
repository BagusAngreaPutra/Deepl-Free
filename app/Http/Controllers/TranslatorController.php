<?php

namespace App\Http\Controllers;

use App\Services\PythonWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TranslatorController extends Controller
{
    private const DEFAULT_PROFILE = 'academic';

    private const SOURCE_LANGUAGES = [
        'auto', 'id', 'en-US', 'en-GB', 'de', 'fr', 'es', 'pt', 'it', 'nl',
        'pl', 'ru', 'ar', 'tr', 'zh-CN', 'ja', 'ko', 'vi', 'th', 'ms',
    ];

    private const TARGET_LANGUAGES = [
        'id', 'en-US', 'en-GB', 'de', 'fr', 'es', 'pt', 'it', 'nl', 'pl',
        'ru', 'ar', 'tr', 'zh-CN', 'ja', 'ko', 'vi', 'th', 'ms',
    ];

    private const PROFILES = [
        'standard' => ['label' => 'Standard British English', 'description' => 'General British spelling and light polishing.'],
        'academic' => ['label' => 'British Academic English', 'description' => 'Academic phrasing and cleaner university-style English.'],
        'edu_academic' => ['label' => 'British Academic English for Course Documents', 'description' => 'Optimised for CLO/Sub-CLO, lesson plans, tasks, and assessment rubrics.'],
    ];

    public function __construct(private readonly PythonWorker $worker) {}

    public function index()
    {
        return view('index');
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function translateText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'source_language' => ['nullable', 'in:'.implode(',', self::SOURCE_LANGUAGES)],
            'target_language' => ['required', 'in:'.implode(',', self::TARGET_LANGUAGES)],
        ], [
            'text.required' => 'Masukkan teks yang ingin diterjemahkan.',
            'text.max' => 'Teks maksimal 5.000 karakter.',
        ]);

        $requestId = (string) Str::uuid();
        Log::info('text_translation.request_received', [
            'request_id' => $requestId,
            'characters' => mb_strlen($validated['text']),
            'source_language' => $validated['source_language'] ?? 'auto',
            'target_language' => $validated['target_language'],
        ]);

        try {
            $payload = $this->worker->run('text', [
                '--text', base64_encode($validated['text']),
                '--source-language', $validated['source_language'] ?? 'auto',
                '--target-language', $validated['target_language'],
                '--profile', self::DEFAULT_PROFILE,
            ], $requestId);

            return response()->json([
                'translation' => $payload['translation'],
                'request_id' => $requestId,
            ]);
        } catch (Throwable $e) {
            Log::error('text_translation.failed', ['request_id' => $requestId, 'exception' => $e]);

            return response()->json([
                'error' => 'Terjemahan teks gagal: '.$e->getMessage(),
                'error_id' => $requestId,
            ], 500);
        }
    }

    public function translate(Request $request): JsonResponse|BinaryFileResponse|StreamedResponse
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        Log::info('translation.request_received', [
            'request_id' => $requestId,
            'content_length' => $request->server('CONTENT_LENGTH'),
            'source_language' => $request->input('source_language', 'auto'),
            'target_language' => $request->input('target_language', 'en-GB'),
        ]);

        $request->validate([
            'docx_file' => ['required', 'file', 'mimes:docx', 'max:'.config('translator.max_upload_kb')],
            'profile' => ['nullable', 'in:'.implode(',', array_keys(self::PROFILES))],
            'custom_words' => ['nullable', 'string'],
            'source_language' => ['nullable', 'in:'.implode(',', self::SOURCE_LANGUAGES)],
            'target_language' => ['nullable', 'in:'.implode(',', self::TARGET_LANGUAGES)],
        ], [
            'docx_file.required' => 'File DOCX belum dipilih.',
            'docx_file.mimes' => 'Format file harus .docx',
            'docx_file.max' => 'Ukuran file maksimal 25 MB.',
        ]);

        $file = $request->file('docx_file');
        Log::info('translation.validated', [
            'request_id' => $requestId,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
        $token = Str::lower(Str::random(10));
        $directory = storage_path('app/private/translator');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $input = $directory.DIRECTORY_SEPARATOR.$token.'_input.docx';
        $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document';
        $targetLanguage = $request->string('target_language')->value() ?: 'en-GB';
        $downloadName = $stem.' '.$targetLanguage.'.docx';
        $output = $directory.DIRECTORY_SEPARATOR.$token.'_output.docx';
        $file->move($directory, basename($input));
        Log::info('translation.input_stored', [
            'request_id' => $requestId,
            'input_exists' => is_file($input),
            'directory_writable' => is_writable($directory),
        ]);

        return response()->stream(function () use (
            $input, $output, $downloadName, $targetLanguage, $request, $requestId, $startedAt, $token
        ): void {
            $send = static function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send(['type' => 'progress', 'stage' => 'document_received', 'percent' => 12]);

            try {
                $result = $this->worker->run('docx', [
                    '--input', $input, '--output', $output,
                    '--profile', $request->string('profile')->value() ?: self::DEFAULT_PROFILE,
                    '--custom-words', base64_encode($request->string('custom_words')->value()),
                    '--source-language', $request->string('source_language')->value() ?: 'auto',
                    '--target-language', $targetLanguage,
                ], $requestId, static function (array $progress) use ($send): void {
                    $send(['type' => 'progress', ...$progress]);
                });
                $summary = $result['summary'];
                file_put_contents($output.'.json', json_encode([
                    'name' => $downloadName,
                    'created_at' => time(),
                ], JSON_UNESCAPED_UNICODE));
                Log::info('translation.completed', [
                    'request_id' => $requestId,
                    'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                    'output_size_bytes' => is_file($output) ? filesize($output) : null,
                    'summary' => $summary,
                ]);
                $send([
                    'type' => 'complete',
                    'percent' => 100,
                    'download_url' => route('translation.download', ['token' => $token]),
                    'download_name' => $downloadName,
                    'summary' => $summary,
                ]);
            } catch (Throwable $e) {
                @unlink($output);
                Log::error('translation.failed', [
                    'request_id' => $requestId,
                    'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                    'exception' => $e,
                ]);
                $send([
                    'type' => 'error',
                    'error' => 'Terjadi kesalahan saat memproses dokumen: '.$e->getMessage(),
                    'error_id' => $requestId,
                ]);
            } finally {
                @unlink($input);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'X-Request-ID' => $requestId,
        ]);
    }

    public function downloadTranslation(string $token): BinaryFileResponse|JsonResponse
    {
        if (! preg_match('/^[a-z0-9]{10}$/', $token)) {
            abort(404);
        }

        $output = storage_path('app/private/translator/'.$token.'_output.docx');
        $metadataPath = $output.'.json';
        if (! is_file($output) || ! is_file($metadataPath)) {
            return response()->json(['error' => 'Hasil terjemahan tidak ditemukan atau sudah diunduh.'], 404);
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        @unlink($metadataPath);

        return response()->download(
            $output,
            $metadata['name'] ?? 'translated.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    public function ocrTranslate(Request $request): JsonResponse|BinaryFileResponse
    {
        $request->validate([
            'image_file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/tiff', 'max:'.config('translator.max_upload_kb')],
            'profile' => ['nullable', 'in:'.implode(',', array_keys(self::PROFILES))],
            'custom_words' => ['nullable', 'string'],
        ], [
            'image_file.required' => 'File gambar belum dipilih.',
            'image_file.mimetypes' => 'Format file harus JPG, PNG, WEBP, atau TIFF.',
        ]);

        $file = $request->file('image_file');
        $token = Str::lower(Str::random(10));
        $directory = storage_path('app/private/translator');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $input = $directory.DIRECTORY_SEPARATOR.$token.'_image';
        $output = $directory.DIRECTORY_SEPARATOR.$token.'_translated.png';
        $file->move($directory, basename($input));

        try {
            $this->worker->run('ocr', [
                '--input', $input, '--output', $output,
                '--profile', $request->string('profile')->value() ?: self::DEFAULT_PROFILE,
                '--custom-words', base64_encode($request->string('custom_words')->value()),
            ], $token);

            return response()->download($output, 'translated_'.$token.'.png', ['Content-Type' => 'image/png'])
                ->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            @unlink($output);
            report($e);
            $status = str_contains($e->getMessage(), 'OCR tidak tersedia') ? 503 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        } finally {
            @unlink($input);
        }
    }

    public function translatePdf(Request $request): JsonResponse|BinaryFileResponse
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        Log::info('pdf_translation.request_received', [
            'request_id' => $requestId,
            'content_length' => $request->server('CONTENT_LENGTH'),
            'output_format' => $request->input('output_format', 'pdf'),
        ]);

        $request->validate([
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:'.config('translator.max_upload_kb')],
            'output_format' => ['required', 'in:pdf,docx'],
            'source_language' => ['nullable', 'in:'.implode(',', self::SOURCE_LANGUAGES)],
            'target_language' => ['nullable', 'in:'.implode(',', self::TARGET_LANGUAGES)],
        ], [
            'pdf_file.required' => 'File PDF belum dipilih.',
            'pdf_file.mimes' => 'Format file harus .pdf',
            'pdf_file.max' => 'Ukuran file maksimal 25 MB.',
            'output_format.in' => 'Hasil PDF harus dipilih sebagai PDF atau DOCX.',
        ]);

        $file = $request->file('pdf_file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $token = Str::lower(Str::random(10));
        $directory = storage_path('app/private/translator');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $input = $directory.DIRECTORY_SEPARATOR.$token.'_input.pdf';
        $outputFormat = $request->string('output_format')->value();
        $output = $directory.DIRECTORY_SEPARATOR.$token.'_output.'.$outputFormat;
        $stem = pathinfo($originalName, PATHINFO_FILENAME) ?: 'document';
        $targetLanguage = $request->string('target_language')->value() ?: 'en-GB';
        $downloadName = $stem.' '.$targetLanguage.'.'.$outputFormat;
        $file->move($directory, basename($input));

        Log::info('pdf_translation.input_stored', [
            'request_id' => $requestId,
            'original_name' => $originalName,
            'size_bytes' => $fileSize,
            'output_format' => $outputFormat,
            'input_exists' => is_file($input),
        ]);

        try {
            $result = $this->worker->run('pdf', [
                '--input', $input,
                '--output', $output,
                '--output-format', $outputFormat,
                '--profile', self::DEFAULT_PROFILE,
                '--source-language', $request->string('source_language')->value() ?: 'auto',
                '--target-language', $targetLanguage,
            ], $requestId);
            $summary = $result['summary'];
            Log::info('pdf_translation.completed', [
                'request_id' => $requestId,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'output_size_bytes' => is_file($output) ? filesize($output) : null,
                'summary' => $summary,
            ]);

            $contentType = $outputFormat === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

            return response()->download($output, $downloadName, [
                'Content-Type' => $contentType,
                'X-Request-ID' => $requestId,
                'X-Elapsed-Seconds' => number_format($summary['elapsed_seconds'] ?? 0, 1, '.', ''),
                'X-Output-Format' => $outputFormat,
            ])->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            @unlink($output);
            Log::error('pdf_translation.failed', [
                'request_id' => $requestId,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Terjadi kesalahan saat memproses PDF: '.$e->getMessage(),
                'error_id' => $requestId,
            ], 500, ['X-Request-ID' => $requestId]);
        } finally {
            @unlink($input);
        }
    }
}
