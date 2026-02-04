<?php
/**
 * File Upload Utility Class
 * Handles file uploads for photos
 */

class FileUpload {
    
    private $uploadDir = __DIR__ . '/../../public/uploads/';
    private $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    private $maxFileSize = 5242880; // 5MB in bytes
    
    /**
     * Upload photo
     * @param array $file - $_FILES array element
     * @param string $type - 'places' or 'checkpoints'
     * @return array - ['success' => bool, 'file_path' => string, 'file_name' => string, 'error' => string]
     */
    public function uploadPhoto($file, $type = 'places') {
        // Create upload directory if it doesn't exist
        $typeDir = $this->uploadDir . $type . '/';
        if (!file_exists($typeDir)) {
            mkdir($typeDir, 0777, true);
        }
        
        // Validate file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded.'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
        }
        
        // Validate file type
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and WebP images allowed.'];
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => 'File size exceeds 5MB limit.'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $typeDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Return relative path for database storage
            $relativePath = 'uploads/' . $type . '/' . $fileName;
            
            return [
                'success' => true,
                'file_path' => $relativePath,
                'file_name' => $fileName,
                'file_size' => $file['size']
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file.'];
        }
    }
    
    /**
     * Delete photo
     * @param string $filePath - Relative file path
     * @return bool
     */
    public function deletePhoto($filePath) {
        $fullPath = __DIR__ . '/../../public/' . $filePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
    
    /**
     * Validate image dimensions (optional)
     * @param string $filePath
     * @param int $minWidth
     * @param int $minHeight
     * @return bool
     */
    public function validateDimensions($filePath, $minWidth = 800, $minHeight = 600) {
        $imageInfo = getimagesize($filePath);
        
        if ($imageInfo === false) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        return ($width >= $minWidth && $height >= $minHeight);
    }
}
?>