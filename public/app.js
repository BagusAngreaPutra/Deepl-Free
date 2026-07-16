const form = document.getElementById('translatorForm');
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('docxFile');
const fileChip = document.getElementById('fileChip');
const loadingOverlay = document.getElementById('loadingOverlay');
const submitBtn = document.getElementById('submitBtn');
const statusBox = document.getElementById('statusBox');
const resultStats = document.getElementById('resultStats');

const statTranslated = document.getElementById('statTranslated');
const statCached = document.getElementById('statCached');
const statSkipped = document.getElementById('statSkipped');
const statErrors = document.getElementById('statErrors');
const statElapsed = document.getElementById('statElapsed');

function updateFileChip(file) {
  if (!file) {
    fileChip.classList.add('hidden');
    fileChip.textContent = '';
    return;
  }
  fileChip.classList.remove('hidden');
  fileChip.textContent = `File dipilih: ${file.name}`;
}

function setStatus(type, title, message) {
  statusBox.className = `status-box ${type}`;
  statusBox.innerHTML = `<strong>${title}</strong><p>${message}</p>`;
}

function showLoading(show) {
  loadingOverlay.classList.toggle('hidden', !show);
  submitBtn.disabled = show;
}

function setStats(headers) {
  resultStats.classList.remove('hidden');
  statTranslated.textContent = headers.get('X-Translated-Paragraphs') || '0';
  statCached.textContent = headers.get('X-Cached-Paragraphs') || '0';
  statSkipped.textContent = headers.get('X-Skipped-Paragraphs') || '0';
  statErrors.textContent = headers.get('X-Errors') || '0';
  statElapsed.textContent = `${headers.get('X-Elapsed-Seconds') || '0'}s`;
}

uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
  uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  const file = e.dataTransfer.files?.[0];
  if (!file) return;
  if (!file.name.toLowerCase().endsWith('.docx')) {
    setStatus('error', 'Format tidak didukung', 'Silakan gunakan file dengan ekstensi .docx.');
    return;
  }
  const dt = new DataTransfer();
  dt.items.add(file);
  fileInput.files = dt.files;
  updateFileChip(file);
  setStatus('idle', 'File siap diproses', 'Dokumen telah dipilih dan siap diterjemahkan.');
});

fileInput.addEventListener('change', (e) => {
  const file = e.target.files?.[0];
  updateFileChip(file);
  if (file) {
    setStatus('idle', 'File siap diproses', 'Dokumen telah dipilih dan siap diterjemahkan.');
  }
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const file = fileInput.files?.[0];
  if (!file) {
    setStatus('error', 'File belum dipilih', 'Silakan pilih dokumen DOCX terlebih dahulu.');
    return;
  }

  const formData = new FormData(form);
  showLoading(true);
  setStatus('processing', 'Proses berjalan', 'Dokumen sedang diterjemahkan. Hasil akan otomatis diunduh setelah selesai.');

  try {
    const response = await fetch('/translate', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      let message = 'Terjadi kesalahan saat memproses dokumen.';
      try {
        const data = await response.json();
        if (data?.error) message = data.error;
      } catch (_) {}
      throw new Error(message);
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = disposition.match(/filename="?([^\"]+)"?/i);
    const filename = filenameMatch?.[1] || 'translated_British_EN.docx';

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    setStats(response.headers);
    setStatus('success', 'Terjemahan selesai', 'Dokumen berhasil diproses dan file hasil sudah diunduh.');
  } catch (error) {
    const message = error instanceof TypeError
      ? 'Koneksi ke server terputus sebelum proses selesai. Coba lagi; jika berulang, periksa status atau log server.'
      : (error.message || 'Terjadi kendala saat menerjemahkan dokumen.');
    setStatus('error', 'Proses gagal', message);
  } finally {
    showLoading(false);
  }
});

// ⚙️ OCR MODE
const modeTabs = document.querySelectorAll('.mode-tab');
const docxModeDiv = document.getElementById('docxMode');
const ocrModeDiv = document.getElementById('ocrMode');
const ocrForm = document.getElementById('ocrForm');
const uploadZoneOCR = document.getElementById('uploadZoneOCR');
const imageFileInput = document.getElementById('imageFile');
const fileChipOCR = document.getElementById('fileChipOCR');
const previewImage = document.getElementById('previewImage');
const ocrPreview = document.getElementById('ocrPreview');
const submitOcrBtn = document.getElementById('submitOcrBtn');
const ocrStatusBox = document.getElementById('ocrStatusBox');
const ocrResult = document.getElementById('ocrResult');
const ocrExtractedText = document.getElementById('ocrExtractedText');
const ocrTranslatedText = document.getElementById('ocrTranslatedText');

function setOcrStatus(type, title, message) {
  ocrStatusBox.className = `status-box ${type}`;
  ocrStatusBox.innerHTML = `<strong>${title}</strong><p>${message}</p>`;
}

// Mode tab switching
modeTabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    const mode = tab.dataset.mode;
    modeTabs.forEach((t) => t.classList.remove('active'));
    tab.classList.add('active');
    
    if (mode === 'docx') {
      docxModeDiv.classList.remove('hidden');
      ocrModeDiv.classList.add('hidden');
    } else {
      docxModeDiv.classList.add('hidden');
      ocrModeDiv.classList.remove('hidden');
    }
  });
});

// OCR image handling
function updateFileChipOCR(file) {
  if (!file) {
    fileChipOCR.classList.add('hidden');
    fileChipOCR.textContent = '';
    ocrPreview.classList.add('hidden');
    return;
  }
  fileChipOCR.classList.remove('hidden');
  fileChipOCR.textContent = `File dipilih: ${file.name}`;

  // Show preview
  const reader = new FileReader();
  reader.onload = (e) => {
    previewImage.src = e.target.result;
    ocrPreview.classList.remove('hidden');
  };
  reader.readAsDataURL(file);
}

uploadZoneOCR.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZoneOCR.classList.add('dragover');
});

uploadZoneOCR.addEventListener('dragleave', () => {
  uploadZoneOCR.classList.remove('dragover');
});

uploadZoneOCR.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZoneOCR.classList.remove('dragover');
  const file = e.dataTransfer.files?.[0];
  if (!file) return;
  
  const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/tiff'];
  if (!validTypes.includes(file.type)) {
    setOcrStatus('error', 'Format tidak didukung', 'Silakan gunakan file JPG, PNG, WEBP, atau TIFF.');
    return;
  }

  const dt = new DataTransfer();
  dt.items.add(file);
  imageFileInput.files = dt.files;
  updateFileChipOCR(file);
  setOcrStatus('idle', 'File siap diproses', 'Gambar telah dipilih dan siap untuk ekstraksi teks.');
});

imageFileInput.addEventListener('change', (e) => {
  const file = e.target.files?.[0];
  updateFileChipOCR(file);
  if (file) {
    setOcrStatus('idle', 'File siap diproses', 'Gambar telah dipilih dan siap untuk ekstraksi teks.');
  }
});

// OCR form submission
ocrForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  const file = imageFileInput.files?.[0];
  if (!file) {
    setOcrStatus('error', 'File belum dipilih', 'Silakan pilih gambar terlebih dahulu.');
    return;
  }

  const formData = new FormData(ocrForm);
  showLoading(true);
  setOcrStatus('processing', 'Proses berjalan', 'Teks sedang diekstraksi, diterjemahkan, dan digabungkan ke gambar...');

  try {
    const response = await fetch('/ocr-translate', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      let message = 'Terjadi kesalahan saat memproses gambar.';
      try {
        const data = await response.json();
        if (data?.error) message = data.error;
      } catch (_) {}
      throw new Error(message);
    }

    // Response is a blob (image file), not JSON
    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = disposition.match(/filename="?([^\"]+)"?/i);
    const filename = filenameMatch?.[1] || 'translated_image.png';

    // Download the translated image
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    // Hide result display section since we're downloading the image
    ocrResult.classList.add('hidden');
    setOcrStatus('success', 'Proses selesai!', 'Gambar dengan teks terjemahan sudah berhasil diunduh.');
  } catch (error) {
    const message = error instanceof TypeError
      ? 'Koneksi ke server terputus sebelum proses selesai. Coba lagi; jika berulang, periksa status atau log server.'
      : (error.message || 'Terjadi kendala saat memproses gambar.');
    setOcrStatus('error', 'Proses gagal', message);
  } finally {
    showLoading(false);
  }
});

// Copy & Download OCR results
document.getElementById('copyOcrBtn')?.addEventListener('click', () => {
  const text = ocrTranslatedText.textContent;
  navigator.clipboard.writeText(text).then(() => {
    alert('Teks terjemahan telah disalin ke clipboard.');
  }).catch(() => {
    alert('Gagal menyalin teks.');
  });
});

document.getElementById('downloadOcrBtn')?.addEventListener('click', () => {
  const text = ocrTranslatedText.textContent;
  const blob = new Blob([text], { type: 'text/plain; charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'ocr-translated.txt';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
});
