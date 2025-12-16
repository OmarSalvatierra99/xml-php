<?php
include '../config.php';
ob_start();

$uploadDir = realpath(__DIR__ . '/../uploads/nomina/');
if (!$uploadDir) {
    mkdir(__DIR__ . '/../uploads/nomina/', 0777, true);
    $uploadDir = realpath(__DIR__ . '/../uploads/nomina/');
}

$uploadedFiles = [];
$invalidFiles = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivos_xml'])) {
    $tempDir = $uploadDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(10));
    mkdir($tempDir, 0777, true);

    foreach ($_FILES['archivos_xml']['tmp_name'] as $i => $tmp) {
        $filename = basename($_FILES['archivos_xml']['name'][$i]);
        if ($_FILES['archivos_xml']['error'][$i] !== UPLOAD_ERR_OK || $_FILES['archivos_xml']['size'][$i] <= 0) {
            $invalidFiles[$filename] = "Archivo vacío o no válido.";
            continue;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'xml') {
            $invalidFiles[$filename] = "Solo se permiten archivos XML.";
            continue;
        }
        move_uploaded_file($tmp, "$tempDir/$filename");
        $uploadedFiles[] = "$tempDir/$filename";
    }

    if (!empty($uploadedFiles)) {
        $scriptPath = realpath(__DIR__ . '/../scripts/extractor_nomina.py');
        $pythonExec = defined('PYTHON_PATH') && !empty(PYTHON_PATH) ? PYTHON_PATH : 'python';
        $command = escapeshellarg($pythonExec) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempDir) . " 2>&1";
        exec($command, $output, $returnVar);

        $excelPath = trim(end($output));
        if ($returnVar === 0 && file_exists($excelPath)) {
            ob_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Nomina_Procesada.xlsx"');
            header('Content-Length: ' . filesize($excelPath));
            readfile($excelPath);
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
            exit;
        } else {
            $error = "Error al procesar nómina. Detalle: " . implode("\n", $output);
        }
    } else {
        $error = "No se subieron archivos válidos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Extracción Nómina | OFS Tlaxcala</title>
<link rel="stylesheet" href="../css/style.css?v=1">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
<header class="dashboard-header">
<div class="user-info">
<img src="../img/user-avatar.png" alt="Avatar" class="avatar">
<div><h1>Extracción de datos XML Nómina</h1><p><?php echo ucwords(str_replace('_', ' ', $role)); ?> | OFS Tlaxcala</p></div>
</div>
<a href="../index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
</header>

<main class="dashboard-main">
<section class="tool-section">
<h2><i class="fas fa-file-invoice-dollar"></i> Extraer datos de Nómina</h2>

<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

<div class="tool-instructions">
<p>Suba uno o varios XML de nómina para extraer datos en Excel.</p>
<div class="alert alert-info"><i class="fas fa-info-circle"></i> Complemento Nomina 1.2 soportado</div>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="form-group">
<label for="archivos_xml"><i class="fas fa-file-upload"></i> Seleccionar archivos XML</label>
<input type="file" id="archivos_xml" name="archivos_xml[]" multiple accept=".xml" required>
</div>
<button type="submit" class="btn-process"><i class="fas fa-cogs"></i> Procesar Nómina</button>
</form>
</section>
</main>
</body>
</html>
