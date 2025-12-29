<?php
chdir(__DIR__);
include 'config.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Herramientas | OFS Tlaxcala</title>
  <link rel="stylesheet" href="css/style.css?v=2">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body data-theme="light">

<a href="#mainContent" class="skip-link">Saltar al contenido principal</a>

<header class="dashboard-header" role="banner">
  <!-- Logo institucional a la izquierda -->
  <div class="ofs-logo">
    <span class="logo-text">OFS</span>
    <span class="logo-subtext">Tlaxcala</span>
  </div>

  <!-- Información del usuario a la derecha -->
  <nav class="header-actions" role="navigation" aria-label="Acciones principales">
    <button type="button" class="btn-toggle" id="themeToggle" aria-label="Cambiar tema de color">
      <i class="fas fa-adjust" aria-hidden="true"></i> Tema
    </button>
  </nav>

  <div class="user-info" aria-label="Información del usuario">
    <div>
      <h1><?php echo htmlspecialchars($full_name); ?></h1>
      <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $role))); ?> | OFS Tlaxcala</p>
    </div>
    <img src="img/user-avatar.png" alt="Avatar de <?php echo htmlspecialchars($full_name); ?>" class="avatar">
  </div>
</header>

<main class="dashboard-main" role="main" id="mainContent">
  <h2 id="pageTitle"><i class="fas fa-toolbox" aria-hidden="true"></i> Herramientas Institucionales</h2>
  <p class="dashboard-description">
    Aplicaciones desarrolladas para fortalecer los procesos de revisión, análisis y fiscalización digital 
    del Órgano de Fiscalización Superior del Estado de Tlaxcala.
  </p>

  <div class="tools-grid" role="list" aria-labelledby="pageTitle">
    <!-- Herramienta 1 -->
    <div class="tool-card" role="listitem" tabindex="0"
         onclick="window.location.href='templates/extraer_xml.php'"
         aria-label="Extraer XML de Gasto - Analiza archivos XML de gasto">
      <i class="fas fa-file-code" aria-hidden="true"></i>
      <h3>Extraer XML de Gasto</h3>
      <p>Analiza archivos XML de gasto y genera reportes Excel con información fiscal y contable detallada.</p>
    </div>

    <!-- Herramienta 2 -->
    <div class="tool-card" role="listitem" tabindex="0"
         onclick="window.location.href='templates/extraer_nomina.php'"
         aria-label="Extraer XML de Nómina - Procesa XML de nómina">
      <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
      <h3>Extraer XML de Nómina</h3>
      <p>Procesa XML de nómina y produce reportes Excel con percepciones y deducciones.</p>
    </div>

    <!-- Herramienta 3 -->
    <div class="tool-card" role="listitem" tabindex="0"
         onclick="window.location.href='templates/clasificar_xml.php'"
         aria-label="Clasificar XML - Organiza automáticamente los XML por tipo">
      <i class="fas fa-folder-tree" aria-hidden="true"></i>
      <h3>Clasificar XML</h3>
      <p>Organiza automáticamente los XML por tipo: Nómina, Gasto o Vacíos, listos para revisión técnica.</p>
    </div>

    <!-- Herramienta 4 -->
    <div class="tool-card" role="listitem" tabindex="0"
         onclick="window.location.href='templates/validar_xml.php'"
         aria-label="Validar XML - Verifica la autenticidad con el SAT">
      <i class="fas fa-check-circle" aria-hidden="true"></i>
      <h3>Validar XML (Gasto y Nómina)</h3>
      <p>Verifica la autenticidad y estatus fiscal de los CFDI de gasto y nómina mediante conexión directa al servicio oficial del SAT.</p>
    </div>
  </div>
</main>

<footer class="dashboard-footer" role="contentinfo">
  © <?php echo date('Y'); ?> Órgano de Fiscalización Superior del Estado de Tlaxcala
</footer>

<script src="js/app.js?v=2"></script>

</body>
</html>
