const form = document.getElementById('translatorForm');
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('docxFile');
const chooseBtn = document.getElementById('chooseBtn');
const submitBtn = document.getElementById('submitBtn');
const clearBtn = document.getElementById('clearBtn');
const emptyState = document.getElementById('emptyState');
const queueHead = document.getElementById('queueHead');
const overallProgress = document.getElementById('overallProgress');
const overallPercent = document.getElementById('overallPercent');
const overallBar = document.getElementById('overallBar');
const queueTitle = document.getElementById('queueTitle');
const queueSummary = document.getElementById('queueSummary');
const fileList = document.getElementById('fileList');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const sourceLanguage = document.getElementById('sourceLanguage');
const targetLanguage = document.getElementById('targetLanguage');
const pdfOptionsModal = document.getElementById('pdfOptionsModal');
const pdfOptionList = document.getElementById('pdfOptionList');
const confirmPdfOptions = document.getElementById('confirmPdfOptions');
const textWorkspace = document.getElementById('textWorkspace');
const sourceText = document.getElementById('sourceText');
const translatedText = document.getElementById('translatedText');
const textSourceLanguage = document.getElementById('textSourceLanguage');
const textTargetLanguage = document.getElementById('textTargetLanguage');
const translateTextBtn = document.getElementById('translateTextBtn');
const copyTranslation = document.getElementById('copyTranslation');
const textStatus = document.getElementById('textStatus');

let items = [];
let running = false;
let audioContext;
let pendingPdfIds = [];

document.querySelectorAll('.mode-tab').forEach((tab) => tab.addEventListener('click', () => {
  document.querySelectorAll('.mode-tab').forEach((candidate) => candidate.classList.toggle('active', candidate === tab));
  const textMode = tab.dataset.mode === 'text';
  textWorkspace.classList.toggle('hidden', !textMode);
  form.classList.toggle('hidden', textMode);
}));

sourceText.addEventListener('input', () => {
  document.getElementById('characterCount').textContent = `${sourceText.value.length.toLocaleString('id-ID')} / 5.000`;
  translateTextBtn.disabled = !sourceText.value.trim();
});

document.getElementById('clearText').addEventListener('click', () => {
  sourceText.value = '';
  translatedText.value = '';
  sourceText.dispatchEvent(new Event('input'));
  copyTranslation.disabled = true;
  textStatus.textContent = 'Siap menerjemahkan';
  sourceText.focus();
});

document.getElementById('swapTextLanguages').addEventListener('click', () => {
  const oldSource = textSourceLanguage.value;
  textSourceLanguage.value = textTargetLanguage.value;
  textTargetLanguage.value = oldSource === 'auto' ? 'id' : oldSource;
  if (translatedText.value) {
    const oldText = sourceText.value;
    sourceText.value = translatedText.value;
    translatedText.value = oldText;
    sourceText.dispatchEvent(new Event('input'));
  }
});

translateTextBtn.addEventListener('click', async () => {
  const text = sourceText.value.trim();
  if (!text || translateTextBtn.disabled) return;
  translateTextBtn.disabled = true;
  translateTextBtn.textContent = 'Menerjemahkan…';
  textStatus.textContent = 'Sedang menerjemahkan';
  try {
    const response = await fetch('/translate-text', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
      body: JSON.stringify({text, source_language: textSourceLanguage.value, target_language: textTargetLanguage.value}),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || Object.values(data.errors || {}).flat()[0] || 'Terjemahan gagal');
    translatedText.value = data.translation;
    copyTranslation.disabled = false;
    textStatus.textContent = 'Terjemahan selesai';
  } catch (error) {
    textStatus.textContent = 'Terjemahan gagal';
    showToast('Terjemahan gagal', error.message);
  } finally {
    translateTextBtn.disabled = !sourceText.value.trim();
    translateTextBtn.textContent = 'Terjemahkan';
  }
});

copyTranslation.addEventListener('click', async () => {
  await navigator.clipboard.writeText(translatedText.value);
  textStatus.textContent = 'Hasil disalin';
});

document.getElementById('swapLanguages').addEventListener('click', () => {
  if (sourceLanguage.value === 'auto') {
    sourceLanguage.value = targetLanguage.value;
    targetLanguage.value = sourceLanguage.value === 'id' ? 'en-GB' : 'id';
    return;
  }
  const previousSource = sourceLanguage.value;
  sourceLanguage.value = targetLanguage.value;
  targetLanguage.value = previousSource === 'auto' ? 'en-GB' : previousSource;
});

chooseBtn.addEventListener('click', (event) => {
  event.preventDefault();
  fileInput.click();
});

fileInput.addEventListener('change', () => addFiles(fileInput.files));

['dragenter', 'dragover'].forEach((name) => uploadZone.addEventListener(name, (event) => {
  event.preventDefault();
  uploadZone.classList.add('dragover');
}));

['dragleave', 'drop'].forEach((name) => uploadZone.addEventListener(name, (event) => {
  event.preventDefault();
  uploadZone.classList.remove('dragover');
}));

uploadZone.addEventListener('drop', (event) => addFiles(event.dataTransfer.files));

clearBtn.addEventListener('click', () => {
  if (running) return;
  items.forEach(releaseDownload);
  items = [];
  fileInput.value = '';
  render();
});

function addFiles(fileCollection) {
  if (running) return;
  const incoming = [...fileCollection];
  let rejected = 0;
  const addedPdfs = [];
  for (const file of incoming) {
    const extension = file.name.toLowerCase().split('.').pop();
    const isSupported = ['docx', 'pdf'].includes(extension);
    const isWithinLimit = file.size <= 25 * 1024 * 1024;
    const duplicate = items.some((item) => item.file.name === file.name && item.file.size === file.size);
    if (!isSupported || !isWithinLimit || duplicate) {
      rejected += 1;
      continue;
    }
    const item = {
      id: crypto.randomUUID(), file, type: extension, outputFormat: extension === 'pdf' ? 'pdf' : 'docx',
      state: 'waiting', progress: 0, message: 'Menunggu',
    };
    items.push(item);
    if (extension === 'pdf') addedPdfs.push(item);
  }
  fileInput.value = '';
  render();
  if (addedPdfs.length) openPdfOptions(addedPdfs);
  if (rejected) showToast('Sebagian file tidak ditambahkan', `${rejected} file duplikat, formatnya bukan DOCX/PDF, atau melebihi 25 MB.`);
}

function openPdfOptions(pdfItems) {
  pendingPdfIds = pdfItems.map((item) => item.id);
  pdfOptionList.replaceChildren(...pdfItems.map((item) => {
    const row = document.createElement('div');
    row.className = 'pdf-option-row';
    row.dataset.id = item.id;
    row.innerHTML = `
      <div><strong></strong><span></span></div>
      <div class="format-switch" role="group" aria-label="Format hasil">
        <button type="button" data-format="pdf" class="active">PDF</button>
        <button type="button" data-format="docx">DOCX</button>
      </div>`;
    row.querySelector('strong').textContent = item.file.name;
    row.querySelector('span').textContent = formatBytes(item.file.size);
    row.querySelectorAll('.format-switch button').forEach((button) => button.addEventListener('click', () => {
      row.querySelectorAll('.format-switch button').forEach((candidate) => candidate.classList.remove('active'));
      button.classList.add('active');
    }));
    return row;
  }));
  pdfOptionsModal.classList.remove('hidden');
}

confirmPdfOptions.addEventListener('click', () => {
  pdfOptionList.querySelectorAll('.pdf-option-row').forEach((row) => {
    const item = items.find((candidate) => candidate.id === row.dataset.id);
    if (item) item.outputFormat = row.querySelector('.format-switch button.active').dataset.format;
  });
  pendingPdfIds = [];
  pdfOptionsModal.classList.add('hidden');
  render();
});

function render() {
  const hasItems = items.length > 0;
  emptyState.classList.toggle('hidden', hasItems);
  queueHead.classList.toggle('hidden', !hasItems);
  overallProgress.classList.toggle('hidden', !hasItems);
  submitBtn.disabled = !hasItems || running;
  clearBtn.disabled = running;
  queueTitle.textContent = `${items.length} file`;

  const completed = items.filter((item) => item.state === 'done').length;
  const failed = items.filter((item) => item.state === 'error').length;
  queueSummary.textContent = running
    ? `${completed} selesai${failed ? ` · ${failed} gagal` : ''}`
    : (completed || failed ? `${completed} selesai · ${failed} gagal` : 'Siap diproses');

  const totalProgress = items.length
    ? Math.round(items.reduce((sum, item) => sum + item.progress, 0) / items.length)
    : 0;
  overallPercent.textContent = `${totalProgress}%`;
  overallBar.style.width = `${totalProgress}%`;

  fileList.replaceChildren(...items.map(createFileRow));
  submitBtn.textContent = running ? 'Sedang menerjemahkan…' : `Terjemahkan ${items.length || 'semua'} file`;
}

function createFileRow(item) {
  const row = document.createElement('article');
  row.className = `file-row ${item.state}`;
  row.innerHTML = `
    <div class="file-badge"></div>
    <div class="file-main">
      <div class="file-meta"><strong></strong><span></span></div>
      <div class="progress-track"><span></span></div>
      <small></small>
    </div>
    <div class="file-actions"></div>`;
  row.querySelector('.file-meta strong').textContent = item.file.name;
  row.querySelector('.file-badge').textContent = item.type.toUpperCase();
  row.querySelector('.file-meta span').textContent = formatBytes(item.file.size);
  row.querySelector('.progress-track span').style.width = `${item.progress}%`;
  const formatLabel = item.type === 'pdf' ? `Hasil ${item.outputFormat.toUpperCase()} · ` : '';
  row.querySelector('small').textContent = `${formatLabel}${item.progress}% · ${item.message}`;
  const actions = row.querySelector('.file-actions');

  if (item.state === 'done' && item.downloadUrl) {
    const download = document.createElement('button');
    download.type = 'button';
    download.className = 'download-btn';
    download.textContent = 'Unduh';
    download.addEventListener('click', () => downloadResult(item));
    actions.append(download);
  } else if (!running) {
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'remove-btn';
    remove.setAttribute('aria-label', `Hapus ${item.file.name}`);
    remove.textContent = '×';
    remove.addEventListener('click', () => {
      releaseDownload(item);
      items = items.filter((candidate) => candidate.id !== item.id);
      render();
    });
    actions.append(remove);
  }
  return row;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (running || !items.length) return;
  running = true;
  prepareNotifications();
  items.filter((item) => item.state !== 'done').forEach((item) => {
    item.state = 'waiting'; item.progress = 0; item.message = 'Menunggu';
  });
  render();

  for (const item of items.filter((candidate) => candidate.state !== 'done')) {
    await processFile(item);
  }

  running = false;
  render();
  const completed = items.filter((item) => item.state === 'done').length;
  const failed = items.filter((item) => item.state === 'error').length;
  notifyFinished(completed, failed);
});

function processFile(item) {
  item.state = 'uploading'; item.message = 'Mengunggah'; render();
  const data = new FormData();
  data.append('_token', csrfToken);
  data.append(item.type === 'pdf' ? 'pdf_file' : 'docx_file', item.file);
  if (item.type === 'pdf') data.append('output_format', item.outputFormat);
  data.append('source_language', sourceLanguage.value);
  data.append('target_language', targetLanguage.value);

  if (item.type === 'docx') return processDocxWithLiveProgress(item, data);

  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', item.type === 'pdf' ? '/translate-pdf' : '/translate');
    xhr.responseType = 'blob';
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable) return;
      item.progress = Math.min(40, Math.round((event.loaded / event.total) * 40));
      item.message = 'Mengunggah';
      render();
    };
    xhr.upload.onload = () => {
      item.state = 'processing'; item.progress = 50; item.message = 'Diproses server'; render();
    };
    xhr.onload = async () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        item.state = 'done'; item.progress = 100; item.message = 'Selesai';
        item.downloadUrl = URL.createObjectURL(xhr.response);
        const outputExtension = item.type === 'pdf' ? item.outputFormat : 'docx';
        item.downloadName = getFilename(xhr.getResponseHeader('Content-Disposition')) || `${stripExtension(item.file.name)} ${targetLanguage.value}.${outputExtension}`;
        render();
        downloadResult(item);
      } else {
        item.state = 'error'; item.progress = 100;
        item.message = await readError(xhr.response);
        render();
        showToast(`Gagal: ${item.file.name}`, item.message);
      }
      resolve();
    };
    xhr.onerror = () => {
      item.state = 'error'; item.progress = 100; item.message = 'Koneksi ke server terputus';
      render();
      showToast(`Gagal: ${item.file.name}`, item.message);
      resolve();
    };
    xhr.send(data);
  });
}

function processDocxWithLiveProgress(item, data) {
  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    let consumed = 0;

    xhr.open('POST', '/translate');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable) return;
      item.progress = Math.min(10, Math.round((event.loaded / event.total) * 10));
      item.message = 'Mengunggah';
      render();
    };
    xhr.upload.onload = () => {
      item.state = 'processing';
      item.progress = Math.max(item.progress, 12);
      item.message = 'Membaca dokumen';
      render();
    };
    xhr.onprogress = () => {
      const chunk = xhr.responseText.slice(consumed);
      const lastNewline = chunk.lastIndexOf('\n');
      if (lastNewline < 0) return;
      consumed += lastNewline + 1;
      chunk.slice(0, lastNewline).split('\n').filter(Boolean).forEach((line) => {
        try { applyLiveEvent(item, JSON.parse(line)); } catch (_) { /* wait for valid event */ }
      });
    };
    xhr.onload = () => {
      xhr.onprogress();
      if (xhr.status >= 400 && item.state !== 'error') {
        item.state = 'error';
        item.progress = 100;
        item.message = 'Proses gagal di server';
        render();
      }
      resolve();
    };
    xhr.onerror = () => {
      item.state = 'error';
      item.progress = 100;
      item.message = 'Koneksi ke server terputus';
      render();
      showToast(`Gagal: ${item.file.name}`, item.message);
      resolve();
    };
    xhr.send(data);
  });
}

function applyLiveEvent(item, event) {
  if (event.type === 'complete') {
    item.state = 'done';
    item.progress = 100;
    item.message = `Selesai · ${event.summary?.translated || 0} teks diterjemahkan`;
    item.downloadUrl = event.download_url;
    item.downloadName = event.download_name;
    render();
    downloadResult(item);
    return;
  }
  if (event.type === 'error') {
    item.state = 'error';
    item.progress = 100;
    item.message = event.error_id ? `${event.error} (ID: ${event.error_id})` : event.error;
    render();
    showToast(`Gagal: ${item.file.name}`, item.message);
    return;
  }

  let percent = event.percent || item.progress;
  let message = 'Sedang memproses';
  if (event.stage === 'translation_batches_started') {
    percent = 18;
    message = `${event.batches} batch siap · ${event.parallel_workers} worker`;
  } else if (event.stage === 'translation_batches_progress') {
    percent = 18 + Math.round((event.completed / Math.max(1, event.total)) * 70);
    message = `Menerjemahkan batch ${event.completed}/${event.total}`;
  } else if (event.stage === 'translation_batch_retry') {
    message = `Koneksi dicoba ulang (${event.attempt}/${event.maximum_attempts})`;
  } else if (event.stage === 'docx_part_started') {
    message = `Memproses bagian dokumen ${event.current}/${event.total}`;
  } else if (event.stage === 'docx_part_completed') {
    percent = 88 + Math.round((event.current / Math.max(1, event.total)) * 7);
    message = `Bagian dokumen ${event.current}/${event.total} selesai`;
  } else if (event.stage === 'docx_output_started') {
    percent = 96;
    message = 'Menyusun file hasil';
  } else if (event.stage === 'docx_output_completed') {
    percent = 99;
    message = 'Menyiapkan unduhan';
  } else if (event.stage === 'document_received') {
    message = 'Dokumen diterima server';
  }
  item.state = 'processing';
  item.progress = Math.max(item.progress, Math.min(99, percent));
  item.message = message;
  render();
}

function downloadResult(item) {
  const link = document.createElement('a');
  link.href = item.downloadUrl;
  link.download = item.downloadName;
  document.body.appendChild(link);
  link.click();
  link.remove();
}

async function readError(blob) {
  try {
    const data = JSON.parse(await blob.text());
    const message = data.error || Object.values(data.errors || {}).flat()[0] || 'Proses gagal';
    return data.error_id ? `${message} (ID: ${data.error_id})` : message;
  } catch (_) {
    return 'Proses gagal di server';
  }
}

function prepareNotifications() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (AudioContextClass) {
    audioContext ||= new AudioContextClass();
    audioContext.resume();
  }
  if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
}

function notifyFinished(completed, failed) {
  playDoneSound();
  const message = failed ? `${completed} file selesai, ${failed} gagal.` : `${completed} file berhasil diterjemahkan.`;
  showToast('Antrean selesai', message);
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('Terjemahan selesai', { body: message });
  }
}

function playDoneSound() {
  if (!audioContext) return;
  [0, 0.16].forEach((delay, index) => {
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.frequency.value = index ? 880 : 660;
    gain.gain.setValueAtTime(0.12, audioContext.currentTime + delay);
    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + delay + 0.25);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start(audioContext.currentTime + delay);
    oscillator.stop(audioContext.currentTime + delay + 0.25);
  });
}

function showToast(title, message) {
  const toast = document.getElementById('toast');
  document.getElementById('toastTitle').textContent = title;
  document.getElementById('toastMessage').textContent = message;
  toast.classList.remove('hidden');
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => toast.classList.add('hidden'), 5000);
}

function releaseDownload(item) { if (item.downloadUrl) URL.revokeObjectURL(item.downloadUrl); }
function stripExtension(name) { return name.replace(/\.(docx|pdf)$/i, ''); }
function getFilename(header = '') {
  const utf = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf) return decodeURIComponent(utf[1]);
  return header.match(/filename="?([^";]+)"?/i)?.[1];
}
function formatBytes(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
