<?php
/**
 * PHP Directory Explorer - Configuration File
 * 
 * Este archivo contiene todas las configuraciones personalizables de la aplicación
 */

// ============================================================================
// INFORMACIÓN DEL DESARROLLADOR
// ============================================================================
define('DEVELOPER_NAME', 'Daniel');
define('DEVELOPER_EMAIL', 'daniel@example.com');
define('APP_NAME', 'PHP Directory Explorer');
define('APP_DESCRIPTION', 'Explorador de directorios web desarrollado en PHP');

// ============================================================================
// CONFIGURACIÓN DEL DIRECTORIO BASE
// ============================================================================

// Configuración del directorio base cuando se accede desde la raíz
// Opciones:
// '' (vacío) = usar directorio actual del script (PHPDirExplorer)
// '../' = usar directorio padre (un nivel arriba)
// '../../' = usar directorio abuelo (dos niveles arriba) 
// '/ruta/absoluta' = usar ruta absoluta específica
// './otra-carpeta' = usar carpeta específica relativa al script
define('ROOT_BASE_CONFIG', '../'); // Por defecto: directorio padre

// ============================================================================
// CONFIGURACIÓN DE FUNCIONALIDADES
// ============================================================================

// Configurar si mostrar archivos ocultos por defecto
define('SHOW_HIDDEN_DEFAULT', false);

// Profundidad máxima para calcular tamaños de directorio (rendimiento)
define('MAX_DIR_DEPTH', 3);

// Configuración de unidades de tamaño
define('SIZE_UNITS', ['B', 'KB', 'MB', 'GB', 'TB']);

// ============================================================================
// CONFIGURACIÓN DE INTERFAZ
// ============================================================================

// Configuración de tema
define('DEFAULT_THEME', 'light'); // 'light' o 'dark'

// Configuración de CDN
define('BOOTSTRAP_CSS_URL', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
define('FONTAWESOME_CSS_URL', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
define('ALPINEJS_URL', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js');
define('BOOTSTRAP_JS_URL', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');

// ============================================================================
// CONFIGURACIÓN DE DEBUG
// ============================================================================

// Habilitar modo debug (mostrar información adicional)
define('DEBUG_MODE', false);

// ============================================================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================================================

// Extensiones de archivo permitidas para descarga (vacío = todas)
define('ALLOWED_EXTENSIONS', []);

// Directorios excluidos de la navegación
define('EXCLUDED_DIRECTORIES', ['.git', '.svn', 'node_modules', 'vendor']);

// Archivos excluidos de la visualización
define('EXCLUDED_FILES', ['.htaccess', '.env', 'config.php']);