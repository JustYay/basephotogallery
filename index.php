<?php
// Включаем отображение ошибок для отладки (можно убрать на продакшене)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройки
$notFoundImagePath = 'not_found.jpg'; // Путь к изображению при 404 ошибке
$excludedFiles = array('index.php', '.htaccess', '.', '..', '404_errors.log'); // Файлы, которые не нужно показывать
$currentDir = './'; // Текущая директория
$enableLogging = true; // Включить логирование
$logFile = './404_errors.log'; // Файл для логирования ошибок

// Функция для логирования ошибок - делаем более надежной
function logError($message) {
    global $enableLogging, $logFile;
    if ($enableLogging) {
        try {
            $logMessage = date('[Y-m-d H:i:s] ') . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown') . ' - ' . $message . "\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        } catch (Exception $e) {
            // Игнорируем ошибки при логировании
        }
    }
}

// Расширенное логирование информации о запросе и правах доступа
$server_info = array(
    'REQUEST_URI' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A',
    'REDIRECT_STATUS' => isset($_SERVER['REDIRECT_STATUS']) ? $_SERVER['REDIRECT_STATUS'] : 'N/A',
    'SCRIPT_FILENAME' => isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : 'N/A',
    'DOCUMENT_ROOT' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'N/A',
    'SERVER_SOFTWARE' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A'
);

// Проверяем права доступа к текущей директории
$currentPerms = "unknown";
try {
    $currentPerms = substr(sprintf('%o', fileperms('./')), -4);
} catch (Exception $e) {
    // Игнорируем ошибки
}
$server_info['PERMS_ROOT'] = $currentPerms;

// Проверяем права доступа к директории /photos/ если она существует
if (@is_dir('./photos')) {
    try {
        $photosPerms = substr(sprintf('%o', fileperms('./photos/')), -4);
        $server_info['PERMS_PHOTOS'] = $photosPerms;
    } catch (Exception $e) {
        // Игнорируем ошибки
    }
}

// Проверяем пользователя
try {
    $server_info['USER'] = function_exists('get_current_user') ? get_current_user() : 'unknown';
} catch (Exception $e) {
    // Игнорируем ошибки
}

logError("Расширенная информация о запросе: " . json_encode($server_info));

// Пробуем исправить права доступа на директории /photos/, если возможно
if (@is_dir('./photos') && !@is_readable('./photos')) {
    logError("Директория photos недоступна для чтения. Попытка исправить...");
    @chmod('./photos', 0755);
}

// Безопасно получаем URI запроса
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$redirectStatus = isset($_SERVER['REDIRECT_STATUS']) ? $_SERVER['REDIRECT_STATUS'] : 'не установлен';

// Логируем запрос
logError("Получен запрос: " . $requestUri . " | REDIRECT_STATUS: " . $redirectStatus . " | IP: " . $_SERVER['REMOTE_ADDR']);

// Предотвращение циклических редиректов для not_found.jpg
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$requestPath = urldecode(trim($requestPath, '/'));

// Проверка доступности файла 404
function checkNotFoundImage($path) {
    return @file_exists($path) && @is_file($path) && @is_readable($path);
}

// Функция для безопасного перенаправления на страницу 404
function redirectTo404() {
    global $notFoundImagePath;
    
    if (checkNotFoundImage($notFoundImagePath)) {
        header('Location: /' . $notFoundImagePath);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo "404 - Файл не найден";
    }
    exit;
}

// Если запрашивается напрямую not_found.jpg, просто показываем его без редиректов
if (strtolower($requestPath) === strtolower($notFoundImagePath)) {
    if (checkNotFoundImage($notFoundImagePath)) {
        $contentType = 'image/jpeg';
        // Определим правильный Content-Type на основе расширения
        $ext = strtolower(pathinfo($notFoundImagePath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $contentType = 'image/png';
        } elseif ($ext === 'gif') {
            $contentType = 'image/gif';
        } elseif ($ext === 'webp') {
            $contentType = 'image/webp';
        }
        
        header('Content-Type: ' . $contentType);
        @readfile($notFoundImagePath);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo "404 - Файл не найден";
    }
    exit;
}

// Проверка, является ли текущий запрос запросом к файлу
$isFileRequest = false;
$extension = pathinfo($requestPath, PATHINFO_EXTENSION);
if (!empty($extension)) {
    $isFileRequest = true;
}

// Функция для поиска директории без учета регистра
function findCaseInsensitiveDir($dir, $searchDir) {
    if (!@is_dir($dir) || !@is_readable($dir)) return false;
    
    try {
        $items = @scandir($dir);
        if ($items === false) return false;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            if (@is_dir($dir . '/' . $item) && strtolower($item) === strtolower($searchDir)) {
                return $item; // Возвращаем реальное имя директории с учетом регистра
            }
        }
    } catch (Exception $e) {
        return false;
    }
    
    return false;
}

// Функция для проверки наличия файла (регистронезависимая)
function fileExistsCaseInsensitive($filepath) {
    try {
        if (@file_exists($filepath)) {
            return $filepath;
        }
        
        $dirName = dirname($filepath);
        $fileName = basename($filepath);
        
        if (!@is_dir($dirName) || !@is_readable($dirName)) {
            return false;
        }
        
        $files = @scandir($dirName);
        if ($files === false) return false;
        
        foreach ($files as $file) {
            if (strtolower($file) === strtolower($fileName)) {
                return $dirName . '/' . $file;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    
    return false;
}

// Обработка ошибок 403 и 404
if (isset($_SERVER['REDIRECT_STATUS'])) {
    if ($_SERVER['REDIRECT_STATUS'] == 403) {
        logError("Обрабатываю ошибку 403 Forbidden для: " . $requestUri);
        
        // Получаем директорию из запроса
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = urldecode(trim($requestPath, '/'));
        
        // Пытаемся обработать как директорию
        if (!empty($requestPath)) {
            $pathToCheck = './' . $requestPath;
            logError("Пытаюсь обработать restricted path: " . $pathToCheck);
            
            // Если это директория, отображаем ее содержимое через наш скрипт
            if (@is_dir($pathToCheck)) {
                logError("Директория найдена: " . $pathToCheck);
                
                // Устанавливаем $subdir для обработки далее в коде
                $subdir = $requestPath;
                $currentDir = $pathToCheck;
                
                // Получаем содержимое директории нашими безопасными методами
                $allItems = safeReadDir($currentDir);
                $directories = array();
                $files = array();
                
                foreach ($allItems as $item) {
                    if (in_array($item, $excludedFiles)) {
                        continue;
                    }
                    
                    $path = $currentDir . '/' . $item;
                    
                    if (@is_dir($path)) {
                        $directories[] = $item;
                    } else if (@is_file($path)) {
                        $files[] = $item;
                    }
                }
                
                // Сортировка
                sort($directories);
                sort($files);
                
                // Продолжаем выполнение обычного кода для отображения директории
            } else {
                logError("Директория не найдена, перенаправляю на 404: " . $pathToCheck);
                redirectTo404();
            }
        }
    } else if ($_SERVER['REDIRECT_STATUS'] == 404) {
        // Обработка 404 ошибки
        logError("Обрабатываю ошибку 404 для: " . $requestUri);
        
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = urldecode(trim($requestPath, '/'));
        
        // Проверяем запрос к файлу
        if ($isFileRequest) {
            // Попытка найти файл с учетом регистра
            $realFile = fileExistsCaseInsensitive('./' . $requestPath);
            if ($realFile) {
                // Если файл найден с другим регистром, редиректим на него
                header('Location: /' . substr($realFile, 2)); // Убираем './' из начала пути
                exit;
            }
            
            // Проверяем, существует ли директория для этого файла
            $filePath = pathinfo($requestPath, PATHINFO_DIRNAME);
            $fileName = pathinfo($requestPath, PATHINFO_BASENAME);
            
            // Пытаемся найти директорию с учетом регистра
            $foundPath = '';
            $currentCheckDir = './';
            $dirParts = explode('/', $filePath);
            $validDirPath = true;
            
            foreach ($dirParts as $part) {
                if (empty($part)) continue;
                
                $realDirName = findCaseInsensitiveDir($currentCheckDir, $part);
                if ($realDirName) {
                    $foundPath .= '/' . $realDirName;
                    $currentCheckDir .= '/' . $realDirName;
                } else {
                    $validDirPath = false;
                    break;
                }
            }
            
            // Если директория существует, проверяем файл в ней
            if ($validDirPath && $foundPath) {
                $checkFilePath = '.' . $foundPath . '/' . $fileName;
                $realFileName = false;
                
                if (@is_dir('.' . $foundPath) && @is_readable('.' . $foundPath)) {
                    $dirFiles = safeReadDir('.' . $foundPath);
                    foreach ($dirFiles as $file) {
                        if (strtolower($file) === strtolower($fileName)) {
                            $realFileName = $file;
                            break;
                        }
                    }
                    
                    if ($realFileName) {
                        // Файл найден с другим регистром
                        header('Location: ' . $foundPath . '/' . $realFileName);
                        exit;
                    }
                }
            } else {
                // Директория не существует - явная обработка случая несуществующего файла в несуществующей директории
                redirectTo404();
            }
            
            // Если файл не найден, перенаправляем на not_found.jpg
            redirectTo404();
        }
        
        // Проверяем, существует ли директория с таким же именем, но другим регистром
        $pathParts = explode('/', $requestPath);
        $foundPath = '';
        $currentCheckDir = './';
        $redirectNeeded = false;
        
        foreach ($pathParts as $part) {
            if (empty($part)) continue;
            
            $realDirName = findCaseInsensitiveDir($currentCheckDir, $part);
            if ($realDirName) {
                $foundPath .= '/' . $realDirName;
                $currentCheckDir .= '/' . $realDirName;
                $redirectNeeded = true;
            } else {
                $redirectNeeded = false;
                break;
            }
        }
        
        if ($redirectNeeded) {
            header('Location: ' . $foundPath);
            exit;
        }
        
        // Если директория не найдена даже с учетом регистра, показываем 404
        redirectTo404();
    }
}

// Функция для получения расширения файла
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Функция для форматирования размера файла
function formatFileSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Функция для безопасного сканирования директории
function safeReadDir($dir) {
    try {
        if (!@is_dir($dir)) {
            logError("Не удалось прочитать директорию (не директория): " . $dir);
            return array();
        }
        
        if (!@is_readable($dir)) {
            logError("Не удалось прочитать директорию (нет прав на чтение): " . $dir);
            // В случае отсутствия прав, возвращаем пустой массив, но не прерываем выполнение
            return array();
        }
        
        $result = @scandir($dir);
        if ($result === false) {
            logError("Не удалось выполнить scandir для: " . $dir);
            return array();
        }
        
        return $result;
    } catch (Exception $e) {
        logError("Исключение при чтении директории: " . $dir . " - " . $e->getMessage());
        return array();
    }
}

// Получение и сортировка всех файлов и директорий
$allItems = safeReadDir($currentDir);
$directories = array();
$files = array();

foreach ($allItems as $item) {
    if (in_array($item, $excludedFiles)) {
        continue;
    }
    
    $path = $currentDir . '/' . $item;
    
    if (@is_dir($path)) {
        $directories[] = $item;
    } else if (@is_file($path)) {
        $files[] = $item;
    }
}

// Сортировка
sort($directories);
sort($files);

// Определение подкаталога, если есть
$subdir = '';
if (isset($_GET['dir'])) {
    $requestedDir = $_GET['dir'];
    $requestedDir = str_replace('..', '', $requestedDir); // Защита от path traversal
    
    // Проверяем, существует ли директория с учетом регистра
    $pathParts = explode('/', $requestedDir);
    $validPath = '';
    $currentCheckDir = './';
    $validRequest = true;
    
    foreach ($pathParts as $part) {
        if (empty($part)) continue;
        
        $realDirName = findCaseInsensitiveDir($currentCheckDir, $part);
        if ($realDirName) {
            if ($validPath) {
                $validPath .= '/' . $realDirName;
            } else {
                $validPath = $realDirName;
            }
            $currentCheckDir .= '/' . $realDirName;
        } else {
            $validRequest = false;
            break;
        }
    }
    
    if ($validRequest && $validPath) {
        // Если путь найден, но с другим регистром, перенаправляем на правильный путь
        if ($validPath != $requestedDir) {
            header('Location: index.php?dir=' . urlencode($validPath));
            exit;
        }
        
        $subdir = $validPath;
        $currentDir .= $subdir;
        
        // Получаем содержимое подкаталога
        $allItems = safeReadDir($currentDir);
        $directories = array();
        $files = array();
        
        foreach ($allItems as $item) {
            if (in_array($item, $excludedFiles)) {
                continue;
            }
            
            $path = $currentDir . '/' . $item;
            
            if (@is_dir($path)) {
                $directories[] = $item;
            } else if (@is_file($path)) {
                $files[] = $item;
            }
        }
        
        // Сортировка
        sort($directories);
        sort($files);
    }
}

// Обработка прямого доступа к директории через URL (например, /photos/3D/)
// Проверяем, является ли запрос напрямую к директории
if (!isset($_GET['dir']) && $requestPath != "") {
    logError("Прямой доступ к ресурсу: " . $requestPath . " | isFileRequest: " . ($isFileRequest ? 'true' : 'false'));
    
    // Проверяем, существует ли директория физически (независимо от того, файл это или директория)
    $pathToCheck = './' . $requestPath;
    
    if (@is_dir($pathToCheck)) {
        logError("Ресурс существует как директория: " . $pathToCheck);
        
        // Проверяем права доступа
        if (!@is_readable($pathToCheck)) {
            logError("Директория недоступна для чтения: " . $pathToCheck);
            echo "Ошибка доступа: нет прав на чтение директории";
            exit;
        }
        
        // Установить $subdir и продолжить выполнение скрипта
        $subdir = $requestPath;
        $currentDir = './' . $subdir;
        
        // Получаем содержимое подкаталога
        $allItems = safeReadDir($currentDir);
        $directories = array();
        $files = array();
        
        foreach ($allItems as $item) {
            if (in_array($item, $excludedFiles)) {
                continue;
            }
            
            $path = $currentDir . '/' . $item;
            
            if (@is_dir($path)) {
                $directories[] = $item;
            } else if (@is_file($path)) {
                $files[] = $item;
            }
        }
        
        // Сортировка
        sort($directories);
        sort($files);
    }
    else if (@is_file($pathToCheck)) {
        logError("Ресурс существует как файл: " . $pathToCheck);
        
        // Если это файл, перенаправляем на него напрямую
        if (!@is_readable($pathToCheck)) {
            logError("Файл недоступен для чтения: " . $pathToCheck);
            echo "Ошибка доступа: нет прав на чтение файла";
            exit;
        }
        
        // Определяем тип файла
        $ext = strtolower(pathinfo($pathToCheck, PATHINFO_EXTENSION));
        $contentType = 'application/octet-stream'; // По умолчанию
        
        // Устанавливаем Content-Type на основе расширения
        if (in_array($ext, ['jpg', 'jpeg'])) {
            $contentType = 'image/jpeg';
        } elseif ($ext === 'png') {
            $contentType = 'image/png';
        } elseif ($ext === 'gif') {
            $contentType = 'image/gif';
        } elseif ($ext === 'webp') {
            $contentType = 'image/webp';
        } elseif ($ext === 'html' || $ext === 'htm') {
            $contentType = 'text/html';
        } elseif ($ext === 'txt') {
            $contentType = 'text/plain';
        }
        
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($pathToCheck));
        @readfile($pathToCheck);
        exit;
    }
    else {
        logError("Ресурс не существует физически: " . $pathToCheck);
        
        // Пытаемся найти директорию с учетом регистра
        $foundPath = '';
        $currentCheckDir = './';
        $pathParts = explode('/', $requestPath);
        $validDirPath = true;
        
        foreach ($pathParts as $part) {
            if (empty($part)) continue;
            
            $realDirName = findCaseInsensitiveDir($currentCheckDir, $part);
            if ($realDirName) {
                $foundPath .= '/' . $realDirName;
                $currentCheckDir .= '/' . $realDirName;
            } else {
                $validDirPath = false;
                break;
            }
        }
        
        if ($validDirPath && $foundPath) {
            logError("Найден правильный путь: " . $foundPath);
            header('Location: ' . $foundPath);
            exit;
        } else {
            logError("Ресурс не найден даже с учетом регистра: " . $requestPath);
            // Если ресурс не найден, показываем 404
            redirectTo404();
        }
    }
}

// HTML страница
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Галерея фотографий</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .breadcrumb {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #eee;
            border-radius: 4px;
        }
        .breadcrumb a {
            color: #337ab7;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
            width: 200px;
        }
        .item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .folder {
            color: #e6a800;
            font-size: 24px;
        }
        .file-icon {
            color: #3498db;
            font-size: 24px;
        }
        .item a {
            color: #333;
            text-decoration: none;
            display: block;
            word-break: break-all;
        }
        .item-name {
            margin-top: 10px;
            font-weight: bold;
        }
        .item-details {
            color: #777;
            font-size: 12px;
            margin-top: 5px;
        }
        .thumbnail {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <h1>Галерея фотографий</h1>
    
    <div class="breadcrumb">
        <a href="/">Главная</a>
        <?php if ($subdir): 
            // Разбиваем путь на части для создания навигационных хлебных крошек
            $pathParts = explode('/', $subdir);
            $cumulativePath = '';
            
            foreach ($pathParts as $part):
                if (empty($part)) continue;
                
                if ($cumulativePath) {
                    $cumulativePath .= '/' . $part;
                } else {
                    $cumulativePath = $part;
                }
        ?>
            / <a href="/<?php echo htmlspecialchars($cumulativePath); ?>/"><?php echo htmlspecialchars($part); ?></a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <?php if ($subdir): ?>
            <div class="item">
                <div class="folder">📁</div>
                <?php 
                // Вычисляем родительскую директорию
                $parentDir = dirname($subdir);
                if ($parentDir == '.' || $parentDir == '/') {
                    // Если это верхний уровень, возвращаемся на главную
                    $backUrl = '/';
                } else {
                    // Иначе на уровень выше
                    $backUrl = '/' . $parentDir . '/';
                }
                ?>
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="item-name">..</a>
                <div class="item-details">Вернуться назад</div>
            </div>
        <?php endif; ?>
        
        <?php foreach ($directories as $dir): ?>
            <div class="item">
                <div class="folder">📁</div>
                <?php 
                // Определяем правильный URL для директории
                if ($subdir) {
                    $dirUrl = $subdir . '/' . $dir . '/';
                } else {
                    $dirUrl = $dir . '/';
                }
                ?>
                <a href="/<?php echo htmlspecialchars($dirUrl); ?>" class="item-name">
                    <?php echo htmlspecialchars($dir); ?>
                </a>
                <div class="item-details">Директория</div>
            </div>
        <?php endforeach; ?>
        
        <?php foreach ($files as $file): ?>
            <div class="item">
                <div class="thumbnail">
                    <?php 
                    $ext = getFileExtension($file);
                    $filePath = ($subdir ? $subdir . '/' : '') . $file;
                    // Добавляем проверку существования файла
                    $fileExists = @file_exists($currentDir . '/' . $file);
                    
                    if ($fileExists && in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="/<?php echo htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($file); ?>">
                    <?php else: ?>
                        <div class="file-icon">📄</div>
                    <?php endif; ?>
                </div>
                <a href="/<?php echo htmlspecialchars($filePath); ?>" class="item-name" target="_blank">
                    <?php echo htmlspecialchars($file); ?>
                </a>
                <div class="item-details">
                    <?php 
                    // Безопасное получение размера файла
                    try {
                        $fileSize = @filesize($currentDir . '/' . $file);
                        if ($fileSize === false) {
                            echo "Недоступно";
                        } else {
                            echo formatFileSize($fileSize);
                        }
                    } catch (Exception $e) {
                        echo "Недоступно";
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html> 