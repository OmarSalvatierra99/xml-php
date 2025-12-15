<?php
/* =========================================================
   INFO DEL SISTEMA | OFS Tlaxcala
   Desarrollado por: Omar Gabriel Salvatierra García
   ========================================================= */

/**
 * Seguridad básica:
 * Este archivo debe estar restringido en producción
 * mediante autenticación o eliminación tras pruebas.
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso no autorizado.');
}

/**
 * Encabezado limpio
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=============================================\n";
echo " SISTEMA DE HERRAMIENTAS - OFS TLAXCALA\n";
echo " Información de entorno y servidor\n";
echo "=============================================\n\n";

/**
 * Variables de entorno
 */
echo "[VARIABLES DE ENTORNO]\n";
echo "PATH:      " . getenv('PATH') . "\n";
echo "USER:      " . (getenv('USER') ?: getenv('USERNAME')) . "\n";
echo "HOME:      " . getenv('HOME') . "\n";
echo "PHP Versión: " . PHP_VERSION . "\n";
echo "Extensiones cargadas: " . implode(', ', get_loaded_extensions()) . "\n";
echo "\n";

/**
 * Rutas críticas del sistema
 */
echo "[RUTAS DE PROYECTO]\n";
echo "Directorio actual: " . __DIR__ . "\n";
echo "Ruta absoluta PHP: " . PHP_BINDIR . "\n";
echo "Archivo ejecutado: " . $_SERVER['SCRIPT_FILENAME'] . "\n\n";

/**
 * Verificación de Python en entorno
 */
echo "[EJECUCIÓN PYTHON]\n";
$python3 = trim(shell_exec('which python3 2>/dev/null'));
$python  = trim(shell_exec('which python 2>/dev/null'));
echo "python3: " . ($python3 ?: "No detectado") . "\n";
echo "python:  " . ($python ?: "No detectado") . "\n\n";

/**
 * Información del sistema operativo
 */
echo "[SISTEMA OPERATIVO]\n";
echo php_uname() . "\n\n";

/**
 * Información PHP resumida
 * phpinfo() se comenta por seguridad, ya que expone variables sensibles.
 * Descomentar solo para pruebas locales.
 */
// phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
?>
