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
  <link rel="stylesheet" href="{{ asset('styles.css') }}?v={{ filemtime(public_path('styles.css')) }}">
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
    <nav class="mode-tabs" aria-label="Mode penerjemah">
      <button type="button" class="mode-tab active" data-mode="text">Teks</button>
      <button type="button" class="mode-tab" data-mode="document">Dokumen</button>
    </nav>

    <section id="textWorkspace" class="workspace text-workspace">
      <div class="workspace-head">
        <div><span class="section-label">TEXT TRANSLATOR</span><h1>Terjemahkan teks</h1></div>
        <span class="file-limit">Maks. 5.000 karakter</span>
      </div>
      <div class="text-language-bar">
        <select id="textSourceLanguage" aria-label="Bahasa sumber">
          <option value="auto">Deteksi otomatis</option>
          <option value="id">Indonesia</option><option value="en-US">English (United States)</option><option value="en-GB">English (British)</option>
          <option value="de">Deutsch</option><option value="fr">Français</option><option value="es">Español</option><option value="pt">Português</option>
          <option value="it">Italiano</option><option value="nl">Nederlands</option><option value="pl">Polski</option><option value="ru">Русский</option>
          <option value="ar">العربية</option><option value="tr">Türkçe</option><option value="zh-CN">中文</option><option value="ja">日本語</option>
          <option value="ko">한국어</option><option value="vi">Tiếng Việt</option><option value="th">ไทย</option><option value="ms">Bahasa Melayu</option>
        </select>
        <button type="button" class="swap-btn" id="swapTextLanguages" aria-label="Tukar bahasa">⇄</button>
        <select id="textTargetLanguage" aria-label="Bahasa tujuan">
          <option value="en-GB">English (British)</option><option value="en-US">English (United States)</option><option value="id">Indonesia</option>
          <option value="de">Deutsch</option><option value="fr">Français</option><option value="es">Español</option><option value="pt">Português</option>
          <option value="it">Italiano</option><option value="nl">Nederlands</option><option value="pl">Polski</option><option value="ru">Русский</option>
          <option value="ar">العربية</option><option value="tr">Türkçe</option><option value="zh-CN">中文</option><option value="ja">日本語</option>
          <option value="ko">한국어</option><option value="vi">Tiếng Việt</option><option value="th">ไทย</option><option value="ms">Bahasa Melayu</option>
        </select>
      </div>
      <div class="text-panels">
        <div class="text-panel input-panel">
          <textarea id="sourceText" maxlength="5000" placeholder="Ketik atau tempel teks di sini" autofocus></textarea>
          <div class="text-panel-foot"><button type="button" class="panel-action" id="clearText">Hapus</button><span id="characterCount">0 / 5.000</span></div>
        </div>
        <div class="text-panel output-panel">
          <textarea id="translatedText" placeholder="Hasil terjemahan" readonly></textarea>
          <div class="text-panel-foot"><span id="textStatus">Siap menerjemahkan</span><button type="button" class="panel-action" id="copyTranslation" disabled>Salin</button></div>
        </div>
      </div>
      <button type="button" class="primary-btn text-translate-btn" id="translateTextBtn" disabled>Terjemahkan</button>
    </section>

    <form id="translatorForm" class="workspace hidden">
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
            <option value="de">Deutsch (Jerman)</option>
            <option value="fr">Français (Prancis)</option>
            <option value="es">Español (Spanyol)</option>
            <option value="pt">Português (Portugis)</option>
            <option value="it">Italiano</option>
            <option value="nl">Nederlands (Belanda)</option>
            <option value="pl">Polski (Polandia)</option>
            <option value="ru">Русский (Rusia)</option>
            <option value="ar">العربية (Arab)</option>
            <option value="tr">Türkçe (Turki)</option>
            <option value="zh-CN">中文 (Mandarin Sederhana)</option>
            <option value="ja">日本語 (Jepang)</option>
            <option value="ko">한국어 (Korea)</option>
            <option value="vi">Tiếng Việt</option>
            <option value="th">ไทย (Thailand)</option>
            <option value="ms">Bahasa Melayu</option>
          </select>
        </div>
        <button type="button" class="swap-btn" id="swapLanguages" aria-label="Tukar bahasa" title="Tukar bahasa">⇄</button>
        <div class="field-group">
          <label for="targetLanguage">Ke</label>
          <select id="targetLanguage" name="target_language">
            <option value="en-GB">English (British)</option>
            <option value="en-US">English (United States)</option>
            <option value="id">Indonesia</option>
            <option value="de">Deutsch (Jerman)</option>
            <option value="fr">Français (Prancis)</option>
            <option value="es">Español (Spanyol)</option>
            <option value="pt">Português (Portugis)</option>
            <option value="it">Italiano</option>
            <option value="nl">Nederlands (Belanda)</option>
            <option value="pl">Polski (Polandia)</option>
            <option value="ru">Русский (Rusia)</option>
            <option value="ar">العربية (Arab)</option>
            <option value="tr">Türkçe (Turki)</option>
            <option value="zh-CN">中文 (Mandarin Sederhana)</option>
            <option value="ja">日本語 (Jepang)</option>
            <option value="ko">한국어 (Korea)</option>
            <option value="vi">Tiếng Việt</option>
            <option value="th">ไทย (Thailand)</option>
            <option value="ms">Bahasa Melayu</option>
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
  <script src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}"></script>
</body>
</html>
