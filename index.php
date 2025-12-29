<?php
// Include version information
require_once 'version.php';

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
    // Si estamos en modo root access, el baseUrl debe apuntar al directorio padre
    // para que las URLs de "Open in browser" no incluyan .explorer
    if ($isRootAccess && !empty($ROOT_BASE_CONFIG)) {
        // Remover .explorer del path para obtener la URL correcta
        $baseUrl = dirname($scriptPath);
    } else {
        $baseUrl = $scriptPath;
    }
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
                $owner = getOwner($fullPath);

                $directories[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'url' => $relativeUrl,
                    'perms' => $perms,
                    'size' => $size,
                    'formatted_size' => formatSize($size),
                    'is_hidden' => $item[0] === '.',
                    'owner' => $owner
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
                $owner = getOwner($fullPath);

                $files[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'url' => $relativeUrl,
                    'perms' => $perms,
                    'size' => $size,
                    'formatted_size' => formatSize($size),
                    'is_hidden' => $item[0] === '.',
                    'owner' => $owner
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

// Función para obtener propietario y grupo del archivo
function getOwner($path)
{
    $owner = 'unknown';
    $group = 'unknown';
    $uid = null;
    $gid = null;
    
    try {
        $uid = fileowner($path);
        $gid = filegroup($path);
        
        // Intentar obtener el nombre del propietario
        if (function_exists('posix_getpwuid') && $uid !== false) {
            $ownerInfo = posix_getpwuid($uid);
            $owner = $ownerInfo['name'] ?? $uid;
        } else {
            $owner = $uid;
        }
        
        // Intentar obtener el nombre del grupo
        if (function_exists('posix_getgrgid') && $gid !== false) {
            $groupInfo = posix_getgrgid($gid);
            $group = $groupInfo['name'] ?? $gid;
        } else {
            $group = $gid;
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return [
        'owner' => $owner,
        'group' => $group,
        'uid' => $uid,
        'gid' => $gid,
        'is_root' => ($owner === 'root' || $uid === 0)
    ];
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
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --success: #10b981;
            --info: #06b6d4;
            --warning: #f59e0b;
            --danger: #ef4444;
            
            --bg-gradient-start: #f8fafc;
            --bg-gradient-end: #e2e8f0;
            --bg-color: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
            --text-color: #1e293b;
            --text-muted: #64748b;
            --card-bg: rgba(255, 255, 255, 0.8);
            --card-border: rgba(255, 255, 255, 0.5);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --border-color: rgba(148, 163, 184, 0.2);
            --hover-bg: rgba(99, 102, 241, 0.08);
            --path-bg: rgba(99, 102, 241, 0.1);
            --glass-blur: blur(20px);
            
            --folder-color: #f59e0b;
            --file-color: #64748b;
            --web-color: #6366f1;
        }

        .dark-mode {
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.8);
            --card-border: rgba(51, 65, 85, 0.5);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --border-color: rgba(51, 65, 85, 0.5);
            --hover-bg: rgba(99, 102, 241, 0.15);
            --path-bg: rgba(99, 102, 241, 0.2);
            
            --folder-color: #fbbf24;
            --file-color: #94a3b8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding-bottom: 70px;
        }

        .dark-mode body {
            background: var(--bg-color);
        }

        /* Animated background */
        .bg-decoration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }

        .bg-decoration::before,
        .bg-decoration::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: float 20s infinite ease-in-out;
        }

        .bg-decoration::before {
            width: 600px;
            height: 600px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            top: -200px;
            right: -100px;
        }

        .bg-decoration::after {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(16, 185, 129, 0.15));
            bottom: -100px;
            left: -100px;
            animation-delay: -10s;
        }

        .dark-mode .bg-decoration::before {
            opacity: 0.3;
        }

        .dark-mode .bg-decoration::after {
            opacity: 0.2;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(5deg); }
            66% { transform: translate(-20px, 20px) rotate(-5deg); }
        }

        /* Glass Cards */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }

        .dark-mode .glass-card:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
        }

        .card-header h5 {
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: -0.02em;
        }

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 20px 0;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin: 0;
            background: linear-gradient(135deg, var(--text-color) 0%, var(--text-muted) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-text span {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Stats Badge */
        .stats-badge {
            display: flex;
            gap: 8px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
        }

        .stat-item.folders {
            color: var(--folder-color);
        }

        .stat-item.files {
            color: var(--primary);
        }

        /* Combined Breadcrumb & Path Bar */
        .breadcrumb-path-bar {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            padding: 12px 20px;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .breadcrumb-path-bar .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-dark);
        }

        .breadcrumb-item.active {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Mini Current Path (right side) */
        .current-path-mini {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.7rem;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-muted);
            background: var(--path-bg);
            padding: 6px 12px;
            border-radius: 8px;
            max-width: 50%;
            overflow: hidden;
        }

        .current-path-mini i {
            color: var(--primary);
            font-size: 0.7rem;
            flex-shrink: 0;
        }

        .current-path-mini span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            .breadcrumb-path-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .current-path-mini {
                max-width: 100%;
                width: 100%;
            }
        }

        /* Directory & File Container */
        .directory-container,
        .file-container {
            max-height: 65vh;
            overflow-y: auto;
            padding: 8px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .directory-container::-webkit-scrollbar,
        .file-container::-webkit-scrollbar {
            width: 6px;
        }

        .directory-container::-webkit-scrollbar-thumb,
        .file-container::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        /* Items */
        .directory-item,
        .file-item {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            cursor: pointer;
        }

        .directory-item:hover,
        .file-item:hover {
            background: var(--hover-bg);
            border-color: var(--border-color);
            transform: translateX(4px);
        }

        .dir-name,
        .file-name {
            flex-grow: 1;
            display: flex;
            align-items: center;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Icons */
        .icon-wrapper {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 14px;
            transition: all 0.2s ease;
        }

        .folder-icon-wrapper {
            background: rgba(245, 158, 11, 0.15);
            color: var(--folder-color);
        }

        .file-icon-wrapper {
            background: rgba(100, 116, 139, 0.15);
            color: var(--file-color);
        }

        .web-icon-wrapper {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .web-icon-wrapper:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
            text-decoration: none;
        }

        .web-icon-wrapper.disabled {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-muted);
            opacity: 0.4;
        }

        /* Meta Information */
        .item-meta {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
        }

        .perms-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .perms-low {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .perms-medium {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
        }

        .perms-high {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .dark-mode .perms-low {
            color: #fbbf24;
        }

        .dark-mode .perms-medium {
            color: var(--primary-light);
        }

        .dark-mode .perms-high {
            color: #f87171;
        }

        .size-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 8px;
            background: var(--border-color);
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Owner Badge */
        .owner-badge {
            padding: 4px 8px;
            font-size: 0.65rem;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
            cursor: default;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .owner-badge i {
            font-size: 0.6rem;
        }

        .owner-root {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .owner-user {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }

        .dark-mode .owner-root {
            color: #f87171;
        }

        .dark-mode .owner-user {
            color: #34d399;
        }

        /* Hidden Items */
        .hidden-item {
            opacity: 0.6;
        }

        .hidden-badge {
            font-size: 0.65rem;
            margin-left: 8px;
            opacity: 0.7;
        }

        /* Theme Toggle */
        .theme-toggle {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: none;
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-color);
        }

        .theme-toggle:hover {
            transform: translateY(-2px) rotate(15deg);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .theme-toggle i {
            font-size: 1.1rem;
        }

        /* Settings Button */
        .settings-btn {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: none;
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
        }

        .settings-btn:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }

        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            background: var(--hover-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateX(-4px);
        }

        /* Settings Modal */
        .settings-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        }
        
        .settings-modal-content {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: 24px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            border: 1px solid var(--card-border);
            overflow: hidden;
        }
        
        .settings-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-header h5 {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-body {
            padding: 24px;
        }

        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            margin-bottom: 12px;
            background: var(--hover-bg);
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .setting-item:hover {
            transform: translateX(4px);
        }

        .setting-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .setting-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .setting-icon.size { background: rgba(99, 102, 241, 0.15); color: var(--primary); }
        .setting-icon.perms { background: rgba(16, 185, 129, 0.15); color: var(--success); }
        .setting-icon.hidden { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .setting-icon.owner { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }

        .setting-label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Settings Info Message */
        .settings-info-message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            margin-top: 8px;
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            border-radius: 12px;
            font-size: 0.8rem;
            color: var(--info);
        }
        
        .settings-info-message i {
            font-size: 1rem;
        }
        
        .dark-mode .settings-info-message {
            background: rgba(6, 182, 212, 0.15);
        }

        /* Custom Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 52px;
            height: 28px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--border-color);
            transition: 0.3s;
            border-radius: 28px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: var(--primary);
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .settings-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: var(--hover-bg);
        }

        .btn-save {
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.9rem;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }

        /* Bottom Bar */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            border-top: 1px solid var(--border-color);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .bottom-bar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .version-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(99, 102, 241, 0.3);
        }

        .version-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        /* Empty State */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-weight: 500;
            margin: 0;
        }

        /* Tooltip */
        .custom-tooltip {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 0.8rem;
            font-family: 'JetBrains Mono', monospace;
            max-width: 400px;
            box-shadow: var(--card-shadow);
        }

        /* Utilities */
        [x-cloak] {
            display: none !important;
        }

        /* Dark Mode Adjustments */
        .dark-mode .btn-close {
            filter: invert(1);
        }

        /* Responsive */
        @media (max-width: 991px) {
            .page-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .stats-badge {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .logo-text h1 {
                font-size: 1.2rem;
            }
            
            .stat-item {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <!-- Background Decoration -->
    <div class="bg-decoration"></div>

    <div class="container py-2" x-data="explorerApp()" x-init="init()">
        <!-- Header -->
        <div class="page-header mb-0">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div class="logo-text">
                    <h1>Directory Explorer</h1>
                    <span>Navigate your file system with ease</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="stats-badge">
                    <div class="stat-item folders">
                        <i class="fas fa-folder"></i>
                        <span><?= count($directories) ?> folders</span>
                    </div>
                    <div class="stat-item files">
                        <i class="fas fa-file"></i>
                        <span><?= count($files) ?> files</span>
                    </div>
                </div>
                <button @click="openSettings()" class="settings-btn">
                    <i class="fas fa-cog"></i>
                </button>
                <button @click="toggleDarkMode()" class="theme-toggle">
                    <i class="fas" :class="darkMode ? 'fa-sun text-warning' : 'fa-moon'"></i>
                </button>
            </div>
        </div>
        
        <!-- Combined Breadcrumb & Path Bar -->
        <nav class="breadcrumb-path-bar mb-3" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="?path=<?= htmlspecialchars(rawurlencode($baseDir)) ?>">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <?php foreach ($pathParts as $index => $part): ?>
                    <li class="breadcrumb-item <?= ($index === count($pathParts) - 1) ? 'active' : '' ?>">
                        <?php if ($index === count($pathParts) - 1): ?>
                            <?= htmlspecialchars($part['name']) ?>
                        <?php else: ?>
                            <a href="?path=<?= htmlspecialchars(rawurlencode($part['path'])) ?>">
                                <?= htmlspecialchars($part['name']) ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="current-path-mini">
                <i class="fas fa-terminal"></i>
                <span><?= htmlspecialchars($currentPath) ?></span>
            </div>
        </nav>

        <div class="row g-4">
            <!-- Directory List -->
            <div class="col-lg-6">
                <div class="glass-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 d-flex align-items-center gap-2">
                            <span class="icon-wrapper folder-icon-wrapper" style="width:32px;height:32px;">
                                <i class="fas fa-folder"></i>
                            </span>
                            Directories
                        </h5>
                        <?php if ($currentPath !== $baseDir): ?>
                            <a href="?path=<?= htmlspecialchars(rawurlencode($parentPath)) ?>" class="btn-back">
                                <i class="fas fa-arrow-up"></i>
                                Up
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="directory-container">
                        <?php if (!empty($directories)): ?>
                            <?php foreach ($directories as $dir): ?>
                                <div class="directory-item <?= $dir['is_hidden'] ? 'hidden-item' : '' ?>"
                                    x-on:mouseenter="showTooltip = true; tooltipContent = '<?= htmlspecialchars($dir['path']) ?>'; tooltipPosition = { x: $event.pageX, y: $event.pageY };"
                                    x-on:mouseleave="showTooltip = false">
                                    
                                    <!-- Web Access Icon -->
                                    <?php if (!empty($dir['url'])): ?>
                                        <a href="<?= htmlspecialchars($dir['url']) ?>" target="_blank" 
                                           class="web-icon-wrapper" title="Open in browser"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="web-icon-wrapper disabled">
                                            <i class="fas fa-globe-americas"></i>
                                        </span>
                                    <?php endif; ?>

                                    <div class="dir-name"
                                        onclick="window.location.href='?path=<?= htmlspecialchars(rawurlencode($dir['path'])) ?><?= $show_hidden ? '&show_hidden=1' : '' ?>'">
                                        <span class="icon-wrapper folder-icon-wrapper">
                                            <i class="fas fa-folder"></i>
                                        </span>
                                        <span><?= htmlspecialchars($dir['name']) ?></span>
                                        <?php if ($dir['is_hidden']): ?>
                                            <i class="fas fa-eye-slash hidden-badge" title="Hidden directory"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Meta Information -->
                                    <div class="item-meta">
                                        <?php
                                        $permsClass = 'perms-low';
                                        if (substr($dir['perms']['octal'], -1) == '7') {
                                            $permsClass = 'perms-high';
                                        } elseif (substr($dir['perms']['octal'], -1) >= '5') {
                                            $permsClass = 'perms-medium';
                                        }
                                        ?>
                                        <span class="perms-badge <?= $permsClass ?>" 
                                              title="<?= $dir['perms']['symbolic'] ?>"
                                              x-show="settings.showPermissions">
                                            <?= $dir['perms']['octal'] ?>
                                        </span>
                                        <span class="owner-badge <?= $dir['owner']['is_root'] ? 'owner-root' : 'owner-user' ?>" 
                                              title="UID: <?= $dir['owner']['uid'] ?> / GID: <?= $dir['owner']['gid'] ?>"
                                              x-show="settings.showOwners">
                                            <i class="fas <?= $dir['owner']['is_root'] ? 'fa-user-shield' : 'fa-user' ?>"></i>
                                            <?= htmlspecialchars($dir['owner']['owner']) ?>:<?= htmlspecialchars($dir['owner']['group']) ?>
                                        </span>
                                        <span class="size-badge" x-show="settings.showSizes">
                                            <?= $dir['formatted_size'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No directories found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- File List -->
            <div class="col-lg-6">
                <div class="glass-card">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex align-items-center gap-2">
                            <span class="icon-wrapper file-icon-wrapper" style="width:32px;height:32px;">
                                <i class="fas fa-file-alt"></i>
                            </span>
                            Files
                        </h5>
                    </div>
                    <div class="file-container">
                        <?php if (!empty($files)): ?>
                            <?php foreach ($files as $file): ?>
                                <div class="file-item <?= $file['is_hidden'] ? 'hidden-item' : '' ?>"
                                    x-on:mouseenter="showTooltip = true; tooltipContent = '<?= htmlspecialchars($file['path']) ?>'; tooltipPosition = { x: $event.pageX, y: $event.pageY };"
                                    x-on:mouseleave="showTooltip = false">
                                    
                                    <!-- Web Access Icon -->
                                    <?php if (!empty($file['url'])): ?>
                                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" 
                                           class="web-icon-wrapper" title="Open in browser">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="web-icon-wrapper disabled">
                                            <i class="fas fa-globe-americas"></i>
                                        </span>
                                    <?php endif; ?>

                                    <span class="icon-wrapper file-icon-wrapper">
                                        <i class="fas fa-file"></i>
                                    </span>
                                    <span class="file-name">
                                        <?= htmlspecialchars($file['name']) ?>
                                        <?php if ($file['is_hidden']): ?>
                                            <i class="fas fa-eye-slash hidden-badge" title="Hidden file"></i>
                                        <?php endif; ?>
                                    </span>

                                    <!-- Meta Information -->
                                    <div class="item-meta">
                                        <?php
                                        $permsClass = 'perms-low';
                                        if (substr($file['perms']['octal'], -1) == '7') {
                                            $permsClass = 'perms-high';
                                        } elseif (substr($file['perms']['octal'], -1) >= '5') {
                                            $permsClass = 'perms-medium';
                                        }
                                        ?>
                                        <span class="perms-badge <?= $permsClass ?>" 
                                              title="<?= $file['perms']['symbolic'] ?>"
                                              x-show="settings.showPermissions">
                                            <?= $file['perms']['octal'] ?>
                                        </span>
                                        <span class="owner-badge <?= $file['owner']['is_root'] ? 'owner-root' : 'owner-user' ?>" 
                                              title="UID: <?= $file['owner']['uid'] ?> / GID: <?= $file['owner']['gid'] ?>"
                                              x-show="settings.showOwners">
                                            <i class="fas <?= $file['owner']['is_root'] ? 'fa-user-shield' : 'fa-user' ?>"></i>
                                            <?= htmlspecialchars($file['owner']['owner']) ?>:<?= htmlspecialchars($file['owner']['group']) ?>
                                        </span>
                                        <span class="size-badge" x-show="settings.showSizes">
                                            <?= $file['formatted_size'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file"></i>
                                <p>No files found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div class="settings-modal" x-show="showSettings" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.self="cancelSettings()">
            <div class="settings-modal-content"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-90"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 @click.stop>
                <div class="settings-header">
                    <h5>
                        <i class="fas fa-sliders-h"></i>
                        Settings
                    </h5>
                    <button type="button" class="btn-close" @click="cancelSettings()"></button>
                </div>
                <div class="settings-body">
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-icon size">
                                <i class="fas fa-database"></i>
                            </div>
                            <span class="setting-label">Show Sizes</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" x-model="tempSettings.showSizes">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-icon perms">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <span class="setting-label">Show Permissions</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" x-model="tempSettings.showPermissions">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-icon hidden">
                                <i class="fas fa-eye"></i>
                            </div>
                            <span class="setting-label">Show Hidden Files</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" x-model="tempSettings.showHiddenFiles">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-icon owner">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <span class="setting-label">Show Owners</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" x-model="tempSettings.showOwners">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <!-- Info message about hidden files -->
                    <div class="settings-info-message" x-show="tempSettings.showHiddenFiles !== settings.showHiddenFiles">
                        <i class="fas fa-info-circle"></i>
                        <span>Changing hidden files visibility will reload the page</span>
                    </div>
                </div>
                <div class="settings-footer">
                    <button type="button" class="btn-cancel" @click="cancelSettings()">Cancel</button>
                    <button type="button" class="btn-save" @click="saveSettings()">
                        <i class="fas fa-check me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Custom Tooltip -->
        <div x-cloak x-show="showTooltip" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             :style="`position: fixed; left: ${tooltipPosition.x + 15}px; top: ${tooltipPosition.y + 10}px; z-index: 999;`"
             class="custom-tooltip">
            <span x-text="tooltipContent"></span>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="bottom-bar">
        <div class="bottom-bar-brand">
            <i class="fas fa-code"></i>
            <span>PHP Directory Explorer</span>
        </div>
        <div x-data="{ showVersionTooltip: false }" class="position-relative">
            <span class="version-badge"
                  @mouseenter="showVersionTooltip = true"
                  @mouseleave="showVersionTooltip = false">
                v<?= $VERSION_NUMBER ?> <?= $VERSION_STATUS ?>
            </span>
            
            <div x-show="showVersionTooltip" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 class="position-absolute bottom-100 end-0 mb-2">
                <div class="custom-tooltip text-nowrap">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Released: <?= date('F j, Y', strtotime($VERSION_DATE)) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Explorer App Script -->
    <script>
        function explorerApp() {
            return {
                // UI State
                showTooltip: false,
                tooltipContent: '',
                tooltipPosition: { x: 0, y: 0 },
                showSettings: false,
                
                // Settings with defaults
                settings: {
                    showSizes: true,
                    showPermissions: true,
                    showHiddenFiles: <?= $show_hidden ? 'true' : 'false' ?>,
                    showOwners: false
                },
                
                // Temporary settings for modal (to allow cancel)
                tempSettings: {
                    showSizes: true,
                    showPermissions: true,
                    showHiddenFiles: <?= $show_hidden ? 'true' : 'false' ?>,
                    showOwners: false
                },
                
                // Initialize the app
                init() {
                    // Load settings from localStorage
                    this.loadSettings();
                    
                    // Sync tempSettings with loaded settings
                    this.tempSettings = { ...this.settings };
                    
                    // Watch for settings changes and auto-save (except hidden files which needs reload)
                    this.$watch('settings.showSizes', (value) => {
                        localStorage.setItem('explorer_showSizes', JSON.stringify(value));
                    });
                    
                    this.$watch('settings.showPermissions', (value) => {
                        localStorage.setItem('explorer_showPermissions', JSON.stringify(value));
                    });
                    
                    this.$watch('settings.showOwners', (value) => {
                        localStorage.setItem('explorer_showOwners', JSON.stringify(value));
                    });
                },
                
                // Load settings from localStorage
                loadSettings() {
                    // Show Sizes - default to true
                    const savedShowSizes = localStorage.getItem('explorer_showSizes');
                    this.settings.showSizes = savedShowSizes !== null ? JSON.parse(savedShowSizes) : true;
                    
                    // Show Permissions - default to true
                    const savedShowPermissions = localStorage.getItem('explorer_showPermissions');
                    this.settings.showPermissions = savedShowPermissions !== null ? JSON.parse(savedShowPermissions) : true;
                    
                    // Show Owners - default to false
                    const savedShowOwners = localStorage.getItem('explorer_showOwners');
                    this.settings.showOwners = savedShowOwners !== null ? JSON.parse(savedShowOwners) : false;
                    
                    // Show Hidden Files - sync with URL parameter (handled by PHP)
                    // This is already set from PHP: <?= $show_hidden ? 'true' : 'false' ?>
                    // But we also save to localStorage for persistence
                    const savedShowHidden = localStorage.getItem('explorer_showHiddenFiles');
                    if (savedShowHidden !== null) {
                        const shouldShowHidden = JSON.parse(savedShowHidden);
                        // If localStorage says different than current URL state, we might need to redirect
                        // But we only do this on initial load if user hasn't explicitly set URL param
                        const urlParams = new URLSearchParams(window.location.search);
                        if (!urlParams.has('show_hidden') && shouldShowHidden !== this.settings.showHiddenFiles) {
                            // Auto-redirect to match saved preference
                            this.settings.showHiddenFiles = shouldShowHidden;
                            this.applyHiddenFilesSetting();
                            return; // Stop here, page will reload
                        }
                    }
                    
                    // Save current state
                    localStorage.setItem('explorer_showHiddenFiles', JSON.stringify(this.settings.showHiddenFiles));
                },
                
                // Toggle dark mode
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('darkMode', this.darkMode);
                },
                
                // Open settings modal
                openSettings() {
                    // Copy current settings to temp
                    this.tempSettings = { ...this.settings };
                    this.showSettings = true;
                },
                
                // Save settings from modal
                saveSettings() {
                    // Apply temp settings to actual settings
                    this.settings.showSizes = this.tempSettings.showSizes;
                    this.settings.showPermissions = this.tempSettings.showPermissions;
                    this.settings.showOwners = this.tempSettings.showOwners;
                    
                    // Save to localStorage
                    localStorage.setItem('explorer_showSizes', JSON.stringify(this.settings.showSizes));
                    localStorage.setItem('explorer_showPermissions', JSON.stringify(this.settings.showPermissions));
                    localStorage.setItem('explorer_showOwners', JSON.stringify(this.settings.showOwners));
                    localStorage.setItem('explorer_showHiddenFiles', JSON.stringify(this.tempSettings.showHiddenFiles));
                    
                    // Check if hidden files setting changed (requires page reload)
                    if (this.tempSettings.showHiddenFiles !== this.settings.showHiddenFiles) {
                        this.settings.showHiddenFiles = this.tempSettings.showHiddenFiles;
                        this.applyHiddenFilesSetting();
                    } else {
                        this.showSettings = false;
                    }
                },
                
                // Apply hidden files setting (requires page reload)
                applyHiddenFilesSetting() {
                    const currentPath = '<?= htmlspecialchars(rawurlencode($currentPath)) ?>';
                    const showHidden = this.settings.showHiddenFiles ? '1' : '0';
                    window.location.href = `?path=${currentPath}&show_hidden=${showHidden}`;
                },
                
                // Cancel settings changes
                cancelSettings() {
                    this.showSettings = false;
                }
            };
        }
    </script>
</body>
</html>