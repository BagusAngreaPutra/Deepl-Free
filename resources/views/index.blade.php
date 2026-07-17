<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>JDS Trasnlator</title>
  <link rel="icon" href="{{ route('brand.logo') }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ route('home') }}" aria-label="JDS Trasnlator">
      <span class="brand-logo"><img src="{{ route('brand.logo') }}" alt="Logo JDS"></span>
      <span><strong>JDS Trasnlator</strong><small>Document translation workspace</small></span>
    </a>
    <span class="secure-note"><i></i> Siap menerjemahkan</span>
  </header>

  <main class="container">
    <form id="translatorForm" class="workspace">
      @csrf
      <div class="workspace-head">
        <div><span class="section-label">DOCUMENT TRANSLATOR</span><h1>Terjemahkan dokumen</h1></div>
        <span class="file-limit">Maks. 25 MB / file</span>
      </div>

      <label class="upload-zone" id="uploadZone">
        <input type="file" id="docxFile" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple hidden>
        <span class="upload-icon" aria-hidden="true">↑</span>
        <strong>Pilih atau tarik file DOCX dan PDF ke sini</strong>
        <span>Bisa mencampur beberapa dokumen sekaligus</span>
        <button type="button" class="choose-btn" id="chooseBtn">Pilih file</button>
      </label>

      <div class="language-picker" aria-label="Pilihan bahasa">
        <div class="field-group">
          <label for="sourceLanguage">Dari</label>
          <select id="sourceLanguage" name="source_language">
            <option value="auto">Deteksi otomatis</option>
            <option value="id">Indonesia</option>
            <option value="en-US">English (United States)</option>
            <option value="en-GB">English (British)</option>
          </select>
        </div>
        <button type="button" class="swap-btn" id="swapLanguages" aria-label="Tukar bahasa" title="Tukar bahasa">⇄</button>
        <div class="field-group">
          <label for="targetLanguage">Ke</label>
          <select id="targetLanguage" name="target_language">
            <option value="en-GB">English (British)</option>
            <option value="en-US">English (United States)</option>
            <option value="id">Indonesia</option>
          </select>
        </div>
      </div>

      <section class="queue-card" id="queueCard" aria-live="polite">
        <div class="empty-state" id="emptyState">Belum ada file dipilih.</div>
        <div class="queue-head hidden" id="queueHead">
          <div><strong id="queueTitle">0 file</strong><span id="queueSummary">Siap diproses</span></div>
          <button type="button" class="text-btn" id="clearBtn">Hapus semua</button>
        </div>
        <div class="overall-progress hidden" id="overallProgress">
          <div><span>Progres keseluruhan</span><strong id="overallPercent">0%</strong></div>
          <div class="progress-track"><span id="overallBar"></span></div>
        </div>
        <div class="file-list" id="fileList"></div>
      </section>

      <button type="submit" class="primary-btn" id="submitBtn" disabled>Terjemahkan semua file</button>
      <p class="form-note">Hasil otomatis diunduh. Biarkan halaman tetap terbuka selama proses berlangsung.</p>
    </form>
  </main>

  <div class="modal-backdrop hidden" id="pdfOptionsModal" role="dialog" aria-modal="true" aria-labelledby="pdfModalTitle">
    <div class="modal-card">
      <div class="modal-icon">PDF</div>
      <div class="modal-copy">
        <span class="section-label">FORMAT HASIL</span>
        <h2 id="pdfModalTitle">Pilih hasil untuk file PDF</h2>
        <p>Setiap PDF dapat diterjemahkan kembali sebagai PDF atau dikonversi menjadi DOCX yang dapat diedit.</p>
      </div>
      <div class="pdf-option-list" id="pdfOptionList"></div>
      <button type="button" class="primary-btn modal-confirm" id="confirmPdfOptions">Gunakan pilihan</button>
    </div>
  </div>

  <div class="toast hidden" id="toast" role="status"><strong id="toastTitle"></strong><span id="toastMessage"></span></div>
  <script src="{{ asset('app.js') }}"></script>
</body>
</html>
