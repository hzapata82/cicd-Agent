<?php
/**
 * 🛠️ cicd/Agent: Diagnostic Utility
 * Simple script to probe PHP environment and file permissions.
 */

header('Content-Type: application/json');

$response = [
    "status" => "success",
    "environment" => [
        "php_version" => phpversion(),
        "server_software" => $_SERVER['SERVER_SOFTWARE'],
        "document_root" => $_SERVER['DOCUMENT_ROOT'],
        "current_user" => get_current_user(),
        "uid" => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid()) : 'N/A'
    ],
    "filesystem" => [
        "current_working_dir" => getcwd(),
        "is_writable" => is_writable('.'),
        "disk_free_space" => disk_free_space('.'),
        "extensions" => [
            "zip" => extension_loaded('zip'),
            "curl" => extension_loaded('curl'),
            "openssl" => extension_loaded('openssl')
        ],
        "listing" => array_diff(scandir('.'), array('..', '.'))
    ],
    "server_vars" => [
        "remote_addr" => $_SERVER['REMOTE_ADDR'],
        "request_method" => $_SERVER['REQUEST_METHOD']
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
