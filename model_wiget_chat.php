<?php
// Kiểm tra session, nếu chưa có thì start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kết nối DB nếu chưa có (để lấy tên người dùng)
// Lưu ý: Điều chỉnh đường dẫn tương đối tùy vào vị trí đặt file này
if (!isset($conn)) {
    include_once __DIR__ . '/database/config.php'; 
}

// 1. XÁC ĐỊNH NGƯỜI DÙNG & VAI TRÒ
$isLoggedIn = isset($_SESSION['ID_TK']);
$role = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['ID_TK'] ?? '';
$branchId = $_SESSION['branch_id'] ?? '';
$userDisplayName = "Bạn"; // Mặc định

// 2. LẤY TÊN NGƯỜI DÙNG TỪ DB (VÌ SESSION LOGIN CHƯA LƯU TÊN)
if ($isLoggedIn && isset($conn)) {
    $tableName = '';
    if ($role === 'customer') {
        $tableName = 'khach_hang';
    } elseif (in_array($role, ['staff', 'branch_manager'])) {
        $tableName = 'nhan_vien';
    } else {
        // Admin
        $tableName = 'quan_tri_vien'; // Hoặc bảng tương ứng của admin
    }

    if ($tableName) {
        // Giả định cột tên là HO_TEN
        $stmtName = $conn->prepare("SELECT HO_TEN FROM $tableName WHERE ID_TK = ?");
        if ($stmtName) {
            $stmtName->bind_param("s", $userId);
            $stmtName->execute();
            $resName = $stmtName->get_result();
            if ($rowName = $resName->fetch_assoc()) {
                $userDisplayName = htmlspecialchars($rowName['HO_TEN']);
            }
            $stmtName->close();
        }
    }
}

// 3. TẠO NGỮ CẢNH (SYSTEM INSTRUCTION) CHO AI
// Đây là phần "DẠY" AI cách trả lời dựa trên session
$aiContext = "";

switch ($role) {
    case 'customer':
        $aiContext = "Người dùng là Khách hàng tên {$userDisplayName} (Mã: {$userId}). " .
                     "Hỗ trợ họ: Đặt lịch chụp ảnh, xem các gói combo, và kiểm tra lịch sử đặt lịch. " .
                     "Nếu họ hỏi giá, hãy nhắc họ xem chi tiết trong trang Dịch vụ.";
        break;

    case 'staff':
        $aiContext = "Người dùng là Nhân viên chuyên trách tên {$userDisplayName} (Mã: {$userId}, Chi nhánh: {$branchId}). " .
                     "Hỗ trợ họ: Xem lịch làm việc cá nhân, báo cáo tình trạng thiết bị hỏng. " .
                     "Nếu hỏi về lương: Nhắc công thức 'Lương cứng + 15% hoa hồng đơn hoàn thành'.";
        break;

    case 'branch_manager':
        $aiContext = "Người dùng là Quản lý chi nhánh tên {$userDisplayName} (Mã: {$userId}, Chi nhánh: {$branchId}). " .
                     "Hỗ trợ họ: Duyệt lịch hẹn chờ xác nhận, Phân công nhân viên, Nhập xuất kho thiết bị.";
        break;

    case 'admin':
        $aiContext = "Người dùng là Quản trị viên hệ thống (Admin). " .
                     "Hỗ trợ tra cứu nhanh báo cáo doanh thu, số lượng user mới. Cảnh báo nếu có thiết bị cần bảo trì.";
        break;

    default: // Guest
        $aiContext = "Người dùng là khách vãng lai chưa đăng nhập. " .
                     "Hãy nhiệt tình giới thiệu Stygian Blue Studio, các gói chụp ảnh đẹp. " .
                     "Hướng dẫn họ đăng ký tài khoản hoặc đăng nhập bằng Google để đặt lịch.";
        break;
}
?>

<style>
    /* Import font icon nếu chưa có */
    @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
</style>

<script src="https://cdn.tailwindcss.com"></script>

<div id="stygian-chat-widget" class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end font-sans">

    <div id="chat-window" class="hidden w-[360px] h-[520px] bg-white rounded-2xl shadow-2xl flex flex-col border border-gray-200 mb-4 overflow-hidden transition-all duration-300 origin-bottom-right scale-95 opacity-0">
        
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-4 flex justify-between items-center text-white shadow-md">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <i class="fa-solid fa-robot text-lg"></i>
                    </div>
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-blue-700 rounded-full"></span>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Trợ lý Stygian Blue</h3>
                    <p class="text-[11px] text-blue-100 opacity-90">Luôn sẵn sàng hỗ trợ</p>
                </div>
            </div>
            <button onclick="toggleChat()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-slate-50 space-y-4 scroll-smooth">
            <div class="flex flex-col items-start max-w-[85%]">
                <div class="bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-none text-sm text-gray-700 shadow-sm">
                    Xin chào <b><?= $userDisplayName ?></b>! 👋<br>
                    <?php if ($role === 'guest'): ?>
                        Tôi có thể giúp bạn tìm hiểu dịch vụ hoặc hướng dẫn đăng ký tài khoản không?
                    <?php else: ?>
                        Tôi là trợ lý ảo AI. Bạn cần hỗ trợ gì về hệ thống hôm nay?
                    <?php endif; ?>
                </div>
                <span class="text-[10px] text-gray-400 mt-1 ml-1">Vừa xong</span>
            </div>
        </div>

        <div class="p-3 bg-white border-t border-gray-100">
            <div class="flex items-center bg-gray-100 rounded-full px-2 py-1 border border-transparent focus-within:border-blue-400 focus-within:bg-white transition-all shadow-inner">
                <input type="text" id="user-input" 
                    class="flex-1 bg-transparent border-none outline-none text-sm text-gray-700 px-3 py-2 placeholder-gray-500"
                    placeholder="Nhập câu hỏi..." onkeypress="handleEnter(event)">
                <button onclick="sendMessage()" class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition shadow-sm">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </div>
            <div class="text-[10px] text-center text-gray-400 mt-2">
                Powered by Gemini AI
            </div>
        </div>
    </div>

    <div id="chat-menu" class="hidden flex-col gap-3 mb-4 transition-all duration-300 translate-y-4 opacity-0">
        <button onclick="openAiChat()" class="flex items-center justify-end gap-3 group">
            <span class="bg-white text-gray-700 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity -translate-x-2 group-hover:translate-x-0 pointer-events-none">Hỏi AI</span>
            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full shadow-lg flex items-center justify-center text-white hover:scale-110 transition-transform border-2 border-white">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
        </button>

        <a href="https://zalo.me/0909xxxxxx" target="_blank" class="flex items-center justify-end gap-3 group">
            <span class="bg-white text-gray-700 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity -translate-x-2 group-hover:translate-x-0 pointer-events-none">Zalo</span>
            <div class="w-12 h-12 bg-blue-500 rounded-full shadow-lg flex items-center justify-center text-white hover:scale-110 transition-transform border-2 border-white font-bold text-lg">Z</div>
        </a>
    </div>

    <button id="launcher-btn" onclick="toggleMenu()" class="w-14 h-14 bg-gray-900 text-white rounded-full shadow-2xl flex items-center justify-center text-2xl hover:scale-105 transition-transform duration-200 relative z-50">
        <i class="fa-regular fa-comment-dots transition-all duration-300" id="launcher-icon"></i>
        <i class="fa-solid fa-xmark absolute opacity-0 rotate-90 transition-all duration-300" id="close-icon"></i>
    </button>

</div>

<script>
    // --- LOGIC JS ---
    
    // Lấy ngữ cảnh từ PHP để gửi kèm API
    const systemContext = `<?= addslashes($aiContext) ?>`; 
    
    const widget = document.getElementById('stygian-chat-widget');
    const menu = document.getElementById('chat-menu');
    const chatWindow = document.getElementById('chat-window');
    const launcherIcon = document.getElementById('launcher-icon');
    const closeIcon = document.getElementById('close-icon');
    const messagesContainer = document.getElementById('chat-messages');
    const userInput = document.getElementById('user-input');

    let isMenuOpen = false;
    let isChatOpen = false;

    function toggleMenu() {
        if (isChatOpen) { toggleChat(); return; }

        isMenuOpen = !isMenuOpen;
        if (isMenuOpen) {
            menu.classList.remove('hidden');
            requestAnimationFrame(() => {
                menu.classList.remove('translate-y-4', 'opacity-0');
                launcherIcon.classList.add('opacity-0', 'rotate-90');
                closeIcon.classList.remove('opacity-0', 'rotate-90');
            });
        } else {
            menu.classList.add('translate-y-4', 'opacity-0');
            launcherIcon.classList.remove('opacity-0', 'rotate-90');
            closeIcon.classList.add('opacity-0', 'rotate-90');
            setTimeout(() => menu.classList.add('hidden'), 300);
        }
    }

    function openAiChat() {
        toggleMenu(); // Đóng menu nhỏ
        chatWindow.classList.remove('hidden');
        requestAnimationFrame(() => {
            chatWindow.classList.remove('scale-95', 'opacity-0');
            isChatOpen = true;
            // Đổi icon launcher thành X
            launcherIcon.classList.add('opacity-0', 'rotate-90');
            closeIcon.classList.remove('opacity-0', 'rotate-90');
        });
        setTimeout(() => userInput.focus(), 300);
    }

    function toggleChat() {
        chatWindow.classList.add('scale-95', 'opacity-0');
        isChatOpen = false;
        launcherIcon.classList.remove('opacity-0', 'rotate-90');
        closeIcon.classList.add('opacity-0', 'rotate-90');
        setTimeout(() => chatWindow.classList.add('hidden'), 300);
    }

    function handleEnter(e) { if (e.key === 'Enter') sendMessage(); }

    async function sendMessage() {
        const text = userInput.value.trim();
        if (!text) return;

        addMessageUI(text, 'user');
        userInput.value = '';
        const loadingId = addLoadingUI();

        try {
            // Gửi đến file xử lý API (Bạn cần tạo file này)
            // Thay đổi đường dẫn phù hợp với thư mục của bạn
            const response = await fetch('/StygianBlue/app/api/chat_process.php', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: text,
                    context: systemContext // QUAN TRỌNG: Gửi context nhận diện user
                })
            });

            const data = await response.json();
            removeLoadingUI(loadingId);
            
            if (data.reply) {
                addMessageUI(data.reply, 'bot');
            } else {
                addMessageUI("Xin lỗi, không nhận được phản hồi từ server.", 'bot');
            }
        } catch (err) {
            removeLoadingUI(loadingId);
            addMessageUI("Lỗi kết nối! Vui lòng thử lại.", 'bot');
            console.error(err);
        }
    }

    function addMessageUI(text, sender) {
        const div = document.createElement('div');
        const isBot = sender === 'bot';
        div.className = `flex flex-col ${isBot ? 'items-start' : 'items-end'} max-w-[85%] self-${isBot ? 'start' : 'end'} animate-[fadeIn_0.3s_ease-out]`;
        
        const bubbleClass = isBot 
            ? 'bg-white border border-gray-200 text-gray-700 rounded-tl-none' 
            : 'bg-blue-600 text-white rounded-tr-none';

        div.innerHTML = `<div class="${bubbleClass} p-3 rounded-2xl text-sm shadow-sm leading-relaxed">${text}</div>`;
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function addLoadingUI() {
        const id = 'loading-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = 'flex items-center gap-1 bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-none w-fit self-start shadow-sm';
        div.innerHTML = `
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce delay-100"></span>
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce delay-200"></span>
        `;
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return id;
    }

    function removeLoadingUI(id) {
        const el = document.getElementById(id);
        if(el) el.remove();
    }
</script>