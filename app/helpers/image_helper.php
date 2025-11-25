<?php

class ImageHelper {
    private static $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];
    
    /**
     * Upload and process image with preview
     */
    public static function uploadImage(array $file, string $targetDir, array $options = []): ?array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        // Validate mime type
        $mime = mime_content_type($file['tmp_name']);
        if (!isset(self::$allowedMimeTypes[$mime])) {
            throw new \Exception('Loại file không hợp lệ. Chỉ chấp nhận: JPG, PNG, WEBP, GIF');
        }
        
        // Get extension
        $ext = self::$allowedMimeTypes[$mime];
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        
        // Create directory if not exists
        $fullTargetDir = $_SERVER['DOCUMENT_ROOT'] . '/' . rtrim($targetDir, '/') . '/';
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }
        
        $filepath = $fullTargetDir . $filename;
        $relativePath = rtrim($targetDir, '/') . '/' . $filename;
        
        // Get image dimensions
        list($width, $height) = getimagesize($file['tmp_name']);
        
        // Auto resize if too large
        $maxWidth = $options['max_width'] ?? 1920;
        $maxHeight = $options['max_height'] ?? 1920;
        
        if ($width > $maxWidth || $height > $maxHeight) {
            $resized = self::resizeImage($file['tmp_name'], $filepath, $maxWidth, $maxHeight, $mime);
            if (!$resized) {
                move_uploaded_file($file['tmp_name'], $filepath);
            }
        } else {
            move_uploaded_file($file['tmp_name'], $filepath);
        }
        
        // Create thumbnail
        $thumbPath = null;
        if ($options['create_thumb'] ?? true) {
            $thumbPath = self::createThumbnail($filepath, $relativePath, 300, 300);
        }
        
        return [
            'path' => $relativePath,
            'thumb' => $thumbPath,
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
            'size' => filesize($filepath),
            'mime' => $mime
        ];
    }
    
    /**
     * Resize image maintaining aspect ratio
     */
    public static function resizeImage(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight, string $mime): bool {
        list($origWidth, $origHeight) = getimagesize($sourcePath);
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        // Create image resource based on mime type
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) return false;
        
        // Create new image
        $dest = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Save based on mime type
        $success = false;
        switch ($mime) {
            case 'image/jpeg':
                $success = imagejpeg($dest, $destPath, 90);
                break;
            case 'image/png':
                $success = imagepng($dest, $destPath, 9);
                break;
            case 'image/webp':
                $success = imagewebp($dest, $destPath, 90);
                break;
            case 'image/gif':
                $success = imagegif($dest, $destPath);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($dest);
        
        return $success;
    }
    
    /**
     * Create thumbnail
     */
    public static function createThumbnail(string $sourcePath, string $originalRelativePath, int $width, int $height): ?string {
        $pathInfo = pathinfo($sourcePath);
        $thumbFilename = $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        $thumbPath = $pathInfo['dirname'] . '/' . $thumbFilename;
        
        $mime = mime_content_type($sourcePath);
        if (self::resizeImage($sourcePath, $thumbPath, $width, $height, $mime)) {
            // Return relative path
            $relativeDir = dirname($originalRelativePath);
            return $relativeDir . '/' . $thumbFilename;
        }
        
        return null;
    }
    
    /**
     * Crop image to specific dimensions
     */
    public static function cropImage(string $sourcePath, string $destPath, int $x, int $y, int $width, int $height): bool {
        $mime = mime_content_type($sourcePath);
        
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) return false;
        
        $dest = imagecreatetruecolor($width, $height);
        
        // Preserve transparency
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $width, $height, $transparent);
        }
        
        imagecopy($dest, $source, 0, 0, $x, $y, $width, $height);
        
        $success = false;
        switch ($mime) {
            case 'image/jpeg':
                $success = imagejpeg($dest, $destPath, 90);
                break;
            case 'image/png':
                $success = imagepng($dest, $destPath, 9);
                break;
            case 'image/webp':
                $success = imagewebp($dest, $destPath, 90);
                break;
            case 'image/gif':
                $success = imagegif($dest, $destPath);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($dest);
        
        return $success;
    }
    
    /**
     * Get placeholder image path
     */
    public static function getPlaceholder(string $type = 'service'): string {
        $placeholders = [
            'service' => '/public/images/placeholder-service.png',
            'user' => '/public/images/placeholder-user.png',
            'product' => '/public/images/placeholder-product.png'
        ];
        
        return $placeholders[$type] ?? $placeholders['service'];
    }
    
    /**
     * Delete image and its thumbnail
     */
    public static function deleteImage(string $relativePath): bool {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $relativePath;
        $deleted = false;
        
        if (file_exists($fullPath)) {
            $deleted = unlink($fullPath);
            
            // Also delete thumbnail
            $pathInfo = pathinfo($fullPath);
            $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
        }
        
        return $deleted;
    }
    
    /**
     * Validate image dimensions
     */
    public static function validateDimensions(string $filePath, int $minWidth = 0, int $minHeight = 0, int $maxWidth = PHP_INT_MAX, int $maxHeight = PHP_INT_MAX): bool {
        list($width, $height) = getimagesize($filePath);
        
        return $width >= $minWidth && $width <= $maxWidth && 
               $height >= $minHeight && $height <= $maxHeight;
    }
}
