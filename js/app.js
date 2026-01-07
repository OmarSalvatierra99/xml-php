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
    const toggle = document.getElementById('themeToggle');

    // Aplicar tema guardado
    const savedTheme = localStorage.getItem(this.storageKey);
    if (savedTheme) {
      body.setAttribute('data-theme', savedTheme);
    }

    // Event listener para toggle
    if (toggle) {
      toggle.addEventListener('click', () => this.toggle());
    }
  },

  toggle() {
    const body = document.body;
    const current = body.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';

    body.setAttribute('data-theme', newTheme);
    localStorage.setItem(this.storageKey, newTheme);
  },

  setTheme(theme) {
    if (theme !== 'light' && theme !== 'dark') return;

    document.body.setAttribute('data-theme', theme);
    localStorage.setItem(this.storageKey, theme);
  },

  getCurrentTheme() {
    return document.body.getAttribute('data-theme') || 'light';
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

    if (messageEl) messageEl.textContent = message;
    if (subtextEl) subtextEl.textContent = subtext;

    this.overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
  },

  hide() {
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
  }
};


// =============================================================================
// FILE UPLOADER
// =============================================================================
const FileUploader = {
  inputId: 'archivos_xml',
  maxSize: 8 * 1024 * 1024, // 8MB
  allowedExtensions: ['xml'],

  init(inputId = 'archivos_xml') {
    this.inputId = inputId;
    const input = document.getElementById(this.inputId);

    if (!input) return;

    // Event listeners
    input.addEventListener('change', (e) => this.handleFileSelect(e));

    // Drag and drop
    this.enableDragDrop(input);
  },

  enableDragDrop(input) {
    const events = ['dragenter', 'dragover', 'dragleave', 'drop'];

    events.forEach(eventName => {
      input.addEventListener(eventName, this.preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      input.addEventListener(eventName, () => {
        input.classList.add('drag-active');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      input.addEventListener(eventName, () => {
        input.classList.remove('drag-active');
      });
    });

    input.addEventListener('drop', (e) => this.handleDrop(e));
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
      return;
    }

    // Validar archivos
    const validation = this.validateFiles(files);

    if (!validation.valid) {
      Toast.error(validation.errors.join('<br>'));
      e.target.value = ''; // Clear input
      this.clearPreview();
      return;
    }

    // Mostrar preview
    this.showPreview(files);
  },

  validateFiles(files) {
    const errors = [];

    for (let i = 0; i < files.length; i++) {
      const file = files[i];

      // Validar extensión
      const ext = file.name.split('.').pop().toLowerCase();
      if (!this.allowedExtensions.includes(ext)) {
        errors.push(`${file.name}: Solo se permiten archivos .xml`);
      }

      // Validar tamaño
      if (file.size > this.maxSize) {
        const sizeMB = (this.maxSize / 1024 / 1024).toFixed(0);
        errors.push(`${file.name}: Excede el límite de ${sizeMB}MB`);
      }

      // Validar que no esté vacío
      if (file.size === 0) {
        errors.push(`${file.name}: Archivo vacío`);
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors
    };
  },

  showPreview(files) {
    // Crear contenedor si no existe
    let container = document.getElementById('filePreviewList');

    if (!container) {
      container = document.createElement('div');
      container.id = 'filePreviewList';
      container.className = 'file-preview-list';

      const input = document.getElementById(this.inputId);
      input.parentNode.insertBefore(container, input.nextSibling);
    }

    // Limpiar contenido previo
    container.innerHTML = '';
    container.style.display = 'block';

    // Crear lista de archivos
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      const item = this.createPreviewItem(file, i);
      container.appendChild(item);
    }
  },

  createPreviewItem(file, index) {
    const item = document.createElement('div');
    item.className = 'file-preview-item';

    const sizeKB = (file.size / 1024).toFixed(1);
    const sizeText = sizeKB > 1024
      ? `${(sizeKB / 1024).toFixed(1)} MB`
      : `${sizeKB} KB`;

    item.innerHTML = `
      <i class="fas fa-file-code file-preview-icon"></i>
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
    } else {
      this.showPreview(input.files);
    }
  },

  clearPreview() {
    const container = document.getElementById('filePreviewList');
    if (container) {
      container.style.display = 'none';
      container.innerHTML = '';
    }
  },

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
      }

      // Mostrar loading overlay
      LoadingManager.show('Procesando archivos...', 'Esto puede tomar unos momentos');
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
