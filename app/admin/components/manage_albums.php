<?php
/**
 * manage_albums.php - Quản lý Albums
 * Cho phép admin tạo, sửa, xóa albums và upload/xóa ảnh
 */

if (!isset($conn)) {
    die('Database connection required');
}

// CSRF token for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ALBUMS_BASE_DIR = __DIR__ . '/../../../public/images/albums';
$ALBUMS_BASE_URL = '/StygianBlue/public/images/albums';
$ALLOW_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$successMessage = '';
$errorMessage = '';

// ==================== HANDLERS ====================

// 1. TẠO ALBUM MỚI (có thể kèm upload ảnh)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        $errorMessage = '⚠️ CSRF token không hợp lệ';
    } else {
        $albumName = trim($_POST['album_name'] ?? '');
        if ($albumName === '') {
            $errorMessage = '⚠️ Tên album không được để trống';
        } elseif (preg_match('~[\\/:*?"<>|]~u', $albumName)) {
            $errorMessage = '⚠️ Tên album không được chứa các ký tự: \\, /, :, *, ?, ", <, >, |';
        } else {
            $albumPath = $ALBUMS_BASE_DIR . '/' . $albumName;
            if (is_dir($albumPath)) {
                $errorMessage = '⚠️ Album "' . htmlspecialchars($albumName) . '" đã tồn tại';
            } else {
                if (@mkdir($albumPath, 0755, true)) {
                    // Tạo thư mục cache cho thumbnails
                    @mkdir($albumPath . '/cache', 0755, true);
                    
                    // Upload ảnh nếu có
                    $uploadCount = 0;
                    if (!empty($_FILES['album_images']['name'][0])) {
                        foreach ($_FILES['album_images']['name'] as $key => $filename) {
                            if ($_FILES['album_images']['error'][$key] === UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                if (in_array($ext, $ALLOW_EXT)) {
                                    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
                                    $destination = $albumPath . '/' . $safeName;
                                    
                                    $counter = 1;
                                    while (file_exists($destination)) {
                                        $nameWithoutExt = pathinfo($safeName, PATHINFO_FILENAME);
                                        $destination = $albumPath . '/' . $nameWithoutExt . '_' . $counter . '.' . $ext;
                                        $counter++;
                                    }
                                    
                                    if (@move_uploaded_file($_FILES['album_images']['tmp_name'][$key], $destination)) {
                                        @chmod($destination, 0644);
                                        $uploadCount++;
                                    }
                                }
                            }
                        }
                    }
                    
                    if ($uploadCount > 0) {
                        $successMessage = '✅ Tạo album "' . htmlspecialchars($albumName) . '" thành công với ' . $uploadCount . ' ảnh';
                    } else {
                        $successMessage = '✅ Tạo album "' . htmlspecialchars($albumName) . '" thành công';
                    }
                } else {
                    $errorMessage = '⚠️ Không thể tạo album. Kiểm tra quyền ghi';
                }
            }
        }
    }
}

// 2. XÓA ALBUM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        $errorMessage = '⚠️ CSRF token không hợp lệ';
    } else {
        $albumName = trim($_POST['album_name'] ?? '');
        $albumPath = $ALBUMS_BASE_DIR . '/' . $albumName;
        if (!is_dir($albumPath)) {
            $errorMessage = '⚠️ Album không tồn tại';
        } else {
            // Xóa toàn bộ thư mục và nội dung
            function deleteDirectory($dir) {
                if (!is_dir($dir)) return false;
                $items = array_diff(scandir($dir), ['.', '..']);
                foreach ($items as $item) {
                    $path = $dir . '/' . $item;
                    is_dir($path) ? deleteDirectory($path) : @unlink($path);
                }
                return @rmdir($dir);
            }
            
            if (deleteDirectory($albumPath)) {
                $successMessage = '✅ Xóa album "' . htmlspecialchars($albumName) . '" thành công';
            } else {
                $errorMessage = '⚠️ Không thể xóa album. Kiểm tra quyền ghi';
            }
        }
    }
}

// 3. UPLOAD ẢNH VÀO ALBUM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        $errorMessage = '⚠️ CSRF token không hợp lệ';
    } else {
        $albumName = trim($_POST['album_name'] ?? '');
        $albumPath = $ALBUMS_BASE_DIR . '/' . $albumName;
        
        if (!is_dir($albumPath)) {
            $errorMessage = '⚠️ Album không tồn tại';
        } elseif (empty($_FILES['images']['name'][0])) {
            $errorMessage = '⚠️ Vui lòng chọn ít nhất một ảnh';
        } else {
            $uploadCount = 0;
            $errors = [];
            
            foreach ($_FILES['images']['name'] as $key => $filename) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, $ALLOW_EXT)) {
                        $errors[] = "File {$filename}: định dạng không hỗ trợ";
                        continue;
                    }
                    
                    // Sanitize filename
                    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
                    $destination = $albumPath . '/' . $safeName;
                    
                    // Tránh ghi đè
                    $counter = 1;
                    while (file_exists($destination)) {
                        $nameWithoutExt = pathinfo($safeName, PATHINFO_FILENAME);
                        $destination = $albumPath . '/' . $nameWithoutExt . '_' . $counter . '.' . $ext;
                        $counter++;
                    }
                    
                    if (@move_uploaded_file($_FILES['images']['tmp_name'][$key], $destination)) {
                        @chmod($destination, 0644);
                        $uploadCount++;
                    } else {
                        $errors[] = "File {$filename}: không thể upload";
                    }
                }
            }
            
            if ($uploadCount > 0) {
                $successMessage = "✅ Upload thành công {$uploadCount} ảnh vào album \"{$albumName}\"";
            }
            if (!empty($errors)) {
                $errorMessage = '⚠️ ' . implode('; ', $errors);
            }
        }
    }
}

// 4. XÓA ẢNH KHỎI ALBUM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        $errorMessage = '⚠️ CSRF token không hợp lệ';
    } else {
        $albumName = trim($_POST['album_name'] ?? '');
        $imageName = trim($_POST['image_name'] ?? '');
        $imagePath = $ALBUMS_BASE_DIR . '/' . $albumName . '/' . $imageName;
        
        if (!file_exists($imagePath)) {
            $errorMessage = '⚠️ Ảnh không tồn tại';
        } else {
            if (@unlink($imagePath)) {
                // Xóa luôn cache thumbnail nếu có
                $cachePath = $ALBUMS_BASE_DIR . '/' . $albumName . '/cache/' . $imageName;
                @unlink($cachePath);
                $successMessage = '✅ Xóa ảnh thành công';
            } else {
                $errorMessage = '⚠️ Không thể xóa ảnh';
            }
        }
    }
}

// ==================== LOAD ALBUMS ====================

function getAlbums($baseDir) {
    if (!is_dir($baseDir)) {
        return [];
    }
    
    $items = array_diff(scandir($baseDir), ['.', '..']);
    $albums = [];
    
    foreach ($items as $name) {
        $path = $baseDir . '/' . $name;
        if (is_dir($path)) {
            // Đếm số ảnh (không tính thư mục cache)
            $imageCount = 0;
            $files = array_diff(scandir($path), ['.', '..', 'cache']);
            foreach ($files as $file) {
                if (is_file($path . '/' . $file)) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, $GLOBALS['ALLOW_EXT'])) {
                        $imageCount++;
                    }
                }
            }
            
            $albums[] = [
                'name' => $name,
                'path' => $path,
                'imageCount' => $imageCount,
                'mtime' => filemtime($path),
            ];
        }
    }
    
    // Sắp xếp theo thời gian mới nhất
    usort($albums, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    
    return $albums;
}

$albums = getAlbums($ALBUMS_BASE_DIR);

// Lấy ảnh của album được chọn
$selectedAlbum = $_GET['album'] ?? '';
$albumImages = [];
if ($selectedAlbum) {
    $albumPath = $ALBUMS_BASE_DIR . '/' . $selectedAlbum;
    if (is_dir($albumPath)) {
        $files = array_diff(scandir($albumPath), ['.', '..', 'cache']);
        foreach ($files as $file) {
            $fullPath = $albumPath . '/' . $file;
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $ALLOW_EXT)) {
                    $albumImages[] = [
                        'name' => $file,
                        'url' => $ALBUMS_BASE_URL . '/' . rawurlencode($selectedAlbum) . '/' . rawurlencode($file),
                        'size' => filesize($fullPath),
                        'mtime' => filemtime($fullPath),
                    ];
                }
            }
        }
        // Sắp xếp theo tên file
        usort($albumImages, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    }
}
?>

<!-- ==================== UI ==================== -->
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-images text-purple-600"></i>
                Quản lý Albums
            </h1>
            <p class="text-gray-600 mt-1">Tạo và quản lý bộ sưu tập ảnh của studio</p>
        </div>
        <button onclick="toggleModal('createAlbumModal')" 
                class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition flex items-center gap-2 border-0"
                style="background: linear-gradient(90deg, #7c3aed, #ec4899); color:#fff;">
            <i class="fas fa-plus-circle"></i>
            Tạo Album Mới
        </button>
    </div>

    <!-- Messages -->
    <?php if ($successMessage): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg mb-4 flex items-center gap-3">
            <i class="fas fa-check-circle text-xl"></i>
            <span><?= $successMessage ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg mb-4 flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-xl"></i>
            <span><?= $errorMessage ?></span>
        </div>
    <?php endif; ?>

    <!-- Albums Grid -->
    <?php if (empty($albums)): ?>
        <div class="text-center py-16 bg-white rounded-xl shadow-sm">
            <i class="fas fa-images text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 text-lg">Chưa có album nào. Hãy tạo album đầu tiên!</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($albums as $album): 
                $firstImage = null;
                $albumPath = $album['path'];
                $files = array_diff(scandir($albumPath), ['.', '..', 'cache']);
                foreach ($files as $file) {
                    if (is_file($albumPath . '/' . $file)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, $ALLOW_EXT)) {
                            $firstImage = $ALBUMS_BASE_URL . '/' . rawurlencode($album['name']) . '/' . rawurlencode($file);
                            break;
                        }
                    }
                }
            ?>
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition overflow-hidden group">
                <!-- Thumbnail -->
                <a href="?page=albums&album=<?= urlencode($album['name']) ?>" 
                   class="block relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-purple-100 to-pink-100">
                    <?php if ($firstImage): ?>
                        <img src="<?= htmlspecialchars($firstImage) ?>" 
                             alt="<?= htmlspecialchars($album['name']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <i class="fas fa-image text-6xl text-gray-300"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                </a>
                
                <!-- Info -->
                <div class="p-4">
                    <h3 class="font-bold text-lg text-gray-800 truncate mb-1">
                        <?= htmlspecialchars($album['name']) ?>
                    </h3>
                    <p class="text-sm text-gray-500 mb-3 flex items-center gap-2">
                        <i class="fas fa-images"></i>
                        <?= $album['imageCount'] ?> ảnh
                    </p>
                    
                    <!-- Actions -->
                    <div class="flex gap-2">
                        <a href="?page=albums&album=<?= urlencode($album['name']) ?>" 
                           class="flex-1 bg-blue-500 hover:bg-blue-600 text-white text-center py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye"></i> Xem
                        </a>
                        <button onclick="confirmDeleteAlbum('<?= htmlspecialchars($album['name']) ?>')"
                                class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-trash"></i> Xóa
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Album Detail View -->
    <?php if ($selectedAlbum && is_dir($ALBUMS_BASE_DIR . '/' . $selectedAlbum)): ?>
        <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="?page=albums" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($selectedAlbum) ?></h2>
                        <p class="text-gray-600"><?= count($albumImages) ?> ảnh</p>
                    </div>
                </div>
                <button onclick="toggleModal('uploadImagesModal')" 
                        class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg font-semibold shadow-md hover:shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-upload"></i>
                    Upload Ảnh
                </button>
            </div>

            <!-- Images Grid -->
            <?php if (empty($albumImages)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-image text-5xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">Album trống. Hãy upload ảnh đầu tiên!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <?php foreach ($albumImages as $idx => $image): ?>
                        <div class="group relative aspect-square overflow-hidden rounded-lg border-2 border-gray-200 hover:border-purple-500 transition cursor-pointer"
                             onclick="openLightbox(<?= $idx ?>)">
                            <img src="<?= htmlspecialchars($image['url']) ?>" 
                                 alt="<?= htmlspecialchars($image['name']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                 loading="lazy">
                            
                            <!-- Delete Button (X) -->
                            <button onclick="event.stopPropagation(); confirmDeleteImage('<?= htmlspecialchars($selectedAlbum) ?>', '<?= htmlspecialchars($image['name']) ?>')"
                                    class="absolute top-2 right-2 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-lg z-10"
                                    title="Xóa ảnh">
                                <i class="fas fa-times"></i>
                            </button>
                            
                            <!-- Zoom Icon -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <i class="fas fa-search-plus text-white text-3xl drop-shadow-lg"></i>
                            </div>
                            
                            <!-- Info -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent text-white p-2 text-xs opacity-0 group-hover:opacity-100 transition">
                                <p class="truncate font-medium" title="<?= htmlspecialchars($image['name']) ?>">
                                    <?= htmlspecialchars($image['name']) ?>
                                </p>
                                <p class="text-gray-300"><?= number_format($image['size'] / 1024, 1) ?> KB</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Modal: Tạo Album Mới -->
<div id="createAlbumModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6">
            <h2 class="text-2xl font-bold">Tạo Album Mới</h2>
            <p class="text-purple-100 text-sm mt-1">Tạo album và kéo thả ảnh/folder vào để upload ngay</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5" id="createAlbumForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="create_album" value="1">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Tên Album <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="album_name" 
                       id="createAlbumName"
                       required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                      placeholder="VD: Sự kiện mùa thu, Wedding 2024...">
                  <p class="text-xs text-gray-500 mt-1">Cho phép dấu, khoảng trắng, số, dấu gạch ngang/underscore; không dùng ký tự \\/:*?"<>|</p>
            </div>
            
            <!-- Drag & Drop Zone -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Ảnh cho Album <span class="text-gray-500">(Tùy chọn)</span>
                </label>
                <div id="dropZoneCreate" 
                     class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition">
                    <i class="fas fa-cloud-upload-alt text-5xl text-gray-400 mb-3"></i>
                    <p class="text-gray-700 font-medium mb-1">Kéo thả folder hoặc ảnh vào đây</p>
                    <p class="text-sm text-gray-500 mb-3">hoặc click để chọn file</p>
                    <input type="file" 
                           name="album_images[]" 
                           id="createAlbumFiles"
                           multiple
                           webkitdirectory
                           directory
                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           class="hidden">
                    <button type="button" 
                            onclick="document.getElementById('createAlbumFiles').click()"
                            class="bg-purple-100 text-purple-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-200 transition">
                        <i class="fas fa-folder-open mr-2"></i>
                        Chọn Folder/Ảnh
                    </button>
                </div>
                <div id="fileListCreate" class="mt-3 max-h-40 overflow-y-auto"></div>
            </div>
            
            <div class="flex gap-3 pt-4 border-t">
                <button type="submit" 
                    class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg border-0"
                    style="background: linear-gradient(90deg, #7c3aed, #ec4899); color:#fff;">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Tạo Album
                </button>
                <button type="button" 
                        onclick="toggleModal('createAlbumModal'); resetCreateForm();"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 rounded-lg transition">
                    Hủy
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Upload Ảnh -->
<div id="uploadImagesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-6">
            <h2 class="text-2xl font-bold">Upload Ảnh</h2>
            <p class="text-green-100 text-sm mt-1">Thêm ảnh vào album "<?= htmlspecialchars($selectedAlbum) ?>"</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="upload_images" value="1">
            <input type="hidden" name="album_name" value="<?= htmlspecialchars($selectedAlbum) ?>">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Chọn Ảnh <span class="text-red-500">*</span>
                </label>
                <input type="file" 
                       name="images[]" 
                       multiple
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                       required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-500 mt-1">Hỗ trợ: JPG, PNG, GIF, WebP. Có thể chọn nhiều ảnh</p>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg">
                    <i class="fas fa-upload mr-2"></i>
                    Upload
                </button>
                <button type="button" 
                        onclick="toggleModal('uploadImagesModal')"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 rounded-lg transition">
                    Hủy
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightboxModal" class="hidden fixed inset-0 bg-black bg-opacity-95 z-[60] backdrop-blur-md">
    <!-- Close Button -->
    <button onclick="closeLightbox()" 
            class="fixed top-6 right-6 w-14 h-14 bg-white/25 hover:bg-white/40 text-gray-900 rounded-full flex items-center justify-center transition z-20 shadow-lg backdrop-blur"
            title="Đóng (ESC)">
        <i class="fas fa-times text-2xl"></i>
    </button>
    
    <!-- Previous Button -->
    <button id="lightboxPrev" 
            onclick="navigateLightbox(-1)"
            class="fixed left-6 top-1/2 -translate-y-1/2 w-14 h-14 bg-white/25 hover:bg-white/40 text-gray-900 rounded-full flex items-center justify-center transition z-20 shadow-lg backdrop-blur"
            title="Ảnh trước (←)">
        <i class="fas fa-chevron-left text-xl"></i>
    </button>
    
    <!-- Next Button -->
    <button id="lightboxNext"
            onclick="navigateLightbox(1)"
            class="fixed right-6 top-1/2 -translate-y-1/2 w-14 h-14 bg-white/25 hover:bg-white/40 text-gray-900 rounded-full flex items-center justify-center transition z-20 shadow-lg backdrop-blur"
            title="Ảnh tiếp (→)">
        <i class="fas fa-chevron-right text-xl"></i>
    </button>
    
    <!-- Image Container -->
    <div class="fixed inset-0 flex items-center justify-center p-20 pb-32" onclick="event.target === this && closeLightbox()">
        <img id="lightboxImage" 
             src="" 
             alt="" 
               class="rounded-lg shadow-2xl opacity-0 transition-opacity duration-300"
               style="object-fit: contain; max-width: 85vw; max-height: 85vh; width: auto; height: auto;">
    </div>
    
    <!-- Image Info -->
    <div class="fixed bottom-0 left-0 right-0 bg-gradient-to-t from-black/90 via-black/50 to-transparent p-8 text-white z-10">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <p id="lightboxTitle" class="font-semibold text-lg mb-1 truncate"></p>
                <p id="lightboxInfo" class="text-sm text-gray-300"></p>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for deletion -->
<form id="deleteAlbumForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="delete_album" value="1">
    <input type="hidden" name="album_name" id="deleteAlbumName">
</form>

<form id="deleteImageForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="delete_image" value="1">
    <input type="hidden" name="album_name" id="deleteImageAlbumName">
    <input type="hidden" name="image_name" id="deleteImageName">
</form>

<?php if ($selectedAlbum && !empty($albumImages)): ?>
<script>
// Lightbox data
const lightboxImages = <?= json_encode($albumImages) ?>;
let currentLightboxIndex = 0;

function openLightbox(index) {
    currentLightboxIndex = index;
    updateLightbox();
    document.getElementById('lightboxModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightboxModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function navigateLightbox(direction) {
    currentLightboxIndex += direction;
    if (currentLightboxIndex < 0) {
        currentLightboxIndex = lightboxImages.length - 1;
    } else if (currentLightboxIndex >= lightboxImages.length) {
        currentLightboxIndex = 0;
    }
    updateLightbox();
}

function updateLightbox() {
    const image = lightboxImages[currentLightboxIndex];
    const imgElement = document.getElementById('lightboxImage');
    
    // Ẩn ảnh trước khi tính kích thước
    imgElement.style.opacity = '0';
    
    // Thiết lập kích thước an toàn mặc định trước khi ảnh load
    imgElement.style.maxWidth = '85vw';
    imgElement.style.maxHeight = '85vh';
    imgElement.style.width = 'auto';
    imgElement.style.height = 'auto';
    
    imgElement.src = image.url;
    imgElement.alt = image.name;
    
    // Đợi ảnh load xong để lấy kích thước thật
    imgElement.onload = function() {
        const aspectRatio = this.naturalWidth / this.naturalHeight;
        
        // Ảnh ngang (landscape)
        if (aspectRatio > 1.3) {
            this.style.maxWidth = '90vw';
            this.style.maxHeight = '80vh';
        }
        // Ảnh dọc (portrait)
        else if (aspectRatio < 0.8) {
            this.style.maxWidth = '65vw';
            this.style.maxHeight = '90vh';
        }
        // Ảnh vuông hoặc gần vuông
        else {
            this.style.maxWidth = '80vw';
            this.style.maxHeight = '85vh';
        }
        
        this.style.width = 'auto';
        this.style.height = 'auto';
        
        // Hiển thị ảnh với fade in
        setTimeout(() => {
            this.style.opacity = '1';
        }, 50);
    };
    
    document.getElementById('lightboxTitle').textContent = image.name;
    document.getElementById('lightboxInfo').textContent = `Ảnh ${currentLightboxIndex + 1} / ${lightboxImages.length} • ${(image.size / 1024).toFixed(1)} KB`;
    const downloadEl = document.getElementById('lightboxDownload');
    if (downloadEl) {
        downloadEl.href = image.url;
        downloadEl.download = image.name;
    }
    
    // Show/hide navigation buttons
    document.getElementById('lightboxPrev').style.display = lightboxImages.length > 1 ? 'flex' : 'none';
    document.getElementById('lightboxNext').style.display = lightboxImages.length > 1 ? 'flex' : 'none';
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('lightboxModal');
    if (!modal.classList.contains('hidden')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            navigateLightbox(-1);
        } else if (e.key === 'ArrowRight') {
            navigateLightbox(1);
        }
    }
});
</script>
<?php endif; ?>

<script>
// Đưa lightbox ra khỏi container (gắn trực tiếp vào body) để luôn cố định theo viewport
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('lightboxModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
});
</script>

<script>
function confirmDeleteAlbum(albumName) {
    if (confirm(`Bạn có chắc chắn muốn xóa album "${albumName}"?\n\nToàn bộ ảnh trong album sẽ bị xóa vĩnh viễn!`)) {
        document.getElementById('deleteAlbumName').value = albumName;
        document.getElementById('deleteAlbumForm').submit();
    }
}

function confirmDeleteImage(albumName, imageName) {
    if (confirm(`Bạn có chắc chắn muốn xóa ảnh "${imageName}"?`)) {
        document.getElementById('deleteImageAlbumName').value = albumName;
        document.getElementById('deleteImageName').value = imageName;
        document.getElementById('deleteImageForm').submit();
    }
}

function resetCreateForm() {
    document.getElementById('createAlbumForm').reset();
    document.getElementById('fileListCreate').innerHTML = '';
}

// Drag & Drop for Create Album
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZoneCreate');
    const fileInput = document.getElementById('createAlbumFiles');
    const fileList = document.getElementById('fileListCreate');
    const albumNameInput = document.getElementById('createAlbumName');
    
    // Click to select
    dropZone.addEventListener('click', function(e) {
        if (e.target.id !== 'createAlbumFiles' && e.target.tagName !== 'BUTTON') {
            fileInput.click();
        }
    });
    
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight drop zone
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, function() {
            dropZone.classList.add('border-purple-500', 'bg-purple-50');
        });
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, function() {
            dropZone.classList.remove('border-purple-500', 'bg-purple-50');
        });
    });
    
    // Handle dropped files
    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    });
    
    // Handle selected files
    fileInput.addEventListener('change', function(e) {
        handleFiles(this.files);
        
        // Auto-fill album name from folder if empty
        if (this.files.length > 0 && !albumNameInput.value) {
            const path = this.files[0].webkitRelativePath || this.files[0].name;
            const folderName = path.split('/')[0];
            if (folderName && folderName !== this.files[0].name) {
                albumNameInput.value = folderName.replace(/[^a-zA-Z0-9_\-\s]/g, '_');
            }
        }
    });
    
    function handleFiles(files) {
        if (files.length === 0) return;
        
        fileList.innerHTML = '';
        const imageFiles = Array.from(files).filter(file => 
            file.type.startsWith('image/')
        );
        
        if (imageFiles.length === 0) {
            fileList.innerHTML = '<p class="text-sm text-amber-600"><i class="fas fa-exclamation-triangle mr-2"></i>Không tìm thấy file ảnh hợp lệ</p>';
            return;
        }
        
        fileList.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                <p class="text-sm text-green-800 font-medium">
                    <i class="fas fa-check-circle mr-2"></i>
                    Đã chọn ${imageFiles.length} ảnh
                </p>
                <p class="text-xs text-green-600 mt-1">Sẽ được upload sau khi tạo album</p>
            </div>
        `;
    }
});
</script>

<style>
/* Animation cho hover effects */
@keyframes slideIn {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.group:hover .group-hover\:scale-110 {
    transition: transform 0.3s ease;
}
</style>
