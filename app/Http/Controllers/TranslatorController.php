<?php

namespace App\Http\Controllers;

use App\Services\PythonWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TranslatorController extends Controller
{
    private const DEFAULT_PROFILE = 'academic';
    private const SOURCE_LANGUAGES = ['auto', 'id', 'en-US', 'en-GB'];
    private const TARGET_LANGUAGES = ['id', 'en-US', 'en-GB'];

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

    public function translate(Request $request): JsonResponse|BinaryFileResponse
    {
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
        $token = Str::lower(Str::random(10));
        $directory = storage_path('app/private/translator');
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $input = $directory.DIRECTORY_SEPARATOR.$token.'_input.docx';
        $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document';
        $targetLanguage = $request->string('target_language')->value() ?: 'en-GB';
        $downloadName = $stem.' '.$targetLanguage.'.docx';
        $output = $directory.DIRECTORY_SEPARATOR.$token.'_output.docx';
        $file->move($directory, basename($input));

        try {
            $result = $this->worker->run('docx', [
                '--input', $input, '--output', $output,
                '--profile', $request->string('profile')->value() ?: self::DEFAULT_PROFILE,
                '--custom-words', base64_encode($request->string('custom_words')->value()),
                '--source-language', $request->string('source_language')->value() ?: 'auto',
                '--target-language', $targetLanguage,
            ]);
            $summary = $result['summary'];
            return response()->download($output, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Translated-Paragraphs' => (string) ($summary['translated'] ?? 0),
                'X-Cached-Paragraphs' => (string) ($summary['cached'] ?? 0),
                'X-Skipped-Paragraphs' => (string) ($summary['skipped'] ?? 0),
                'X-Errors' => (string) ($summary['errors'] ?? 0),
                'X-Elapsed-Seconds' => number_format($summary['elapsed_seconds'] ?? 0, 1, '.', ''),
                'X-Profile' => (string) ($summary['profile'] ?? self::DEFAULT_PROFILE),
                'X-Target-Language' => $targetLanguage,
            ])->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            @unlink($output);
            report($e);
            return response()->json(['error' => 'Terjadi kesalahan saat memproses dokumen: '.$e->getMessage()], 500);
        } finally {
            @unlink($input);
        }
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
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $input = $directory.DIRECTORY_SEPARATOR.$token.'_image';
        $output = $directory.DIRECTORY_SEPARATOR.$token.'_translated.png';
        $file->move($directory, basename($input));

        try {
            $this->worker->run('ocr', [
                '--input', $input, '--output', $output,
                '--profile', $request->string('profile')->value() ?: self::DEFAULT_PROFILE,
                '--custom-words', base64_encode($request->string('custom_words')->value()),
            ]);
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
}
