/**
 * OFS Tlaxcala - Herramientas
 * Módulos JavaScript centralizados para manejo de tema, notificaciones,
 * carga de archivos y estados de carga
 */

// =============================================================================
// THEME MANAGER
// =============================================================================
const ThemeManager = {
  storageKey: 'ofs-theme',

  init() {
    const body = document.body;
    const root = document.documentElement;
    const toggle = document.getElementById('themeToggle');

    // Aplicar tema guardado
    const savedTheme = localStorage.getItem(this.storageKey);
    const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const initialTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
    body.setAttribute('data-theme', initialTheme);
    root.setAttribute('data-theme', initialTheme);
    this.syncToggle(initialTheme);

    // Event listener para toggle
    if (toggle) {
      toggle.addEventListener('click', () => this.toggle());
    }
  },

  toggle() {
    const body = document.body;
    const current = body.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';

    this.applyTheme(newTheme);
  },

  setTheme(theme) {
    if (theme !== 'light' && theme !== 'dark') return;

    this.applyTheme(theme);
  },

  getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || document.body.getAttribute('data-theme') || 'light';
  },

  applyTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(this.storageKey, theme);
    this.syncToggle(theme);
  },

  syncToggle(theme) {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    const isDark = theme === 'dark';
    toggle.classList.toggle('is-dark', isDark);
    toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
  }
};


// =============================================================================
// TOAST NOTIFICATIONS
// =============================================================================
const Toast = {
  container: null,

  init() {
    // Crear contenedor si no existe
    if (!document.getElementById('toastContainer')) {
      const container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    this.container = document.getElementById('toastContainer');
  },

  show(message, type = 'info', duration = 5000) {
    if (!this.container) this.init();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
      success: 'fa-check-circle',
      error: 'fa-exclamation-circle',
      warning: 'fa-exclamation-triangle',
      info: 'fa-info-circle'
    };

    toast.innerHTML = `
      <i class="fas ${icons[type]} toast-icon"></i>
      <div class="toast-content">
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" aria-label="Cerrar" type="button">
        <i class="fas fa-times"></i>
      </button>
    `;

    // Event listener para cerrar
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
      this.remove(toast);
    });

    // Agregar al contenedor
    this.container.appendChild(toast);

    // Auto-remover después del duration
    if (duration > 0) {
      setTimeout(() => {
        this.remove(toast);
      }, duration);
    }

    return toast;
  },

  remove(toast) {
    toast.style.animation = 'fadeOut 0.3s ease';
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  },

  success(message, duration) {
    return this.show(message, 'success', duration);
  },

  error(message, duration) {
    return this.show(message, 'error', duration);
  },

  warning(message, duration) {
    return this.show(message, 'warning', duration);
  },

  info(message, duration) {
    return this.show(message, 'info', duration);
  }
};


// =============================================================================
// LOADING MANAGER
// =============================================================================
const LoadingManager = {
  overlay: null,

  init() {
    // Crear overlay si no existe
    if (!document.getElementById('loadingOverlay')) {
      const overlay = document.createElement('div');
      overlay.id = 'loadingOverlay';
      overlay.className = 'loading-overlay';
      overlay.style.display = 'none';

      overlay.innerHTML = `
        <div class="loading-content">
          <div class="spinner"></div>
          <h3 id="loadingMessage">Procesando...</h3>
          <p id="loadingSubtext">Esto puede tomar unos momentos</p>
          <div class="loading-progress">
            <div class="loading-progress-bar" id="loadingProgressBar"></div>
          </div>
          <div class="loading-progress-text" id="loadingProgressText">0%</div>
        </div>
      `;

      document.body.appendChild(overlay);
    }
    this.overlay = document.getElementById('loadingOverlay');
  },

  show(message = 'Procesando...', subtext = 'Esto puede tomar unos momentos') {
    if (!this.overlay) this.init();

    const messageEl = document.getElementById('loadingMessage');
    const subtextEl = document.getElementById('loadingSubtext');
    const progressText = document.getElementById('loadingProgressText');
    const progressBar = document.getElementById('loadingProgressBar');

    if (messageEl) messageEl.textContent = message;
    if (subtextEl) subtextEl.textContent = subtext;
    if (progressText) progressText.textContent = '0%';
    if (progressBar) progressBar.style.width = '0%';

    this.overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
  },

  hide() {
    if (this.progressTimer) {
      clearInterval(this.progressTimer);
      this.progressTimer = null;
    }
    if (this.overlay) {
      this.overlay.style.display = 'none';
      document.body.style.overflow = ''; // Restore scrolling
    }
  },

  updateMessage(message, subtext) {
    const messageEl = document.getElementById('loadingMessage');
    const subtextEl = document.getElementById('loadingSubtext');

    if (messageEl && message) messageEl.textContent = message;
    if (subtextEl && subtext) subtextEl.textContent = subtext;
  },

  startProgress(totalFiles = 0) {
    const progressText = document.getElementById('loadingProgressText');
    const progressBar = document.getElementById('loadingProgressBar');
    if (!progressText || !progressBar) return;

    if (this.progressTimer) {
      clearInterval(this.progressTimer);
    }

    const baseSeconds = Math.max(6, Math.min(60, Math.round((totalFiles || 1) * 0.4)));
    const start = Date.now();
    const target = baseSeconds * 1000;
    const maxFast = 92;
    let lastPercent = 0;

    this.progressTimer = setInterval(() => {
      const elapsed = Date.now() - start;
      let percent = Math.min(maxFast, Math.round((elapsed / target) * maxFast));

      if (percent >= maxFast) {
        percent = Math.min(98, lastPercent + 1);
      }

      if (percent !== lastPercent) {
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}%`;
        lastPercent = percent;
      }
    }, 500);
  }
};


// =============================================================================
// FILE UPLOADER
// =============================================================================
const FileUploader = {
  inputId: 'archivos_xml',
  maxSize: 8 * 1024 * 1024, // 8MB
  maxZipSize: 50 * 1024 * 1024, // 50MB
  allowedExtensions: ['xml'],
  maxPreviewItems: 8,
  wrapper: null,
  clearButton: null,
  label: null,
  labelTitle: null,
  labelSubtitle: null,
  progressBar: null,
  progressLabel: null,

  init(inputId = 'archivos_xml') {
    this.inputId = inputId;
    const input = document.getElementById(this.inputId);

    if (!input) return;

    this.wrapper = input.closest('.file-upload') || input.parentNode;
    this.clearButton = this.wrapper ? this.wrapper.querySelector('.file-upload-clear') : null;
    this.label = this.wrapper ? this.wrapper.querySelector('.file-upload-label') : null;
    this.labelTitle = this.wrapper ? this.wrapper.querySelector('.file-upload-title') : null;
    this.labelSubtitle = this.wrapper ? this.wrapper.querySelector('.file-upload-subtitle') : null;

    if (input.dataset.maxSize) {
      const maxSizeParsed = parseInt(input.dataset.maxSize, 10);
      if (!Number.isNaN(maxSizeParsed) && maxSizeParsed > 0) {
        this.maxSize = maxSizeParsed;
      }
    }

    if (input.dataset.maxZipSize) {
      const maxZipSizeParsed = parseInt(input.dataset.maxZipSize, 10);
      if (!Number.isNaN(maxZipSizeParsed) && maxZipSizeParsed > 0) {
        this.maxZipSize = maxZipSizeParsed;
      }
    }

    if (this.label && this.labelTitle && this.labelSubtitle) {
      this.label.dataset.defaultTitle = this.labelTitle.textContent.trim();
      this.label.dataset.defaultSubtitle = this.labelSubtitle.textContent.trim();
    }

    if (this.clearButton) {
      this.clearButton.addEventListener('click', () => this.clearSelection());
    }

    // Event listeners
    input.addEventListener('change', (e) => this.handleFileSelect(e));

    // Drag and drop
    this.enableDragDrop(input);

    // Initialize summary
    this.ensureProgressElements();
    this.updateSummary(input.files);
  },

  enableDragDrop(input) {
    const dropzone = this.wrapper || input;
    const events = ['dragenter', 'dragover', 'dragleave', 'drop'];

    events.forEach(eventName => {
      dropzone.addEventListener(eventName, this.preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.classList.add('drag-active');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.classList.remove('drag-active');
      });
    });

    dropzone.addEventListener('drop', (e) => this.handleDrop(e));
  },

  preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  },

  handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    const input = document.getElementById(this.inputId);
    input.files = files;

    // Trigger change event
    const event = new Event('change', { bubbles: true });
    input.dispatchEvent(event);
  },

  handleFileSelect(e) {
    const files = e.target.files;

    if (files.length === 0) {
      this.clearPreview();
      this.updateSummary(files);
      this.setLabelState(false);
      return;
    }

    // Validar archivos
    const validation = this.validateFiles(files);

    if (!validation.valid) {
      Toast.error(validation.errors.join('<br>'));
      e.target.value = ''; // Clear input
      this.clearPreview();
      this.updateSummary([]);
      this.setLabelState(false);
      return;
    }

    if (validation.warnings.length) {
      Toast.warning(validation.warnings.join('<br>'));
    }

    // Mostrar preview
    this.showPreview(files);
    this.updateSummary(files);
    this.setLabelState(true);
  },

  validateFiles(files) {
    const errors = [];
    const warnings = [];
    const maxFiles = this.getMaxFiles();
    const hasZip = Array.from(files).some((file) => {
      const ext = file.name.split('.').pop().toLowerCase();
      return ext === 'zip';
    });

    if (maxFiles > 0 && files.length > maxFiles) {
      warnings.push(`Seleccionaste ${files.length} archivos, pero el servidor permite máximo ${maxFiles} por carga. Divide en lotes para evitar omisiones.`);
    }

    if (files.length > 500 && !hasZip) {
      errors.push('Si necesitas cargar más de 500 XML, súbelos en un archivo ZIP para que el sistema los descomprima automáticamente.');
    }

    for (let i = 0; i < files.length; i++) {
      const file = files[i];

      // Validar extensión
      const ext = file.name.split('.').pop().toLowerCase();
      if (!this.allowedExtensions.includes(ext)) {
        errors.push(`${file.name}: Solo se permiten archivos .xml o .zip`);
      }

      // Validar tamaño
      const sizeLimit = ext === 'zip' ? this.maxZipSize : this.maxSize;
      if (file.size > sizeLimit) {
        const sizeMB = (sizeLimit / 1024 / 1024).toFixed(0);
        errors.push(`${file.name}: Excede el límite de ${sizeMB}MB`);
      }

      // Validar que no esté vacío
      if (file.size === 0) {
        errors.push(`${file.name}: Archivo vacío`);
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors,
      warnings: warnings
    };
  },

  getMaxFiles() {
    const input = document.getElementById(this.inputId);
    if (!input) return 0;
    const max = parseInt(input.dataset.maxFiles || '0', 10);
    return Number.isNaN(max) ? 0 : max;
  },

  showPreview(files) {
    // Crear contenedor si no existe
    let container = document.getElementById('filePreviewList');

    if (!container) {
      container = document.createElement('div');
      container.id = 'filePreviewList';
      container.className = 'file-preview-list';

      const input = document.getElementById(this.inputId);
      const anchor = this.wrapper || input.parentNode;
      anchor.parentNode.insertBefore(container, anchor.nextSibling);
    }

    // Limpiar contenido previo
    container.innerHTML = '';
    container.style.display = 'block';
    if (this.wrapper) {
      this.wrapper.classList.add('has-files');
    }

    // Crear lista de archivos
    const limit = Math.min(files.length, this.maxPreviewItems);
    for (let i = 0; i < limit; i++) {
      const file = files[i];
      const item = this.createPreviewItem(file, i);
      container.appendChild(item);
    }

    if (files.length > limit) {
      const more = document.createElement('div');
      more.className = 'file-preview-item file-preview-more';
      more.innerHTML = `
        <i class="fas fa-ellipsis-h file-preview-icon"></i>
        <div class="file-preview-info">
          <div class="file-preview-name">y ${files.length - limit} archivos más</div>
          <div class="file-preview-size">La lista se compacta para mejorar el rendimiento.</div>
        </div>
      `;
      container.appendChild(more);
    }
  },

  createPreviewItem(file, index) {
    const item = document.createElement('div');
    item.className = 'file-preview-item';

    const sizeKB = (file.size / 1024).toFixed(1);
    const sizeText = sizeKB > 1024
      ? `${(sizeKB / 1024).toFixed(1)} MB`
      : `${sizeKB} KB`;
    const ext = file.name.split('.').pop().toLowerCase();
    const icon = ext === 'zip' ? 'fa-file-archive' : 'fa-file-code';

    item.innerHTML = `
      <i class="fas ${icon} file-preview-icon"></i>
      <div class="file-preview-info">
        <div class="file-preview-name">${this.escapeHtml(file.name)}</div>
        <div class="file-preview-size">${sizeText}</div>
      </div>
      <button type="button" class="file-preview-remove" data-index="${index}" aria-label="Eliminar archivo">
        <i class="fas fa-trash"></i>
      </button>
    `;

    // Event listener para eliminar
    const removeBtn = item.querySelector('.file-preview-remove');
    removeBtn.addEventListener('click', () => this.removeFile(index));

    return item;
  },

  removeFile(index) {
    const input = document.getElementById(this.inputId);
    const dt = new DataTransfer();
    const files = Array.from(input.files);

    files.forEach((file, i) => {
      if (i !== index) {
        dt.items.add(file);
      }
    });

    input.files = dt.files;

    // Actualizar preview
    if (input.files.length === 0) {
      this.clearPreview();
      this.setLabelState(false);
    } else {
      this.showPreview(input.files);
      this.setLabelState(true);
    }
    this.updateSummary(input.files);
  },

  clearPreview() {
    const container = document.getElementById('filePreviewList');
    if (container) {
      container.style.display = 'none';
      container.innerHTML = '';
    }
    if (this.wrapper) {
      this.wrapper.classList.remove('has-files');
    }
  },

  updateSummary(files) {
    const countEl = document.getElementById('fileCount');
    const sizeEl = document.getElementById('fileTotalSize');
    if (!countEl || !sizeEl) return;

    const totalSize = Array.from(files || []).reduce((acc, file) => acc + (file.size || 0), 0);
    const count = files ? files.length : 0;

    countEl.textContent = `${count} archivo${count === 1 ? '' : 's'}`;
    sizeEl.textContent = this.formatBytes(totalSize);
    this.updateProgress(totalSize, count);
  },

  formatBytes(bytes) {
    if (!bytes || bytes <= 0) return '0 MB';
    const units = ['B', 'KB', 'MB', 'GB'];
    const idx = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / Math.pow(1024, idx);
    return `${value.toFixed(value < 10 && idx > 0 ? 1 : 0)} ${units[idx]}`;
  },

  clearSelection() {
    const input = document.getElementById(this.inputId);
    if (!input) return;
    input.value = '';
    this.clearPreview();
    this.updateSummary([]);
    this.setLabelState(false);
  },

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  setLabelState(hasFiles) {
    if (!this.labelTitle || !this.labelSubtitle) return;
    if (hasFiles) {
      this.labelTitle.textContent = 'Archivos listos para procesar';
      this.labelSubtitle.textContent = 'Revisa la lista o ajusta tu selección antes de enviar.';
    } else if (this.label && this.label.dataset.defaultTitle) {
      this.labelTitle.textContent = this.label.dataset.defaultTitle;
      this.labelSubtitle.textContent = this.label.dataset.defaultSubtitle || '';
    }
  },

  ensureProgressElements() {
    if (!this.wrapper || this.wrapper.querySelector('.file-upload-progress')) return;
    const progress = document.createElement('div');
    progress.className = 'file-upload-progress';
    progress.innerHTML = '<div class="file-upload-progress-bar"></div>';

    const label = document.createElement('div');
    label.className = 'file-upload-progress-label';
    label.textContent = 'Carga: 0 MB';

    const meta = this.wrapper.querySelector('.file-upload-meta');
    if (meta && meta.parentNode) {
      meta.parentNode.insertBefore(progress, meta.nextSibling);
      progress.insertAdjacentElement('afterend', label);
    } else {
      this.wrapper.appendChild(progress);
      this.wrapper.appendChild(label);
    }

    this.progressBar = progress.querySelector('.file-upload-progress-bar');
    this.progressLabel = label;
  },

  updateProgress(totalSize, count) {
    if (!this.progressBar || !this.progressLabel) return;
    const maxFiles = this.getMaxFiles();
    const effectiveCount = Math.max(count || 1, 1);
    const capacity = maxFiles > 0 ? this.maxSize * maxFiles : this.maxSize * effectiveCount;
    const ratio = capacity > 0 ? Math.min(totalSize / capacity, 1) : 0;
    this.progressBar.style.width = `${Math.round(ratio * 100)}%`;
    const capacityLabel = this.formatBytes(capacity);
    this.progressLabel.textContent = `Carga: ${this.formatBytes(totalSize)} de ${capacityLabel}`;
  }
};


// =============================================================================
// FORM SUBMIT HANDLER
// =============================================================================
function initFormSubmitHandler() {
  const forms = document.querySelectorAll('form[method="POST"]');

  forms.forEach(form => {
    // Skip if form already has handler
    if (form.dataset.hasSubmitHandler) return;
    form.dataset.hasSubmitHandler = 'true';

    form.addEventListener('submit', (e) => {
      // Validar archivos antes de enviar
      const fileInput = form.querySelector('input[type="file"]');

      if (fileInput && fileInput.files.length > 0) {
        const validation = FileUploader.validateFiles(fileInput.files);

        if (!validation.valid) {
          e.preventDefault();
          Toast.error(validation.errors.join('<br>'));
          return false;
        }

        if (validation.warnings.length) {
          Toast.warning(validation.warnings.join('<br>'));
        }
      }

      // Mostrar loading overlay
      LoadingManager.show('Procesando archivos...', 'Esto puede tomar unos momentos');
      const totalFiles = fileInput && fileInput.files ? fileInput.files.length : 0;
      LoadingManager.startProgress(totalFiles);
    });
  });
}


// =============================================================================
// ACCESSIBILITY HELPERS
// =============================================================================
function initAccessibility() {
  // Agregar keyboard navigation a las tool cards
  const toolCards = document.querySelectorAll('.tool-card');

  toolCards.forEach(card => {
    // Hacer focusable si no tiene tabindex
    if (!card.hasAttribute('tabindex')) {
      card.setAttribute('tabindex', '0');
    }

    // Keyboard handler
    card.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        card.click();
      }
    });
  });

  // Skip to content link
  const skipLink = document.querySelector('.skip-link');
  if (skipLink) {
    skipLink.addEventListener('click', (e) => {
      e.preventDefault();
      const mainContent = document.getElementById('mainContent');
      if (mainContent) {
        mainContent.focus();
        mainContent.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }
}


// =============================================================================
// SCROLL TO DOWNLOAD SECTION
// =============================================================================
function scrollToDownload() {
  const downloadSection = document.querySelector('.tool-output');
  if (downloadSection) {
    setTimeout(() => {
      downloadSection.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }, 300);
  }
}


// =============================================================================
// INITIALIZATION
// =============================================================================
document.addEventListener('DOMContentLoaded', () => {
  // Inicializar módulos
  ThemeManager.init();
  Toast.init();
  LoadingManager.init();

  // Inicializar file uploader si existe
  if (document.getElementById('archivos_xml')) {
    FileUploader.allowedExtensions = ['xml', 'zip'];
    FileUploader.init('archivos_xml');
  }

  // Inicializar form handlers
  initFormSubmitHandler();

  // Inicializar accesibilidad
  initAccessibility();

  // Si hay un downloadLink, scroll to it
  if (document.querySelector('.tool-output')) {
    scrollToDownload();
  }
});


// =============================================================================
// EXPORT PARA USO GLOBAL
// =============================================================================
window.ThemeManager = ThemeManager;
window.Toast = Toast;
window.LoadingManager = LoadingManager;
window.FileUploader = FileUploader;
