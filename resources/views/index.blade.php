<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>DOCX Translator</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ route('home') }}" aria-label="DOCX Translator">
      <span class="brand-mark">D</span>
      <span><strong>DOCX Translator</strong><small>Indonesia ke British English</small></span>
    </a>
    <span class="secure-note">File diproses satu per satu</span>
  </header>

  <main class="container">
    <form id="translatorForm" class="workspace">
      @csrf
      <label class="upload-zone" id="uploadZone">
        <input type="file" id="docxFile" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple hidden>
        <span class="upload-icon" aria-hidden="true">↑</span>
        <strong>Pilih atau tarik file DOCX ke sini</strong>
        <span>Bisa memilih beberapa file sekaligus · Maksimal 25 MB per file</span>
        <button type="button" class="choose-btn" id="chooseBtn">Pilih file</button>
      </label>

      <div class="settings-grid">
        <div class="field-group">
          <label for="profile">Gaya hasil</label>
          <select id="profile" name="profile">
            @foreach ($profiles as $key => $profile)
              <option value="{{ $key }}" @selected($key === 'edu_academic')>{{ $profile['label'] }}</option>
            @endforeach
          </select>
        </div>
        <details class="dictionary">
          <summary>Tambahkan kamus khusus <span>Opsional</span></summary>
          <textarea id="customWords" name="custom_words" rows="5" placeholder="course contract=Course Agreement&#10;submission proof=Evidence of submission"></textarea>
          <small>Satu pasangan kata per baris dengan format kata=terjemahan.</small>
        </details>
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
      <p class="form-note">Hasil akan otomatis diunduh. Biarkan halaman ini tetap terbuka selama proses berlangsung.</p>
    </form>
  </main>

  <div class="toast hidden" id="toast" role="status"><strong id="toastTitle"></strong><span id="toastMessage"></span></div>
  <script src="{{ asset('app.js') }}"></script>
</body>
</html>
