<?php
/**
 * PHP Directory Explorer - Helper Functions
 * 
 * Este archivo contiene todas las funciones auxiliares de la aplicación
 */

/**
 * Obtener ruta absoluta de forma segura
 */
function getAbsolutePath($path)
{
    return realpath($path) ?: $path;
}

/**
 * Convierte una ruta del sistema de archivos a una URL relativa
 */
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

/**
 * Obtener lista de directorios en una ruta dada
 */
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

            // Excluir directorios configurados
            if (in_array($item, EXCLUDED_DIRECTORIES)) {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath) && is_readable($fullPath)) {
                $relativeUrl = getRelativeUrl($fullPath);
                $perms = getPerms($fullPath);
                $size = getDirSize($fullPath, MAX_DIR_DEPTH);

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
        if (DEBUG_MODE) {
            error_log("Error reading directory $path: " . $e->getMessage());
        }
    }

    return $directories;
}

/**
 * Obtener lista de archivos en una ruta dada
 */
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

            // Excluir archivos configurados
            if (in_array($item, EXCLUDED_FILES)) {
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
                    'is_hidden' => $item[0] === '.',
                    'extension' => pathinfo($item, PATHINFO_EXTENSION)
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
        if (DEBUG_MODE) {
            error_log("Error reading files in $path: " . $e->getMessage());
        }
    }

    return $files;
}

/**
 * Calcular tamaño de directorio recursivamente (con límite para evitar sobrecarga)
 */
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
        if (DEBUG_MODE) {
            error_log("Error calculating directory size for $path: " . $e->getMessage());
        }
    }

    return $size;
}

/**
 * Obtener permisos en formato legible
 */
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

/**
 * Formatear tamaños de archivo
 */
function formatSize($bytes)
{
    $units = SIZE_UNITS;

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Detectar si estamos en modo de acceso root
 */
function detectRootAccess()
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    // Extraer la parte del path sin parámetros
    $requestPath = strtok($requestUri, '?');
    $scriptPath = dirname($scriptName);

    // Detectar si estamos en modo "root access"
    $isRootAccess = false;

    if (rtrim($requestPath, '/') !== rtrim($scriptPath, '/')) {
        // Si la ruta solicitada es diferente a la ruta del script, estamos en modo rewrite
        $isRootAccess = (
            str_ends_with($requestPath, '/') || 
            str_ends_with($requestPath, '/index.php')
        );
    }

    return [
        'isRootAccess' => $isRootAccess,
        'requestPath' => $requestPath,
        'scriptPath' => $scriptPath,
        'requestUri' => $requestUri,
        'scriptName' => $scriptName
    ];
}

/**
 * Configurar el directorio base según el contexto
 */
function setupBaseDirectory()
{
    $scriptDir = dirname(__FILE__);
    $accessInfo = detectRootAccess();
    
    if ($accessInfo['isRootAccess'] && !empty(ROOT_BASE_CONFIG)) {
        // Estamos en modo root access, usar la configuración personalizada
        if (ROOT_BASE_CONFIG[0] === '/') {
            // Ruta absoluta
            $baseDir = ROOT_BASE_CONFIG;
        } else {
            // Ruta relativa al directorio del script
            $baseDir = realpath($scriptDir . '/' . ROOT_BASE_CONFIG) ?: $scriptDir;
        }
    } else {
        // Acceso directo al PHPDirExplorer o configuración vacía
        $baseDir = $scriptDir;
    }

    $baseUrl = '';
    // Si estamos en un entorno web, intentar determinar la URL base
    if ($accessInfo['scriptPath'] !== '/' && $accessInfo['scriptPath'] !== '\\') {
        $baseUrl = $accessInfo['scriptPath'];
    }

    return [
        'baseDir' => $baseDir,
        'baseUrl' => $baseUrl,
        'scriptDir' => $scriptDir,
        'accessInfo' => $accessInfo
    ];
}

/**
 * Generar información de debug si está habilitado
 */
function getDebugInfo($baseDir, $baseUrl, $scriptDir, $accessInfo)
{
    if (!DEBUG_MODE) {
        return '';
    }

    return "<!-- DEBUG INFO
Request URI: " . $accessInfo['requestUri'] . "
Request Path: " . $accessInfo['requestPath'] . "
Script Name: " . $accessInfo['scriptName'] . "
Script Path: " . $accessInfo['scriptPath'] . "
Script Dir: $scriptDir
Is Root Access: " . ($accessInfo['isRootAccess'] ? 'YES' : 'NO') . "
Base Dir: $baseDir
Base URL: $baseUrl
Root Base Config: " . ROOT_BASE_CONFIG . "
-->";
}

/**
 * Validar y sanitizar el path actual
 */
function validateCurrentPath($requestedPath, $baseDir)
{
    $currentPath = isset($requestedPath) ? $requestedPath : $baseDir;
    $currentPath = getAbsolutePath($currentPath);

    // Validar que el directorio existe y es accesible
    if (!file_exists($currentPath) || !is_dir($currentPath)) {
        $currentPath = $baseDir;
    }

    // Validar que el path está dentro del directorio base (seguridad)
    if (strpos($currentPath, getAbsolutePath($baseDir)) !== 0) {
        $currentPath = $baseDir;
    }

    return $currentPath;
}

/**
 * Obtener el path padre validado
 */
function getValidParentPath($currentPath, $baseDir)
{
    $parentPath = dirname($currentPath);
    if (strpos(getAbsolutePath($parentPath), getAbsolutePath($baseDir)) !== 0) {
        $parentPath = $baseDir;
    }
    return $parentPath;
}

/**
 * Generar breadcrumb de navegación
 */
function generatePathBreadcrumb($currentPath, $baseDir)
{
    $pathParts = [];
    $tempPath = $currentPath;
    
    while (strpos($tempPath, $baseDir) === 0 && $tempPath !== $baseDir) {
        array_unshift($pathParts, [
            'name' => basename($tempPath),
            'path' => $tempPath
        ]);
        $tempPath = dirname($tempPath);
    }
    
    return $pathParts;
}