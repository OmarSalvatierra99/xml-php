<?php
chdir(__DIR__);
include 'config.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Herramientas | OFS Tlaxcala</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body data-theme="light">

<header class="dashboard-header">
  <!-- Logo institucional a la izquierda -->
  <img src="img/logo.png" alt="OFS Tlaxcala" class="ofs-logo">

  <!-- Información del usuario a la derecha -->
  <div class="header-actions">
    <button type="button" class="btn-toggle" id="themeToggle"><i class="fas fa-adjust"></i> Tema</button>
  </div>

  <div class="user-info">
    <div>
      <h1><?php echo $full_name; ?></h1>
      <p><?php echo ucwords(str_replace('_', ' ', $role)); ?> | OFS Tlaxcala</p>
    </div>
    <img src="img/user-avatar.png" alt="Avatar" class="avatar">
  </div>
</header>

<main class="dashboard-main">
  <h2><i class="fas fa-toolbox"></i> Herramientas Institucionales</h2>
  <p class="dashboard-description">
    Aplicaciones desarrolladas para fortalecer los procesos de revisión, análisis y fiscalización digital 
    del Órgano de Fiscalización Superior del Estado de Tlaxcala.
  </p>

  <div class="tools-grid">
    <!-- Herramienta 1 -->
    <div class="tool-card" onclick="window.location.href='templates/extraer_xml.php'">
      <i class="fas fa-file-code"></i>
      <h3>Extraer XML de Gasto</h3>
      <p>Analiza archivos XML de gasto y genera reportes Excel con información fiscal y contable detallada.</p>
    </div>

    <!-- Herramienta 2 -->
    <div class="tool-card" onclick="window.location.href='templates/extraer_nomina.php'">
      <i class="fas fa-file-invoice-dollar"></i>
      <h3>Extraer XML de Nómina</h3>
      <p>Procesa XML de nómina y produce reportes Excel con percepciones y deducciones.</p>
    </div>

    <!-- Herramienta 3 -->
    <div class="tool-card" onclick="window.location.href='templates/clasificar_xml.php'">
      <i class="fas fa-folder-tree"></i>
      <h3>Clasificar XML</h3>
      <p>Organiza automáticamente los XML por tipo: Nómina, Gasto o Vacíos, listos para revisión técnica.</p>
    </div>

    <!-- Herramienta 4 -->
    <div class="tool-card" onclick="window.location.href='templates/validar_xml.php'">
      <i class="fas fa-check-circle"></i>
      <h3>Validar XML (Gasto y Nómina)</h3>
      <p>Verifica la autenticidad y estatus fiscal de los CFDI de gasto y nómina mediante conexión directa al servicio oficial del SAT.</p>
    </div>
  </div>
</main>

<footer class="dashboard-footer">
  © <?php echo date('Y'); ?> Órgano de Fiscalización Superior del Estado de Tlaxcala 
</footer>

<script>
(function() {
  const body = document.body;
  const toggle = document.getElementById('themeToggle');
  const savedTheme = localStorage.getItem('ofs-theme');
  if (savedTheme) {
    body.setAttribute('data-theme', savedTheme);
  }
  if (toggle) {
    toggle.addEventListener('click', () => {
      const current = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      body.setAttribute('data-theme', current);
      localStorage.setItem('ofs-theme', current);
    });
  }
})();
</script>

</body>
</html>
