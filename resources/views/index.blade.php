<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DOCX Translator Pro</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}" />
</head>
<body>
  <div class="page-shell"></div>

  <header class="topbar">
    <div class="brand">
      <div class="brand-mark">D</div>
      <div>
        <p class="brand-title">DOCX Translator Pro</p>
        <p class="brand-subtitle">Indonesia → British English</p>
      </div>
    </div>
    <a class="ghost-btn" href="#translator">Mulai Terjemahkan</a>
  </header>

  <main class="container">
    <section class="hero">
      <div class="hero-copy">
        <span class="eyebrow">Web HTML versi profesional</span>
        <h1>Ubah dokumen <span>DOCX</span> menjadi British academic English dengan hasil yang lebih rapi, lebih natural, dan lebih siap pakai.</h1>
        <p class="hero-text">
          Aplikasi ini mempertahankan struktur file DOCX, menerjemahkan isi dokumen, lalu menjalankan tahap academic polishing untuk memperbaiki frasa, paralelisme, dan istilah British English.
        </p>

        <div class="hero-points">
          <div class="point-card">
            <strong>Layout aman</strong>
            <span>Struktur paragraf, header, footer, dan bagian utama tetap diproses langsung dari XML DOCX.</span>
          </div>
          <div class="point-card">
            <strong>Lebih akurat</strong>
            <span>Hasil diterjemahkan lalu dipoles agar lebih mendekati British academic English, bukan sekadar ganti ejaan.</span>
          </div>
          <div class="point-card">
            <strong>Fleksibel</strong>
            <span>Dapat menambahkan padanan kata custom dan memilih profil polishing sesuai jenis dokumen.</span>
          </div>
        </div>
      </div>

      <div class="hero-panel">
        <div class="panel-window">
          <div class="window-top">
            <span></span><span></span><span></span>
          </div>
          <div class="workflow-card">
            <div class="workflow-item">
              <div class="workflow-icon">1</div>
              <div>
                <h3>Upload DOCX</h3>
                <p>Pilih dokumen Word berbahasa Indonesia.</p>
              </div>
            </div>
            <div class="workflow-item">
              <div class="workflow-icon">2</div>
              <div>
                <h3>Batch Translate</h3>
                <p>Teks diekstraksi dan diterjemahkan secara efisien.</p>
              </div>
            </div>
            <div class="workflow-item">
              <div class="workflow-icon">3</div>
              <div>
                <h3>Academic Polishing</h3>
                <p>Perapian frasa akademik, lesson plan, CLO, dan rubric.</p>
              </div>
            </div>
            <div class="workflow-item">
              <div class="workflow-icon">4</div>
              <div>
                <h3>Download Hasil</h3>
                <p>File keluaran siap diunduh dalam format DOCX.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="stats-grid">
      <article class="stat-box">
        <strong>DOCX Native</strong>
        <span>Memproses file .docx langsung</span>
      </article>
      <article class="stat-box">
        <strong>Header & Footer</strong>
        <span>Ikut diproses bila tersedia</span>
      </article>
      <article class="stat-box">
        <strong>Custom Dictionary</strong>
        <span>Dukungan istilah spesifik pengguna</span>
      </article>
      <article class="stat-box">
        <strong>Clean UI</strong>
        <span>Antarmuka ringan dan mudah dipahami</span>
      </article>
    </section>

    <section class="translator-section" id="translator">
      <div class="section-heading">
        <span class="eyebrow">Translator workspace</span>
        <h2>Unggah dokumen dan jalankan proses terjemahan</h2>
        <p>Pilih profil polishing agar hasil lebih sesuai untuk dokumen umum, akademik, atau dokumen perkuliahan.</p>
      </div>

      <!-- Mode tabs: DOCX or OCR -->
      <div class="mode-tabs">
        <button class="mode-tab active" data-mode="docx">
          <span class="mode-icon">📄</span> DOCX Translator
        </button>
        <button class="mode-tab" data-mode="ocr">
          <span class="mode-icon">🖼️</span> OCR Translator
        </button>
      </div>

      <!-- DOCX Mode -->
      <div class="workspace-grid" id="docxMode">
        <form id="translatorForm" class="translator-card">
          @csrf
          <label class="upload-zone" id="uploadZone">
            <input type="file" id="docxFile" name="docx_file" accept=".docx" hidden>
            <div class="upload-illustration">⇪</div>
            <h3>Tarik file DOCX ke sini</h3>
            <p>atau klik untuk memilih dokumen dari komputer Anda</p>
            <span class="upload-hint">Format yang didukung: .docx</span>
            <div class="file-chip hidden" id="fileChip"></div>
          </label>

          <div class="field-group">
            <label for="profile">Profil hasil terjemahan</label>
            <select id="profile" name="profile">
              @foreach ($profiles as $key => $profile)
              <option value="{{ $key }}" @selected($key === 'edu_academic')>{{ $profile['label'] }}</option>
              @endforeach
            </select>
            <small>Gunakan <strong>British Academic English for Course Documents</strong> untuk CLO/Sub-CLO, lesson plan, assignment, dan assessment rubric.</small>
          </div>

          <div class="field-group">
            <label for="customWords">Custom dictionary (opsional)</label>
            <textarea id="customWords" name="custom_words" rows="8" placeholder="Contoh:
minimal pair=minimal pairs
course contract=Course Agreement
submission proof=Evidence of submission"></textarea>
            <small>Satu baris satu pasangan kata. Gunakan format <strong>american=british</strong> atau <strong>american:british</strong>.</small>
          </div>

          <button type="submit" class="primary-btn" id="submitBtn">Terjemahkan Dokumen</button>
        </form>

        <aside class="info-card">
          <h3>Yang diproses oleh sistem</h3>
          <ul class="feature-list">
            <li>Isi utama dokumen Word</li>
            <li>Header dan footer yang terdeteksi</li>
            <li>Footnotes, endnotes, dan comments bila ada</li>
            <li>Padanan British English + academic polishing</li>
          </ul>

          <div class="mini-divider"></div>

          <h3>Status proses</h3>
          <div id="statusBox" class="status-box idle">
            <strong>Siap digunakan</strong>
            <p>Silakan pilih file DOCX untuk memulai.</p>
          </div>

          <div id="resultStats" class="result-stats hidden">
            <div><span>Diterjemahkan</span><strong id="statTranslated">0</strong></div>
            <div><span>Cache</span><strong id="statCached">0</strong></div>
            <div><span>Dilewati</span><strong id="statSkipped">0</strong></div>
            <div><span>Error</span><strong id="statErrors">0</strong></div>
            <div><span>Durasi</span><strong id="statElapsed">0s</strong></div>
          </div>
        </aside>
      </div>

      <!-- OCR Mode -->
      <div class="workspace-grid hidden" id="ocrMode">
        <form id="ocrForm" class="translator-card">
          @csrf
          <label class="upload-zone" id="uploadZoneOCR">
            <input type="file" id="imageFile" name="image_file" accept="image/*" hidden>
            <div class="upload-illustration">🖼️</div>
            <h3>Tarik gambar ke sini</h3>
            <p>atau klik untuk memilih gambar dari komputer Anda</p>
            <span class="upload-hint">Format yang didukung: JPG, PNG, WEBP, TIFF</span>
            <div class="file-chip hidden" id="fileChipOCR"></div>
          </label>

          <div id="ocrPreview" class="ocr-preview hidden">
            <img id="previewImage" src="" alt="Preview">
          </div>

          <div class="field-group">
            <label for="ocrProfile">Profil hasil terjemahan</label>
            <select id="ocrProfile" name="profile">
              @foreach ($profiles as $key => $profile)
              <option value="{{ $key }}" @selected($key === 'edu_academic')>{{ $profile['label'] }}</option>
              @endforeach
            </select>
            <small>Pilih profil yang sesuai dengan jenis dokumen atau teks.</small>
          </div>

          <div class="field-group">
            <label for="ocrCustomWords">Custom dictionary (opsional)</label>
            <textarea id="ocrCustomWords" name="custom_words" rows="8" placeholder="Contoh:
minimal pair=minimal pairs
course contract=Course Agreement"></textarea>
            <small>Satu baris satu pasangan kata.</small>
          </div>

          <button type="submit" class="primary-btn" id="submitOcrBtn">Ekstrak & Terjemahkan</button>
        </form>

        <aside class="info-card">
          <h3>Hasil OCR & Terjemahan</h3>
          
          <div id="ocrStatusBox" class="status-box idle">
            <strong>Siap digunakan</strong>
            <p>Silakan pilih gambar untuk mulai OCR.</p>
          </div>

          <div id="ocrResult" class="ocr-result hidden">
            <div class="result-section">
              <h4>Teks Terdeteksi (Asli)</h4>
              <div id="ocrExtractedText" class="text-display"></div>
            </div>

            <div class="result-section">
              <h4>Teks Terjemahan</h4>
              <div id="ocrTranslatedText" class="text-display"></div>
            </div>

            <div class="result-actions">
              <button type="button" class="secondary-btn" id="copyOcrBtn">Salin Hasil</button>
              <button type="button" class="secondary-btn" id="downloadOcrBtn">Download TXT</button>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <div class="loading-overlay hidden" id="loadingOverlay">
    <div class="loader-card">
      <div class="loader"></div>
      <h3>Sedang memproses dokumen</h3>
      <p>Mohon tunggu, teks sedang diterjemahkan dan dirapikan ke gaya British academic English.</p>
    </div>
  </div>

  <script src="{{ asset('app.js') }}"></script>
</body>
</html>
