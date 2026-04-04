<?php
/**
 * 🚀 cicd/Agent: Deployment Bridge
 * Standalone Engine for Atomic Deployments in Shared Hosting.
 * 
 * Version: 2.0.0
 * Security: HMAC-SHA256 Validation
 * 
 * @author Henry Zapata (hzapata82)
 */

header('Content-Type: application/json');
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- CONFIGURATION ---
$config_file = 'deploy.json';
$config = [
    'webhook_secret' => getenv("WEBHOOK_SECRET") ?: 'default_insecure_secret',
    'allowed_actions' => ['deploy', 'backup', 'rollback', 'probe'],
    'backup_filename' => 'backup_latest.zip',
    'flatten_extracted' => true,
    'restart_passenger' => true,
    'exclude_patterns' => ['.git', 'venv', '__pycache__', 'node_modules', '.env_stable']
];

if (file_exists($config_file)) {
    $file_config = json_decode(file_get_contents($config_file), true);
    if ($file_config) {
        $config = array_merge($config, $file_config);
    }
}

// --- SECURITY VALIDATION (HMAC) ---
// We support both a simple token via GET (legacy) or HMAC signature via Headers (GitHub style)
$headers = getallheaders();
$github_signature = isset($headers['X-Hub-Signature-256']) ? $headers['X-Hub-Signature-256'] : '';
$raw_payload = file_get_contents('php://input');
$input_token = isset($_GET['token']) ? $_GET['token'] : '';

$is_authenticated = false;

// 1. Check GitHub HMAC Signature
if (!empty($github_signature)) {
    $expected_signature = 'sha256=' . hash_hmac('sha256', $raw_payload, $config['webhook_secret']);
    if (hash_equals($expected_signature, $github_signature)) {
        $is_authenticated = true;
    }
} 
// 2. Fallback to Simple Token (Legacy)
elseif (!empty($input_token) && hash_equals($config['webhook_secret'], $input_token)) {
    $is_authenticated = true;
}

if (!$is_authenticated) {
    http_response_code(403);
    die(json_encode([
        "status" => "error",
        "message" => "Authentication failed. Invalid signature or token."
    ]));
}

// --- UTILS ---

/**
 * Atomic ZIP extraction with flattening capability
 */
function extract_zip($zip_path, $dest_path, $flatten = true) {
    if (!file_exists($zip_path)) return false;
    $zip = new ZipArchive;
    if ($zip->open($zip_path) === TRUE) {
        // Temporary extraction to identify structure
        $temp_dir = 'tmp_extract_' . time();
        if (!is_dir($temp_dir)) mkdir($temp_dir);
        
        $zip->extractTo($temp_dir);
        $zip->close();

        // Check if we need to flatten (GitHub usually puts everything in a subfolder repo-branch-uuid)
        $items = array_diff(scandir($temp_dir), array('..', '.'));
        if ($flatten && count($items) === 1 && is_dir($temp_dir . '/' . current($items))) {
            $subfolder = $temp_dir . '/' . current($items);
            smart_copy($subfolder, $dest_path);
        } else {
            smart_copy($temp_dir, $dest_path);
        }

        // Cleanup
        rrmdir($temp_dir);
        return true;
    }
    return false;
}

/**
 * Smart move/copy to root
 */
function smart_copy($source, $dest) {
    $files = scandir($source);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $src_path = $source . '/' . $file;
        $dest_path = $dest . '/' . $file;

        if (is_dir($src_path)) {
            if (is_dir($dest_path)) rrmdir($dest_path);
            mkdir($dest_path);
            smart_copy($src_path, $dest_path);
        } else {
            if (file_exists($dest_path)) unlink($dest_path);
            rename($src_path, $dest_path);
        }
    }
}

/**
 * Create a full backup of the current installation
 */
function create_backup($source, $destination, $exclude_patterns = []) {
    if (!extension_loaded('zip') || !file_exists($source)) return false;
    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) return false;

    $source = realpath($source);
    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current, $key, $iterator) use ($exclude_patterns, $source) {
                    $relative_path = str_replace($source . '/', '', $current->getPathname());
                    foreach ($exclude_patterns as $pattern) {
                        if (strpos($relative_path, $pattern) !== false) return false;
                    }
                    return true;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = realpath($file);
            $local_path = str_replace($source . '/', '', $file);
            if (is_dir($file)) {
                $zip->addEmptyDir($local_path);
            } else if (is_file($file)) {
                $zip->addFromString($local_path, file_get_contents($file));
            }
        }
    }
    return $zip->close();
}

/**
 * Recursive Remove Directory
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}

/**
 * Trigger Passenger Restart
 */
function restart_passenger() {
    if (!is_dir('tmp')) @mkdir('tmp');
    return touch('tmp/restart.txt');
}

// --- ACTIONS ENGINE ---
$action = isset($_GET['action']) ? $_GET['action'] : 'deploy';
if (!in_array($action, $config['allowed_actions'])) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Forbidden action: $action"]));
}

$response = [
    "status" => "processing",
    "action" => $action,
    "timestamp" => date('Y-m-d H:i:s')
];

switch ($action) {
    case 'backup':
        if (create_backup('.', $config['backup_filename'], $config['exclude_patterns'])) {
            $response["status"] = "success";
            $response["message"] = "Backup created successfully.";
        } else {
            http_response_code(500);
            $response["status"] = "error";
            $response["message"] = "Failed to create backup. Check permissions and ZIP extension.";
        }
        break;

    case 'deploy':
        // Look for the most recent zip that isn't the backup
        $zips = glob("*.zip");
        $deploy_zip = null;
        arsort($zips); // Sort descending to get latest if multiple exist
        foreach ($zips as $zip) {
            if ($zip !== $config['backup_filename']) {
                $deploy_zip = $zip;
                break;
            }
        }

        if ($deploy_zip && extract_zip($deploy_zip, '.', $config['flatten_extracted'])) {
            if ($config['restart_passenger']) restart_passenger();
            $response["status"] = "success";
            $response["message"] = "Deployment successful from $deploy_zip.";
            $response["file"] = $deploy_zip;
            // Optionally delete the zip after deploy? 
            // unlink($deploy_zip);
        } else {
            http_response_code(500);
            $response["status"] = "error";
            $response["message"] = "No valid ZIP found for deployment or extraction failed.";
        }
        break;

    case 'rollback':
        if (extract_zip($config['backup_filename'], '.', false)) {
            if ($config['restart_passenger']) restart_passenger();
            $response["status"] = "success";
            $response["message"] = "Rollback successful from " . $config['backup_filename'];
        } else {
            http_response_code(500);
            $response["status"] = "error";
            $response["message"] = "No backup file found to rollback.";
        }
        break;

    case 'probe':
        $response["status"] = "success";
        $response["info"] = [
            'php_version' => phpversion(),
            'cwd' => getcwd(),
            'user' => get_current_user(),
            'write_permissions' => is_writable('.'),
            'zip_extension' => extension_loaded('zip'),
            'files' => array_diff(scandir('.'), array('..', '.'))
        ];
        break;
}

echo json_encode($response);
