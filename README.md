# DOCX Translator Pro — Laravel

Migrasi Laravel dari aplikasi Flask di folder induk. Laravel menangani halaman web, validasi upload, endpoint, respons unduhan, dan pembersihan file. Mesin pemrosesan Python disertakan sebagai worker CLI internal agar hasil terjemahan DOCX dan OCR tetap identik tanpa menjalankan Flask.

## Fitur

- Penerjemahan DOCX termasuk body, header, footer, footnotes, endnotes, dan comments.
- Google Translate, kamus British English bawaan, academic polishing, dan tiga profil hasil.
- OCR JPG/PNG/WEBP/TIFF (Bahasa Indonesia + Inggris), penerjemahan, dan overlay hasil ke PNG.
- Batas upload 25 MB, statistik proses melalui response header, health endpoint, dan pembersihan file sementara.

## Instalasi

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Worker memerlukan Python 3 dan dependensi yang sama dengan aplikasi asal:

```bash
python -m pip install -r python/requirements.txt
```

Atur `PYTHON_BINARY` di `.env`. Contoh Windows:

```dotenv
PYTHON_BINARY=D:\path\to\.venv\Scripts\python.exe
TRANSLATOR_TIMEOUT=900
TRANSLATOR_PARALLEL_WORKERS=3
```

Mesin menerjemahkan hingga tiga batch secara paralel, melewati teks yang sudah
menggunakan bahasa tujuan, dan menyimpan hasil berulang pada cache SQLite di
`storage/app/private/translator-cache.sqlite`. Gunakan 1–4 parallel worker;
nilai 3 direkomendasikan agar lebih cepat tanpa terlalu agresif terhadap layanan terjemahan.

Lalu jalankan:

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`. Endpoint kesehatan tersedia di `/health`.

EasyOCR mengunduh model pertama kali digunakan (sekitar 200 MB). Untuk produksi, pastikan PHP mengizinkan upload 25 MB (`upload_max_filesize` dan `post_max_size`) dan proses web memiliki hak tulis ke `storage`.

## Pengujian

```bash
php artisan test
python python/worker.py --help
```
