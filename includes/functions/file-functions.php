<?php
/**
 * File and Signature Related Functions
 */

/**
 * Centralized File Upload Handler
 */
if (!function_exists('saveUpload')) {
    function saveUpload($fileKey, $prefix, $subDir = 'signatures')
    {
        if (!isset($_FILES[$fileKey]) || empty($_FILES[$fileKey]['name'][0]))
            return null;

        $files = $_FILES[$fileKey];
        $isMultiple = is_array($files['name']);
        $count = $isMultiple ? count($files['name']) : 1;
        $paths = [];

        $uploadDir = dirname(__DIR__, 2) . '/uploads/' . $subDir . '/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        for ($i = 0; $i < $count; $i++) {
            $error = $isMultiple ? $files['error'][$i] : $files['error'];
            $size = $isMultiple ? $files['size'][$i] : $files['size'];

            // 100MB Limit (100 * 1024 * 1024)
            if ($size > 104857600) {
                continue; // Skip files that are too large
            }

            if ($error === UPLOAD_ERR_OK) {
                $tmpName = $isMultiple ? $files['tmp_name'][$i] : $files['tmp_name'];
                $originalName = $isMultiple ? $files['name'][$i] : $files['name'];
                $fileName = uniqid() . '_' . $prefix . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $originalName);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $paths[] = 'uploads/' . $subDir . '/' . $fileName;
                }
            }
        }
        if (empty($paths))
            return null;
        return $isMultiple ? json_encode($paths) : $paths[0];
    }
}

/**
 * Signature Saving Helper (from Base64 data)
 */
if (!function_exists('saveSignature')) {
    function saveSignature($fileKey, $dataKey, $prefix)
    {
        // Check for file upload first
        $path = saveUpload($fileKey, $prefix, 'signatures');
        if ($path)
            return $path;

        // Fallback to base64 data (signature pad)
        if (!empty($_POST[$dataKey])) {
            $data = $_POST[$dataKey];
            $data = str_replace('data:image/png;base64,', '', $data);
            $data = str_replace(' ', '+', $data);
            $decodedData = base64_decode($data);
            $fileName = uniqid() . '_' . $prefix . '_signature.png';
            $filePath = dirname(__DIR__, 2) . '/uploads/signatures/' . $fileName;

            if (!is_dir(dirname($filePath)))
                mkdir(dirname($filePath), 0777, true);
            if (file_put_contents($filePath, $decodedData)) {
                return 'uploads/signatures/' . $fileName;
            }
        }
        return '';
    }
}

/**
 * Admin Signature Handler (Supports both file upload and base64 signature pad)
 */
if (!function_exists('saveAdminSignature')) {
    function saveAdminSignature($postDataKey, $prefix, $fileKey = null)
    {
        // 1. Check for file upload first if fileKey is provided
        if ($fileKey !== null && isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $path = saveUpload($fileKey, $prefix, 'signatures');
            if ($path)
                return $path;
        }

        // 2. Fallback to base64 data (signature pad)
        if (!empty($_POST[$postDataKey])) {
            $data = $_POST[$postDataKey];
            $data = str_replace('data:image/png;base64,', '', $data);
            $data = str_replace(' ', '+', $data);
            $decodedData = base64_decode($data);
            $fileName = uniqid() . '_' . $prefix . '_signature.png';
            $filePath = dirname(__DIR__, 2) . '/uploads/signatures/' . $fileName;

            if (!is_dir(dirname($filePath)))
                mkdir(dirname($filePath), 0777, true);
            if (file_put_contents($filePath, $decodedData)) {
                return 'uploads/signatures/' . $fileName;
            }
        }
        return '';
    }
}
