<?php
include '../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['downloads'])) {
    $_SESSION['downloads'] = [];
}
ob_start();

$uploadBase = __DIR__ . '/../uploads/xml/';
$uploadDir = realpath($uploadBase);
if (!$uploadDir) {
    @mkdir($uploadBase, 0777, true);
    $uploadDir = realpath($uploadBase) ?: $uploadBase;
}

$maxUploadSize = 8 * 1024 * 1024; // 8MB por archivo
$maxZipSize = 50 * 1024 * 1024; // 50MB para ZIP con muchos XML
$maxFileUploads = (int) ini_get('max_file_uploads');
$maxUploadsText = $maxFileUploads > 0 ? $maxFileUploads . ' archivos' : 'sin límite definido';
$allowedExtensions = ['xml', 'zip'];

$uploadedFiles = [];
$invalidFiles = [];
$error = '';
$warnings = [];
$infoMessage = '';
$downloadLink = '';
$autoDownload = false;
$classificationStats = [];

function log_technical($message) {
    error_log("[CLASIFICADOR] " . $message);
}

function cleanup_temp_dir($path) {
    if (is_dir($path)) {
        foreach (glob($path . '/*') as $file) {
            if (is_dir($file)) {
                cleanup_temp_dir($file); // Recursively clean subdirectories
            } else {
                @unlink($file);
            }
        }
        @rmdir($path);
    }
}

function sanitize_filename($filename) {
    $base = basename($filename);
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
    $clean = trim($clean, '._-');
    return $clean !== '' ? $clean : 'archivo.xml';
}

function unique_destination($dir, $filename) {
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($path)) {
        return $path;
    }
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    for ($i = 1; $i <= 999; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . $name . '_' . $i . ($ext ? '.' . $ext : '');
        if (!file_exists($candidate)) {
            return $candidate;
        }
    }
    return $path;
}

function extract_xml_from_zip($zipPath, $destDir, &$warnings, &$invalidFiles) {
    $extracted = [];
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $invalidFiles[basename($zipPath)] = "No se pudo abrir el archivo ZIP.";
        return $extracted;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (!$entryName || substr($entryName, -1) === '/') {
            continue;
        }
        if (!preg_match('~\\.xml$~i', $entryName)) {
            continue;
        }

        $baseName = sanitize_filename(basename($entryName));
        if ($baseName === '') {
            $warnings[] = "Se omitió un XML con nombre inválido dentro del ZIP.";
            continue;
        }

        $targetPath = unique_destination($destDir, $baseName);
        $stream = $zip->getStream($entryName);
        if (!$stream) {
            $warnings[] = "No se pudo extraer {$baseName} del ZIP.";
            continue;
        }

        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            $warnings[] = "No se pudo leer {$baseName} del ZIP.";
            continue;
        }

        if (file_put_contents($targetPath, $contents) === false) {
            $warnings[] = "No se pudo guardar {$baseName} extraído del ZIP.";
            continue;
        }

        $extracted[] = $targetPath;
    }

    $zip->close();

    if (empty($extracted)) {
        $warnings[] = "El archivo ZIP no contiene XML válidos.";
    }

    return $extracted;
}

function run_python_script($pythonExec, $scriptPath, $workdir) {
    $cmd = escapeshellarg($pythonExec) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($workdir);
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptorspec, $pipes, null, null);
    if (!is_resource($process)) {
        return [ '', 'No se pudo iniciar el proceso de Python.', 2 ];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    return [ $stdout, $stderr, $status ];
}

function extract_zip_path($stdout) {
    $trimmed = trim($stdout);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        if (!empty($decoded['path']) && preg_match('~\\.zip$~i', $decoded['path'])) {
            return $decoded['path'];
        }
        if (!empty($decoded['file']) && preg_match('~\\.zip$~i', $decoded['file'])) {
            return $decoded['file'];
        }
        if (!empty($decoded['stats'])) {
            return [$decoded['path'] ?? $decoded['file'] ?? null, $decoded['stats']];
        }
    }

    if (preg_match_all('~(/[^\s]+\\.zip)~i', $stdout, $matches) && !empty($matches[1])) {
        return trim(end($matches[1]));
    }

    $lines = array_filter(array_map('trim', explode("\n", $stdout)));
    $lastLine = end($lines);
    if ($lastLine && preg_match('~\\.zip$~i', $lastLine)) {
        return $lastLine;
    }

    return null;
}

function parse_warnings($stderr) {
    $warnings = [];
    foreach (explode("\n", $stderr) as $line) {
        if (stripos($line, 'WARNING') !== false) {
            $warnings[] = trim($line);
        }
    }
    return $warnings;
}

function parse_ini_size($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    $last = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($last) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
    }
    return (int) round($number);
}

$postMaxSizeIni = ini_get('post_max_size');
$postMaxBytes = parse_ini_size($postMaxSizeIni);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && !$error) {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $error = "La carga completa excede el límite permitido por el servidor ({$postMaxSizeIni}). Reduce el número de archivos o ajusta post_max_size y upload_max_filesize en PHP.";
    } else {
        $error = "No se recibió ningún archivo. Verifica que los XML sean válidos y que no se exceda el límite total de carga.";
    }
}

if (isset($_GET['download']) && isset($_SESSION['downloads'][$_GET['download']])) {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['download']);
    $entry = $_SESSION['downloads'][$token] ?? null;
    if ($entry) {
        $realPath = realpath($entry['path']);
        if ($realPath && strpos($realPath, $uploadDir) === 0 && file_exists($realPath)) {
            ob_clean();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="XML_Clasificados.zip"');
            header('Content-Length: ' . filesize($realPath));
            readfile($realPath);
        } else {
            log_technical("Descarga denegada o archivo no encontrado: {$entry['path']}");
        }
        cleanup_temp_dir($entry['tempDir']);
        unset($_SESSION['downloads'][$token]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivos_xml'])) {
    if (!is_dir($uploadDir)) {
        $error = "No se pudo preparar el espacio de carga. Verifica la carpeta uploads.";
    } elseif (!is_writable($uploadDir)) {
        $error = "No se pudo guardar la carga. Verifica permisos en uploads.";
    }
    $tempDir = $uploadDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(10));
    if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true)) {
        $error = "No se pudo preparar el espacio de carga. Intenta de nuevo.";
    }
    if (!$error && (!is_dir($tempDir) || !is_writable($tempDir))) {
        $error = "No se pudo guardar la carga. Intenta de nuevo.";
    }

    $totalFiles = isset($_FILES['archivos_xml']['name']) ? count($_FILES['archivos_xml']['name']) : 0;
    $hasZip = false;
    foreach ((array) ($_FILES['archivos_xml']['name'] ?? []) as $name) {
        if (strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)) === 'zip') {
            $hasZip = true;
            break;
        }
    }
    if ($maxFileUploads > 0 && $totalFiles > $maxFileUploads) {
        $warnings[] = "Se seleccionaron {$totalFiles} archivos, pero el servidor permite máximo {$maxFileUploads} por carga. Los excedentes serán omitidos.";
    }
    if ($totalFiles > 500 && !$hasZip) {
        $error = "Si necesitas cargar más de 500 XML, súbelos en un archivo ZIP para que el sistema los descomprima automáticamente.";
    }

    if ($error) {
        cleanup_temp_dir($tempDir);
    }

    $finfo = null;
    if (!$error) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
    }
    foreach ($_FILES['archivos_xml']['tmp_name'] as $key => $tmp) {
        if ($error) {
            break;
        }
        $filename = basename($_FILES['archivos_xml']['name'][$key]);
        $fileError = $_FILES['archivos_xml']['error'][$key];
        $fileSize = $_FILES['archivos_xml']['size'][$key];

        if ($fileError !== UPLOAD_ERR_OK || $fileSize <= 0) {
            $invalidFiles[$filename] = "Archivo vacío o no válido.";
            continue;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $sizeLimit = $ext === 'zip' ? $maxZipSize : $maxUploadSize;
        if ($fileSize > $sizeLimit) {
            $limitText = $ext === 'zip' ? '50MB' : '8MB';
            $invalidFiles[$filename] = "El archivo excede el límite de {$limitText}.";
            continue;
        }
        if (!in_array($ext, $allowedExtensions, true)) {
            $invalidFiles[$filename] = "Solo se permiten archivos .xml o .zip.";
            continue;
        }

        $mime = $finfo ? finfo_file($finfo, $tmp) : '';
        if ($ext === 'zip') {
            if ($mime && stripos($mime, 'zip') === false && stripos($mime, 'compressed') === false) {
                $invalidFiles[$filename] = "Tipo de archivo ZIP no permitido.";
                continue;
            }
        } elseif ($mime && stripos($mime, 'xml') === false && stripos($mime, 'text') === false) {
            $warnings[] = "Se aceptó {$filename} por extensión, aunque el tipo reportado no es XML.";
        }

        if (!is_uploaded_file($tmp)) {
            $invalidFiles[$filename] = "El archivo no se recibió correctamente. Intenta de nuevo.";
            continue;
        }

        $safeName = sanitize_filename($filename);
        $targetPath = unique_destination($tempDir, $safeName);
        if (move_uploaded_file($tmp, $targetPath)) {
            if ($ext === 'zip') {
                $extracted = extract_xml_from_zip($targetPath, $tempDir, $warnings, $invalidFiles);
                $uploadedFiles = array_merge($uploadedFiles, $extracted);
                @unlink($targetPath);
            } else {
                $uploadedFiles[] = $targetPath;
            }
        } else {
            $lastError = error_get_last();
            log_technical("Error moviendo {$filename}: " . ($lastError['message'] ?? 'sin detalle'));
            $invalidFiles[$filename] = "No se pudo guardar el archivo. Intenta de nuevo.";
        }
    }
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!empty($uploadedFiles)) {
        $scriptPath = realpath(__DIR__ . '/../scripts/clasificador_xml.py');
        $pythonExec = defined('PYTHON_PATH') && !empty(PYTHON_PATH) ? PYTHON_PATH : ($python_path ?? 'python3');
        [$stdout, $stderr, $returnVar] = run_python_script($pythonExec, $scriptPath, $tempDir);

        $result = extract_zip_path($stdout);
        if (is_array($result)) {
            [$zipPath, $classificationStats] = $result;
        } else {
            $zipPath = $result;
        }
        $warnings = parse_warnings($stderr);

        log_technical("Resultado Clasificador | exit={$returnVar} | path=" . ($zipPath ?: 'N/A') . " | stderr=" . trim($stderr));

        if ($returnVar === 0 && $zipPath && file_exists($zipPath)) {
            $token = bin2hex(random_bytes(8));
            $_SESSION['downloads'][$token] = ['path' => $zipPath, 'tempDir' => $tempDir];
            $downloadLink = '?download=' . $token;
            $autoDownload = false; // No auto-download for classification
            $infoMessage = $warnings ? "Clasificación completada con advertencias." : "Clasificación completada correctamente.";
        } elseif ($returnVar === 1) {
            $error = "Ocurrió un problema al clasificar los XML. Vuelve a intentarlo y revisa los mensajes.";
            cleanup_temp_dir($tempDir);
        } else {
            $error = "No se pudo clasificar los archivos XML.";
            cleanup_temp_dir($tempDir);
        }
    } else {
        $error = "No se subieron archivos válidos.";
        cleanup_temp_dir($tempDir);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <script>
      (function() {
        try {
          var savedTheme = localStorage.getItem('ofs-theme');
          if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
          }
        } catch (e) {}
      })();
    </script>
    <title>Clasificar XML | OFS Tlaxcala</title>
    <link rel="stylesheet" href="../css/style.css?v=4">
</head>
<body data-theme="light">
<header class="dashboard-header" role="banner">
    <div class="ofs-logo">
        <span class="logo-text">OFS</span>
        <span class="logo-subtext">Tlaxcala</span>
    </div>
    <div class="user-info" aria-label="Información del usuario">
        <img src="../img/user-avatar.png" alt="Avatar" class="avatar">
        <div>
            <h1>Clasificar archivos XML</h1>
            <p><?php echo ucwords(str_replace('_', ' ', $role)); ?> | OFS Tlaxcala</p>
        </div>
    </div>
    <nav class="header-actions" role="navigation" aria-label="Acciones principales">
        <button type="button" class="btn-toggle theme-toggle" id="themeToggle" aria-label="Cambiar tema de color">
            <span class="theme-icon" aria-hidden="true"><i class="fas fa-adjust"></i></span>
            <span class="theme-label">Tema</span>
            <span class="theme-state theme-state-light">Claro</span>
            <span class="theme-state theme-state-dark">Oscuro</span>
        </button>
        <a href="../index.php" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver</a>
    </nav>
</header>

<nav class="primary-nav" role="navigation" aria-label="Navegación principal">
    <div class="nav-title">Accesos rápidos</div>
    <div class="nav-links">
        <a class="nav-link" href="../index.php"><i class="fas fa-home" aria-hidden="true"></i> Inicio</a>
        <a class="nav-link" href="extraer_xml.php"><i class="fas fa-file-code" aria-hidden="true"></i> Extraer XML</a>
        <a class="nav-link" href="extraer_nomina.php"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Extraer Nómina</a>
        <a class="nav-link is-active" href="clasificar_xml.php"><i class="fas fa-folder-tree" aria-hidden="true"></i> Clasificar</a>
        <a class="nav-link" href="validar_xml.php"><i class="fas fa-check-circle" aria-hidden="true"></i> Validar</a>
    </div>
</nav>

<main class="dashboard-main">
<section class="tool-section">
<h2><i class="fas fa-folder-tree"></i> Clasificar XML por Tipo</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
<?php endif; ?>

<?php if ($infoMessage): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $infoMessage; ?></div>
<?php endif; ?>

<?php if (!empty($classificationStats)): ?>
<div class="alert alert-info">
    <i class="fas fa-chart-bar"></i>
    <div>
        <strong>Resultados de la clasificación:</strong>
        <ul>
            <?php if (!empty($classificationStats['nomina'])): ?>
                <li><strong>Nómina:</strong> <?php echo $classificationStats['nomina']; ?> archivo(s)</li>
            <?php endif; ?>
            <?php if (!empty($classificationStats['gasto'])): ?>
                <li><strong>Gasto:</strong> <?php echo $classificationStats['gasto']; ?> archivo(s)</li>
            <?php endif; ?>
            <?php if (!empty($classificationStats['vacios'])): ?>
                <li><strong>Vacíos/No reconocidos:</strong> <?php echo $classificationStats['vacios']; ?> archivo(s)</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Advertencias detectadas:</strong>
        <ul>
            <?php foreach ($warnings as $warn): ?>
                <li><?php echo htmlspecialchars($warn); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($invalidFiles)): ?>
<div class="alert alert-warning">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>Archivos omitidos:</strong>
        <ul>
            <?php foreach ($invalidFiles as $file => $reason): ?>
                <li><?php echo htmlspecialchars($file); ?> - <?php echo htmlspecialchars($reason); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="tool-instructions">
<p>Suba uno o varios archivos XML para clasificarlos automáticamente por tipo: Nómina, Gasto o Vacíos.</p>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="form-group">
  <div class="file-upload">
    <input type="file" id="archivos_xml" name="archivos_xml[]" multiple accept=".xml,.XML,.zip,.ZIP" required data-max-files="<?php echo (int) $maxFileUploads; ?>" data-max-size="<?php echo (int) $maxUploadSize; ?>" data-max-zip-size="<?php echo (int) $maxZipSize; ?>">
    <label for="archivos_xml" class="file-upload-label">
      <span class="file-upload-icon"><i class="fas fa-file-upload" aria-hidden="true"></i></span>
      <span class="file-upload-title">Arrastra y suelta tus XML</span>
      <span class="file-upload-subtitle">o selecciona múltiples archivos o un ZIP</span>
    </label>
    <div class="file-upload-meta">
      <div class="file-upload-stats">
        <span id="fileCount">0 archivos</span>
        <span id="fileTotalSize">0 MB</span>
      </div>
      <button type="button" class="file-upload-clear" id="clearFiles">Limpiar selección</button>
    </div>
  </div>
  <div class="form-note file-upload-hint">Para más de 500 XML, comprime los archivos en un ZIP.</div>
</div>
<button type="submit" class="btn-process"><i class="fas fa-cogs"></i> Clasificar Archivos</button>
</form>

<?php if ($downloadLink): ?>
<div class="tool-output">
    <p>Los archivos han sido clasificados y están listos para descargar.</p>
    <div class="tool-output-actions">
      <a href="<?php echo $downloadLink; ?>" class="btn-download"><i class="fas fa-download"></i> Descargar ZIP</a>
      <a href="clasificar_xml.php" class="btn-secondary"><i class="fas fa-redo"></i> Subir más archivos</a>
    </div>
</div>
<?php endif; ?>
</section>
</main>

<footer class="dashboard-footer" role="contentinfo">
  © <?php echo date('Y'); ?> Órgano de Fiscalización Superior del Estado de Tlaxcala
</footer>

<script src="../js/app.js?v=2"></script>

</body>
</html>
