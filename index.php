<?php
// ============================================================================
// CONFIGURACIÓN PERSONALIZABLE
// ============================================================================

// Configuración del directorio base cuando se accede desde la raíz
// Opciones:
// '' (vacío) = usar directorio actual del script (PHPDirExplorer)
// '../' = usar directorio padre (un nivel arriba)
// '../../' = usar directorio abuelo (dos niveles arriba) 
// '/ruta/absoluta' = usar ruta absoluta específica
// './otra-carpeta' = usar carpeta específica relativa al script
$ROOT_BASE_CONFIG = '../'; // Por defecto: directorio padre

// ============================================================================
// DETECCIÓN AUTOMÁTICA DE CONTEXTO
// ============================================================================

$scriptDir = dirname(__FILE__);

// Determinar si estamos siendo accedidos via rewrite desde una URL de directorio raíz
// Esto detecta si el REQUEST_URI apunta a un directorio pero el SCRIPT_NAME apunta a nuestro index.php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Extraer la parte del path sin parámetros
$requestPath = strtok($requestUri, '?');
$scriptPath = dirname($scriptName);

// Detectar si estamos en modo "root access":
// 1. El REQUEST_URI termina en '/' (acceso a directorio)
// 2. O el REQUEST_URI termina en '/index.php' pero no apunta directamente a nuestro script
// 3. Y el script que se está ejecutando está en una subcarpeta
$isRootAccess = false;

if (rtrim($requestPath, '/') !== rtrim($scriptPath, '/')) {
    // Si la ruta solicitada es diferente a la ruta del script, estamos en modo rewrite
    $isRootAccess = (
        str_ends_with($requestPath, '/') || 
        str_ends_with($requestPath, '/index.php')
    );
}

// ============================================================================
// CONFIGURACIÓN DEL DIRECTORIO BASE
// ============================================================================

if ($isRootAccess && !empty($ROOT_BASE_CONFIG)) {
    // Estamos en modo root access, usar la configuración personalizada
    if ($ROOT_BASE_CONFIG[0] === '/') {
        // Ruta absoluta
        $baseDir = $ROOT_BASE_CONFIG;
    } else {
        // Ruta relativa al directorio del script
        $baseDir = realpath($scriptDir . '/' . $ROOT_BASE_CONFIG) ?: $scriptDir;
    }
} else {
    // Acceso directo al PHPDirExplorer o configuración vacía
    $baseDir = $scriptDir;
}

$baseUrl = '';

// Si estamos en un entorno web, intentar determinar la URL base
if ($scriptPath !== '/' && $scriptPath !== '\\') {
    $baseUrl = $scriptPath;
}

// ============================================================================
// DEBUG (descomenta las siguientes líneas para debug)
// ============================================================================
/*
echo "<!-- DEBUG INFO
Request URI: $requestUri
Request Path: $requestPath
Script Name: $scriptName
Script Path: $scriptPath
Script Dir: $scriptDir
Is Root Access: " . ($isRootAccess ? 'YES' : 'NO') . "
Base Dir: $baseDir
Root Base Config: $ROOT_BASE_CONFIG
-->";
*/

// Function to get absolute path safely
function getAbsolutePath($path)
{
    return realpath($path) ?: $path;
}

// Convierte una ruta del sistema de archivos a una URL relativa
function getRelativeUrl($path)
{
    global $baseDir, $baseUrl;

    $realPath = getAbsolutePath($path);
    $realBaseDir = getAbsolutePath($baseDir);

    // Si el path está dentro de la base, calcular la URL relativa
    if (strpos($realPath, $realBaseDir) === 0) {
        $relativePath = substr($realPath, strlen($realBaseDir));
        $relativePath = str_replace('\\', '/', $relativePath);

        // Normalizar: asegurar que comience con /
        if (!empty($relativePath) && $relativePath[0] !== '/') {
            $relativePath = '/' . $relativePath;
        }

        // Combinar con la URL base
        return $baseUrl . $relativePath;
    }

    return null;
}

// El resto de las funciones se mantienen igual
function getDirectories($path, $showHidden = false)
{
    $directories = [];

    if (!is_readable($path)) {
        return $directories;
    }

    try {
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            // Saltamos archivos ocultos si showHidden es false
            if (!$showHidden && $item[0] === '.') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath) && is_readable($fullPath)) {
                $relativeUrl = getRelativeUrl($fullPath);
                $perms = getPerms($fullPath);
                $size = getDirSize($fullPath, 2); // Limitar a profundidad 2 para rendimiento

                $directories[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'url' => $relativeUrl,
                    'perms' => $perms,
                    'size' => $size,
                    'formatted_size' => formatSize($size),
                    'is_hidden' => $item[0] === '.'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }

    return $directories;
}

function getFiles($path, $showHidden = false)
{
    $files = [];

    if (!is_readable($path)) {
        return $files;
    }

    try {
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            // Saltamos archivos ocultos si showHidden es false
            if (!$showHidden && $item[0] === '.') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_file($fullPath) && is_readable($fullPath)) {
                $relativeUrl = getRelativeUrl($fullPath);
                $perms = getPerms($fullPath);
                $size = filesize($fullPath);

                $files[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'url' => $relativeUrl,
                    'perms' => $perms,
                    'size' => $size,
                    'formatted_size' => formatSize($size),
                    'is_hidden' => $item[0] === '.'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }

    return $files;
}

// Función para calcular tamaño de directorio recursivamente (con límite para evitar sobrecarga)
function getDirSize($path, $maxDepth = 3, $currentDepth = 0)
{
    if ($currentDepth > $maxDepth) {
        return 0; // Limitar profundidad para evitar problemas de rendimiento
    }

    $size = 0;
    if (!is_readable($path)) {
        return $size;
    }

    try {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $fullPath = $path . '/' . $item;
            if (is_file($fullPath)) {
                $size += filesize($fullPath);
            } else if (is_dir($fullPath)) {
                $size += getDirSize($fullPath, $maxDepth, $currentDepth + 1);
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }

    return $size;
}

// Función para obtener permisos en formato legible
function getPerms($path)
{
    $perms = fileperms($path);

    if (($perms & 0xC000) == 0xC000) {
        // Socket
        $info = 's';
    } elseif (($perms & 0xA000) == 0xA000) {
        // Enlace simbólico
        $info = 'l';
    } elseif (($perms & 0x8000) == 0x8000) {
        // Regular
        $info = '-';
    } elseif (($perms & 0x6000) == 0x6000) {
        // Especial de bloque
        $info = 'b';
    } elseif (($perms & 0x4000) == 0x4000) {
        // Directorio
        $info = 'd';
    } elseif (($perms & 0x2000) == 0x2000) {
        // Especial de carácter
        $info = 'c';
    } elseif (($perms & 0x1000) == 0x1000) {
        // FIFO
        $info = 'p';
    } else {
        // Desconocido
        $info = 'u';
    }

    // Propietario
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
        (($perms & 0x0800) ? 's' : 'x') :
        (($perms & 0x0800) ? 'S' : '-'));

    // Grupo
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
        (($perms & 0x0400) ? 's' : 'x') :
        (($perms & 0x0400) ? 'S' : '-'));

    // Otros
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
        (($perms & 0x0200) ? 't' : 'x') :
        (($perms & 0x0200) ? 'T' : '-'));

    // Formato numérico
    $octal = sprintf('%04o', $perms & 0777);

    return ['symbolic' => $info, 'octal' => $octal];
}

// Función para formatear tamaños de archivo
function formatSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// La lógica principal se mantiene igual
$currentPath = isset($_GET['path']) ? $_GET['path'] : $baseDir;
$currentPath = getAbsolutePath($currentPath);

if (!file_exists($currentPath) || !is_dir($currentPath)) {
    $currentPath = $baseDir;
}

if (strpos($currentPath, getAbsolutePath($baseDir)) !== 0) {
    $currentPath = $baseDir;
}

$parentPath = dirname($currentPath);
if (strpos(getAbsolutePath($parentPath), getAbsolutePath($baseDir)) !== 0) {
    $parentPath = $baseDir;
}

// Modificamos la llamada a estas funciones para pasar el parámetro show_hidden
$show_hidden = isset($_GET['show_hidden']) && $_GET['show_hidden'] === '1';
$directories = getDirectories($currentPath, $show_hidden);
$files = getFiles($currentPath, $show_hidden);
$isScriptDir = ($currentPath === $baseDir);

$pathParts = [];
$tempPath = $currentPath;
while (strpos($tempPath, $baseDir) === 0 && $tempPath !== $baseDir) {
    array_unshift($pathParts, [
        'name' => basename($tempPath),
        'path' => $tempPath
    ]);
    $tempPath = dirname($tempPath);
}
?>

<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" :class="{ 'dark-mode': darkMode }">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directory Explorer</title>
    <!-- Bootstrap CSS (actualizado a 5.3.0) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;

            --bg-color: #f5f5f5;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.1);
            --hover-bg: rgba(13, 110, 253, 0.05);
            --path-bg: rgba(13, 110, 253, 0.1);
        }

        .dark-mode {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            /* Color de texto más claro para mejor contraste */
            --card-bg: #1e1e1e;
            --border-color: rgba(255, 255, 255, 0.1);
            --hover-bg: rgba(255, 255, 255, 0.05);
            --path-bg: rgba(13, 110, 253, 0.3);

            /* Añade estas variables adicionales para colores específicos en modo oscuro */
            --folder-name-color: #ffffff;
            --file-name-color: #f0f0f0;
            --icon-folder-color: #ffc107;
            /* Amarillo más brillante para carpetas */
            --icon-file-color: #adb5bd;
            /* Gris más claro para archivos */
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .web-icon {
            color: var(--primary-color);
        }

        .folder-icon {
            color: var(--warning-color);
            margin-right: 8px;
        }

        .file-icon {
            color: var(--secondary-color);
            margin-right: 8px;
        }

        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
            background-color: var(--card-bg);
            transition: background-color 0.3s;
        }

        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
        }

        .directory-container,
        .file-container {
            max-height: 70vh;
            overflow-y: auto;
            padding: 0;
        }

        .directory-item,
        .file-item {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease;
        }

        .directory-item:hover,
        .file-item:hover {
            background-color: var(--hover-bg);
        }

        .directory-item:last-child,
        .file-item:last-child {
            border-bottom: none;
        }

        .dir-name {
            cursor: pointer;
            flex-grow: 1;
            display: flex;
            align-items: center;
        }

        .me-2 {
            margin-right: 12px !important;
        }

        .breadcrumb {
            background-color: var(--card-bg);
            padding: 12px 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .current-path {
            background-color: var(--path-bg);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-outline-secondary {
            border-color: var(--border-color);
        }

        /* Estilo para destacar los iconos de web */
        .web-accessible {
            animation: pulse 2s infinite;
            color: var(--primary-color);
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        /* Estilo para los tooltips */
        [x-cloak] {
            display: none !important;
        }

        .tooltip-inner {
            max-width: 300px;
        }

        /* Estilo para el botón de tema */
        .theme-toggle {
            cursor: pointer;
            margin-right: 15px;
            padding: 5px 10px;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: var(--card-bg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .theme-toggle i {
            font-size: 1.2rem;
        }

        /* Estilos específicos para modo oscuro */
        .dark-mode .breadcrumb-item.active {
            color: #adb5bd;
        }

        .dark-mode .text-muted {
            color: #adb5bd !important;
        }

        .dark-mode .badge.bg-primary {
            background-color: #0d6efd !important;
        }

        .dark-mode a {
            color: #6ea8fe;
        }

        .dark-mode a:hover {
            color: #9ec5fe;
        }

        .dark-mode .directory-item,
        .dark-mode .file-item {
            border-bottom-color: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .dark-mode .folder-icon {
            color: var(--icon-folder-color);
        }

        .dark-mode .file-icon {
            color: var(--icon-file-color);
        }

        .dark-mode .dir-name {
            color: var(--folder-name-color);
        }

        .dark-mode .directory-item:hover,
        .dark-mode .file-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .dark-mode .card,
        .dark-mode .card-header,
        .dark-mode .breadcrumb {
            background-color: var(--card-bg);
        }

        .dark-mode .theme-toggle {
            background-color: #2d2d2d;
        }

        /* ... tus estilos actuales ... */

        .file-meta,
        .dir-meta {
            margin-left: auto;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--secondary-color);
            white-space: nowrap;
            padding-left: 10px;
            letter-spacing: -0.2px;
        }

        .perms-badge {
            padding: 2px 5px;
            font-size: 0.7rem;
            border-radius: 3px;
            font-family: monospace;
            margin-right: 8px;
            font-weight: 600;
        }

        .size-badge {
            padding: 2px 5px;
            font-size: 0.7rem;
            border-radius: 3px;
            margin-right: 8px;
            font-weight: 500;
        }

        /* Estilos para permisos según el nivel */
        .perms-low {
            background-color: rgba(255, 193, 7, 0.2);
            color: #b78500;
        }

        .perms-medium {
            background-color: rgba(13, 110, 253, 0.2);
            color: #0a58ca;
        }

        .perms-high {
            background-color: rgba(220, 53, 69, 0.2);
            color: #b02a37;
        }

        .perms-badge code {
            font-size: 0.7rem;
        }

        .dark-mode .file-meta,
        .dark-mode .dir-meta {
            color: #adb5bd;
        }

        .dark-mode .perms-low {
            background-color: rgba(255, 193, 7, 0.3);
            color: #ffcd39;
        }

        .dark-mode .perms-medium {
            background-color: rgba(13, 110, 253, 0.3);
            color: #6ea8fe;
        }

        .dark-mode .perms-high {
            background-color: rgba(220, 53, 69, 0.3);
            color: #ea868f;
        }

        .dark-mode .size-badge {
            background-color: rgba(108, 117, 125, 0.3);
            color: #e0e0e0;
        }

        /* Estilos para el modal de configuración */
        .settings-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        }
        
        .settings-modal-content {
            background-color: var(--card-bg);
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .settings-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .settings-body {
            padding: 20px;
        }
        
        .settings-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            text-align: right;
        }
        
        .form-check {
            margin-bottom: 15px;
        }
        
        /* Estilo para archivos ocultos */
        .hidden-item {
            opacity: 0.7;
        }
        
        .hidden-item .fa-eye-slash {
            margin-left: 5px;
            font-size: 0.7em;
            opacity: 0.7;
        }
        
        /* Animación para el botón de configuración */
        .settings-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .settings-toggle:hover {
            transform: rotate(90deg);
        }
        
        .form-switch {
            padding-left: 2.5em;
        }
        
        .form-switch .form-check-input {
            width: 3em;
        }
        
        .settings-icon {
            margin-left: 10px;
            color: var(--primary-color);
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .settings-icon:hover {
            transform: rotate(90deg);
        }
    </style>
</head>

<body>
    <div class="container py-4" x-data="{ 
        showTooltip: false, 
        tooltipContent: '', 
        tooltipPosition: { x: 0, y: 0 },
        showSettings: false,
        
        // Configuraciones de usuario
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        },
        
        // Nueva configuración
        settings: {
            showSizes: localStorage.getItem('showSizes') === 'true',
            showPermissions: localStorage.getItem('showPermissions') !== 'false',  // Por defecto true
            showHiddenFiles: localStorage.getItem('showHiddenFiles') === 'true'
        },
        
        // Método para guardar configuraciones
        saveSettings() {
            localStorage.setItem('showSizes', this.settings.showSizes);
            localStorage.setItem('showPermissions', this.settings.showPermissions);
            localStorage.setItem('showHiddenFiles', this.settings.showHiddenFiles);
            
            // Solo recargamos si cambia la configuración de archivos ocultos
            // porque esto requiere volver a procesar los archivos en el servidor
            if (this.settings.showHiddenFiles !== (<?= $show_hidden ? 'true' : 'false' ?>)) {
                window.location.href = '?path=<?= htmlspecialchars(rawurlencode($currentPath)) ?>&show_hidden=' + (this.settings.showHiddenFiles ? '1' : '0');
            } else {
                this.showSettings = false;
            }
        }
    }">
        <div class="page-header">
            <div class="d-flex align-items-center">
                <button @click="toggleDarkMode()" class="theme-toggle border-0">
                    <i class="fas" :class="darkMode ? 'fa-sun text-warning' : 'fa-moon text-primary'"></i>
                </button>
                <h1 class="mb-0">
                    <i class="fas fa-folder-open me-2 text-primary"></i>
                    Directory Explorer
                    <i class="fas fa-cog settings-icon" @click="showSettings = true"></i>
                </h1>
            </div>
            <span class="badge bg-primary"><?= count($directories) ?> dirs, <?= count($files) ?> files</span>
        </div>
        
        <div class="current-path">
            <strong><i class="fas fa-map-marker-alt me-1"></i> Current Path:</strong>
            <?= htmlspecialchars($currentPath) ?>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="?path=<?= htmlspecialchars(rawurlencode($baseDir)) ?>" class="text-decoration-none">
                        <i class="fas fa-home"></i> Base Directory
                    </a>
                </li>

                <?php foreach ($pathParts as $index => $part): ?>
                    <li class="breadcrumb-item <?= ($index === count($pathParts) - 1) ? 'active' : '' ?>">
                        <?php if ($index === count($pathParts) - 1): ?>
                            <?= htmlspecialchars($part['name']) ?>
                        <?php else: ?>
                            <a href="?path=<?= htmlspecialchars(rawurlencode($part['path'])) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($part['name']) ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="row mt-4">
            <!-- Directory List (Left Column) -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-folder me-2" :class="darkMode ? 'text-warning' : 'text-primary'"></i>
                            <span :class="darkMode ? 'text-light' : ''">Directories</span>
                        </h5>
                        <?php if ($currentPath !== $baseDir): ?>
                            <a href="?path=<?= htmlspecialchars(rawurlencode($parentPath)) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-up"></i> Up
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="directory-container">
                        <?php if (!empty($directories)): ?>
                            <?php foreach ($directories as $dir): ?>
                                <div class="directory-item <?= $dir['is_hidden'] ? 'hidden-item' : '' ?>"
                                    x-on:mouseenter="showTooltip = true; tooltipContent = '<?= htmlspecialchars($dir['path']) ?>'; tooltipPosition = { x: $event.pageX, y: $event.pageY };"
                                    x-on:mouseleave="showTooltip = false">
                                    <!-- Icono de acceso web -->
                                    <?php if (!empty($dir['url'])): ?>
                                        <a href="<?= htmlspecialchars($dir['url']) ?>" target="_blank" class="me-2"
                                            title="Open in browser">
                                            <i class="fas fa-globe web-icon web-accessible"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="me-2 text-muted">
                                            <i class="fas fa-ban web-icon"
                                                :style="darkMode ? 'opacity: 0.5;' : 'opacity: 0.3;'"></i>
                                        </span>
                                    <?php endif; ?>

                                    <div class="dir-name"
                                        onclick="window.location.href='?path=<?= htmlspecialchars(rawurlencode($dir['path'])) ?><?= $show_hidden ? '&show_hidden=1' : '' ?>'">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <?= htmlspecialchars($dir['name']) ?>
                                        <?php if ($dir['is_hidden']): ?>
                                            <i class="fas fa-eye-slash" title="Hidden directory"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Información de permisos y tamaño -->
                                    <div class="dir-meta">
                                        <?php
                                        $permsClass = 'perms-low';
                                        if (substr($dir['perms']['octal'], -1) == '7') {
                                            $permsClass = 'perms-high';
                                        } elseif (substr($dir['perms']['octal'], -1) >= '5') {
                                            $permsClass = 'perms-medium';
                                        }
                                        ?>
                                        <span class="perms-badge <?= $permsClass ?>" title="<?= $dir['perms']['symbolic'] ?>"
                                          x-show="settings.showPermissions">
                                            <code><?= $dir['perms']['octal'] ?></code>
                                        </span>
                                        <span class="size-badge" :class="darkMode ? 'bg-dark' : 'bg-light'" 
                                          x-show="settings.showSizes">
                                            <?= $dir['formatted_size'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                                <p>No directories found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- File List (Right Column) -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2" :class="darkMode ? 'text-info' : 'text-primary'"></i>
                            <span :class="darkMode ? 'text-light' : ''">Files</span>
                        </h5>
                    </div>
                    <div class="file-container">
                        <?php if (!empty($files)): ?>
                            <?php foreach ($files as $file): ?>
                                <div class="file-item <?= $file['is_hidden'] ? 'hidden-item' : '' ?>"
                                    x-on:mouseenter="showTooltip = true; tooltipContent = '<?= htmlspecialchars($file['path']) ?>'; tooltipPosition = { x: $event.pageX, y: $event.pageY };"
                                    x-on:mouseleave="showTooltip = false">
                                    <!-- Icono de acceso web -->
                                    <?php if (!empty($file['url'])): ?>
                                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" class="me-2"
                                            title="Open in browser">
                                            <i class="fas fa-globe web-icon web-accessible"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="me-2 text-muted">
                                            <i class="fas fa-ban web-icon"
                                                :style="darkMode ? 'opacity: 0.5;' : 'opacity: 0.3;'"></i>
                                        </span>
                                    <?php endif; ?>

                                    <i class="fas fa-file file-icon"></i>
                                    <?= htmlspecialchars($file['name']) ?>
                                    <?php if ($file['is_hidden']): ?>
                                        <i class="fas fa-eye-slash" title="Hidden file"></i>
                                    <?php endif; ?>

                                    <!-- Información de permisos y tamaño -->
                                    <div class="file-meta">
                                        <?php
                                        $permsClass = 'perms-low';
                                        if (substr($file['perms']['octal'], -1) == '7') {
                                            $permsClass = 'perms-high';
                                        } elseif (substr($file['perms']['octal'], -1) >= '5') {
                                            $permsClass = 'perms-medium';
                                        }
                                        ?>
                                        <span class="perms-badge <?= $permsClass ?>" title="<?= $file['perms']['symbolic'] ?>"
                                              x-show="settings.showPermissions">
                                            <code><?= $file['perms']['octal'] ?></code>
                                        </span>
                                        <span class="size-badge" :class="darkMode ? 'bg-dark' : 'bg-light'"
                                              x-show="settings.showSizes">
                                            <?= $file['formatted_size'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-file fa-3x mb-3 text-muted"></i>
                                <p>No files found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de configuración -->
        <div class="settings-modal" x-show="showSettings" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="settings-modal-content"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-90"
                 x-transition:enter-end="opacity-100 transform scale-100">
                <div class="settings-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog me-2"></i>
                        <span :class="darkMode ? 'text-light' : ''">Explorer Settings</span>
                    </h5>
                    <button type="button" class="btn-close" @click="showSettings = false" :class="darkMode ? 'btn-close-white' : ''"></button>
                </div>
                <div class="settings-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="showSizes" x-model="settings.showSizes">
                        <label class="form-check-label" for="showSizes" :class="darkMode ? 'text-light' : ''">
                            <i class="fas fa-database me-2 text-primary"></i>
                            Show file/directory sizes
                        </label>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="showPermissions" x-model="settings.showPermissions">
                        <label class="form-check-label" for="showPermissions" :class="darkMode ? 'text-light' : ''">
                            <i class="fas fa-lock me-2 text-success"></i>
                            Show file/directory permissions
                        </label>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="showHiddenFiles" x-model="settings.showHiddenFiles">
                        <label class="form-check-label" for="showHiddenFiles" :class="darkMode ? 'text-light' : ''">
                            <i class="fas fa-eye me-2 text-warning"></i>
                            Show hidden files (dot files)
                        </label>
                    </div>
                </div>
                <div class="settings-footer">
                    <button type="button" class="btn btn-secondary me-2" @click="showSettings = false">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="saveSettings()">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- Tooltip personalizado con Alpine.js -->
        <div x-cloak x-show="showTooltip" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            :style="`position: fixed; left: ${tooltipPosition.x + 15}px; top: ${tooltipPosition.y + 10}px; z-index: 999;`"
            class="bg-dark text-white py-1 px-2 rounded text-sm" style="max-width: 300px;">
            <p class="mb-0" x-text="tooltipContent"></p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>