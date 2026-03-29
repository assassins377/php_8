<?php

declare(strict_types=1);

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Image Upload Service
 * 
 * Handles secure image upload, validation, and processing
 */
class UploadService
{
    private string $uploadPath;
    private int $maxSize;
    private array $allowedExtensions;
    private array $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    
    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);
        $this->uploadPath = $basePath . '/storage/uploads';
        $this->maxSize = (int) ($_ENV['MAX_FILE_SIZE'] ?? 5242880); // 5MB default
        $this->allowedExtensions = array_map('trim', explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,webp'));
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload and process image file
     * 
     * @param array $file File from $_FILES
     * @return array Result with success status and file path or error message
     */
    public function uploadImage(array $file): array
    {
        // Validate upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error($this->getUploadErrorMessage($file['error']));
        }
        
        // Validate file size
        if ($file['size'] > $this->maxSize) {
            return $this->error('Размер файла превышает допустимый лимит');
        }
        
        // Validate file is not empty
        if ($file['size'] === 0) {
            return $this->error('Файл пуст');
        }
        
        // Get real MIME type using finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        // Validate MIME type
        if (!isset($this->allowedMimeTypes[$mimeType])) {
            return $this->error('Недопустимый тип файла');
        }
        
        // Validate extension matches MIME type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $expectedExtension = $this->allowedMimeTypes[$mimeType];
        
        if (!in_array($expectedExtension, $this->allowedExtensions, true)) {
            return $this->error('Тип файла не разрешён');
        }
        
        // Generate secure filename
        $filename = $this->generateFilename($expectedExtension);
        $destination = $this->uploadPath . '/' . $filename;
        
        // Process image (resize, strip EXIF)
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file['tmp_name']);
            
            // Resize if too large (max 1920px width)
            $image->scale(1920, 1920);
            
            // Save without EXIF data
            $image->save($destination);
            
            // Set proper permissions
            chmod($destination, 0644);
            
            return $this->success($filename);
        } catch (\Exception $e) {
            error_log("Image processing error: " . $e->getMessage());
            
            // Clean up temp file if exists
            if (file_exists($destination)) {
                unlink($destination);
            }
            
            return $this->error('Ошибка обработки изображения');
        }
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile(string $filename): bool
    {
        // Prevent directory traversal
        $filename = basename($filename);
        $filepath = $this->uploadPath . '/' . $filename;
        
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Get full URL for uploaded file
     */
    public function getFileUrl(string $filename): string
    {
        $filename = basename($filename);
        return '/uploads/' . $filename;
    }
    
    /**
     * Get full path for uploaded file
     */
    public function getFilePath(string $filename): string
    {
        $filename = basename($filename);
        return $this->uploadPath . '/' . $filename;
    }
    
    /**
     * Generate secure random filename
     */
    private function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
    
    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает допустимый лимит',
            UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка прервана расширением PHP',
            default => 'Неизвестная ошибка загрузки',
        };
    }
    
    /**
     * Create success response
     */
    private function success(string $filename): array
    {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $this->getFileUrl($filename),
        ];
    }
    
    /**
     * Create error response
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
