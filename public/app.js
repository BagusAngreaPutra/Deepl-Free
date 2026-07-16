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

let items = [];
let running = false;
let audioContext;

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
  for (const file of incoming) {
    const isDocx = file.name.toLowerCase().endsWith('.docx');
    const isWithinLimit = file.size <= 25 * 1024 * 1024;
    const duplicate = items.some((item) => item.file.name === file.name && item.file.size === file.size);
    if (!isDocx || !isWithinLimit || duplicate) {
      rejected += 1;
      continue;
    }
    items.push({ id: crypto.randomUUID(), file, state: 'waiting', progress: 0, message: 'Menunggu' });
  }
  fileInput.value = '';
  render();
  if (rejected) showToast('Sebagian file tidak ditambahkan', `${rejected} file duplikat, bukan DOCX, atau melebihi 25 MB.`);
}

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
    <div class="file-badge">DOCX</div>
    <div class="file-main">
      <div class="file-meta"><strong></strong><span></span></div>
      <div class="progress-track"><span></span></div>
      <small></small>
    </div>
    <div class="file-actions"></div>`;
  row.querySelector('.file-meta strong').textContent = item.file.name;
  row.querySelector('.file-meta span').textContent = formatBytes(item.file.size);
  row.querySelector('.progress-track span').style.width = `${item.progress}%`;
  row.querySelector('small').textContent = `${item.progress}% · ${item.message}`;
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
  data.append('docx_file', item.file);
  data.append('source_language', sourceLanguage.value);
  data.append('target_language', targetLanguage.value);

  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/translate');
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
        item.downloadName = getFilename(xhr.getResponseHeader('Content-Disposition')) || `${stripExtension(item.file.name)} ${targetLanguage.value}.docx`;
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
function stripExtension(name) { return name.replace(/\.docx$/i, ''); }
function getFilename(header = '') {
  const utf = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf) return decodeURIComponent(utf[1]);
  return header.match(/filename="?([^";]+)"?/i)?.[1];
}
function formatBytes(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
