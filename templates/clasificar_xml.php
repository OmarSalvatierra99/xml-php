<?php
include '../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['downloads'])) {
    $_SESSION['downloads'] = [];
}
ob_start();

$uploadDir = realpath(__DIR__ . '/../uploads/xml/');
if (!$uploadDir) {
    mkdir(__DIR__ . '/../uploads/xml/', 0777, true);
    $uploadDir = realpath(__DIR__ . '/../uploads/xml/');
}

$maxUploadSize = 8 * 1024 * 1024; // 8MB por archivo
$allowedExtensions = ['xml'];

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
    $tempDir = $uploadDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(10));
    mkdir($tempDir, 0777, true);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    foreach ($_FILES['archivos_xml']['tmp_name'] as $key => $tmp) {
        $filename = basename($_FILES['archivos_xml']['name'][$key]);
        $fileError = $_FILES['archivos_xml']['error'][$key];
        $fileSize = $_FILES['archivos_xml']['size'][$key];

        if ($fileError !== UPLOAD_ERR_OK || $fileSize <= 0) {
            $invalidFiles[$filename] = "Archivo vacío o no válido.";
            continue;
        }

        if ($fileSize > $maxUploadSize) {
            $invalidFiles[$filename] = "El archivo excede el límite de 8MB.";
            continue;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $invalidFiles[$filename] = "Solo se permiten archivos .xml.";
            continue;
        }

        $mime = $finfo ? finfo_file($finfo, $tmp) : '';
        if ($mime && stripos($mime, 'xml') === false && stripos($mime, 'text') === false) {
            $invalidFiles[$filename] = "Tipo de archivo no permitido.";
            continue;
        }

        if (move_uploaded_file($tmp, $tempDir . DIRECTORY_SEPARATOR . $filename)) {
            $uploadedFiles[] = $tempDir . DIRECTORY_SEPARATOR . $filename;
        } else {
            $invalidFiles[$filename] = "No se pudo guardar el archivo de forma segura.";
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
    <title>Clasificar XML | OFS Tlaxcala</title>
    <link rel="stylesheet" href="../css/style.css?v=1">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body data-theme="light">
<header class="dashboard-header">
    <div class="user-info">
        <img src="../img/user-avatar.png" alt="Avatar" class="avatar">
        <div>
            <h1>Clasificar archivos XML</h1>
            <p><?php echo ucwords(str_replace('_', ' ', $role)); ?> | OFS Tlaxcala</p>
        </div>
    </div>
    <div class="header-actions">
        <button type="button" class="btn-toggle" id="themeToggle"><i class="fas fa-adjust"></i> Tema</button>
        <a href="../index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</header>

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
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    El sistema organizará los archivos en carpetas separadas y generará un archivo ZIP con la estructura completa.
    Tamaño máximo por archivo: 8MB.
</div>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="form-group">
<label for="archivos_xml"><i class="fas fa-file-upload"></i> Seleccionar archivos XML</label>
<input type="file" id="archivos_xml" name="archivos_xml[]" multiple accept=".xml" required>
</div>
<button type="submit" class="btn-process"><i class="fas fa-cogs"></i> Clasificar Archivos</button>
</form>

<?php if ($downloadLink): ?>
<div class="tool-output">
    <p>Los archivos han sido clasificados y están listos para descargar.</p>
    <a href="<?php echo $downloadLink; ?>" class="btn-download"><i class="fas fa-download"></i> Descargar ZIP</a>
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
