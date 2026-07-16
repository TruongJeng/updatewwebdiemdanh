<?php
// sidebar.php – FINAL
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to check active state
function isActive($path, $currentPage) {
    return $currentPage === $path ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 font-semibold border-r-4 border-primary-600 dark:border-primary-500' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-primary-600 dark:hover:text-primary-400 font-medium border-r-4 border-transparent';
}
?>

<!-- Alpine Store for Sidebar State -->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('sidebar', {
            open: false,
            toggle() { this.open = !this.open },
            close() { this.open = false }
        })
    })

    // Auto-close sidebar on mobile when clicking any nav link
    document.addEventListener('click', function(e) {
        // Find closest <a> tag
        const link = e.target.closest('aside a[href]');
        if (!link) return;
        
        const href = link.getAttribute('href');
        // Skip anchors (#), javascript:, and external links
        if (!href || href === '#' || href.startsWith('javascript:') || link.target === '_blank') return;
        
        // Only on mobile (sidebar is toggled via store)
        if (window.innerWidth >= 1024) return;
        
        // Check if sidebar is open
        if (Alpine.store('sidebar') && Alpine.store('sidebar').open) {
            e.preventDefault();
            Alpine.store('sidebar').close();
            // Navigate after sidebar close animation
            setTimeout(() => {
                window.location.href = href;
            }, 250);
        }
    });
</script>

<!-- Mobile Toggle Button (Floating) -->
<button @click="$store.sidebar.toggle()" 
        class="lg:hidden fixed bottom-6 right-6 z-[2000] w-14 h-14 bg-primary-600 text-white rounded-full shadow-[0_10px_25px_-5px_rgba(37,99,235,0.5)] flex items-center justify-center hover:bg-primary-700 hover:scale-105 active:scale-95 transition-all focus:outline-none"
        aria-label="Toggle Menu">
    <i class="bi bi-list text-2xl" x-show="!$store.sidebar.open"></i>
    <i class="bi bi-x-lg text-xl" x-show="$store.sidebar.open" x-cloak></i>
</button>

<!-- Backdrop -->
<div x-show="$store.sidebar.open" 
     @click="$store.sidebar.open = false"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-slate-900/50 dark:bg-black/70 backdrop-blur-sm z-[1080] lg:hidden" 
     x-cloak></div>

<!-- Sidebar -->
<aside :class="$store.sidebar.open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed top-14 sm:top-16 left-0 z-[1090] w-64 h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shadow-sm transition-transform duration-300 ease-in-out overflow-y-auto overscroll-contain flex flex-col pb-20 lg:pb-0">
    
    <nav class="flex-1 px-3 sm:px-4 py-4 sm:py-6 space-y-1">
        
        <!-- Dashboard -->
        <a href="/hethongdiemdanh/dashboard" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('dashboard.php', $currentPage) ?>">
            <i class="bi bi-house-door text-lg"></i>
            <span>Trang chủ</span>
        </a>

        <!-- Divider + Label: Trại sinh -->
        <div class="pt-3 pb-1">
            <p class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Điểm danh</p>
        </div>

        <!-- Sự kiện (MỚI - Trung tâm) -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/views/events" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('events.php', $currentPage) ?>">
            <i class="bi bi-calendar-event text-lg"></i>
            <span>Sự kiện</span>
        </a>

        <!-- Tạo mã PIN -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/views/create_pin" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('create_pin.php', $currentPage) ?>">
            <i class="bi bi-key text-lg"></i>
            <span>Tạo mã PIN</span>
        </a>

        <!-- Điểm danh trại sinh -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/views/enter_pin" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('enter_pin.php', $currentPage) ?>">
            <i class="bi bi-person-check text-lg"></i>
            <span>Quét QR (BTC)</span>
        </a>

        <!-- Kiểm tra điểm danh -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/views/attendance_list" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('attendance_list.php', $currentPage) ?>">
            <i class="bi bi-list-check text-lg"></i>
            <span>Kiểm tra phiên</span>
        </a>

        <!-- QL Trại sinh -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/modules/manage_campers" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('manage_campers.php', $currentPage) ?>">
            <i class="bi bi-pencil-square text-lg"></i>
            <span>Quản lý trại sinh</span>
        </a>

        <!-- Chia đội trại sinh -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/modules/chiadoi" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('chiadoi.php', $currentPage) ?>">
            <i class="bi bi-diagram-3 text-lg"></i>
            <span>Chia đội</span>
        </a>

        <!-- Thống kê trại sinh -->
        <a href="/hethongdiemdanh/attendanceTraiSinh/views/report_attendance" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('report_attendance.php', $currentPage) ?>">
            <i class="bi bi-archive-fill text-lg"></i>
            <span>Thống kê</span>
        </a>

        <!-- Divider + Label: Quản lý sự kiện -->
        <div class="pt-3 pb-1">
            <p class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Quản lý sự kiện</p>
        </div>

        <!-- Sự kiện & Điểm danh (Dropdown) -->
        <div x-data="{ expanded: false }" class="pt-1">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-3 py-3 sm:py-2.5 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-primary-600 dark:hover:text-primary-400 font-medium rounded-l-lg border-r-4 border-transparent transition-colors focus:outline-none">
                <div class="flex items-center gap-3">
                    <i class="bi bi-calendar-event text-lg"></i>
                    <span>Sự kiện</span>
                </div>
                <i class="bi bi-chevron-down text-xs transition-transform duration-300" :class="expanded ? 'rotate-180' : ''"></i>
            </button>
            
            <div x-show="expanded" 
                 x-transition:enter="transition ease-out duration-200 origin-top"
                 x-transition:enter-start="opacity-0 scale-y-95"
                 x-transition:enter-end="opacity-100 scale-y-100"
                 x-transition:leave="transition ease-in duration-150 origin-top"
                 x-transition:leave-start="opacity-100 scale-y-100"
                 x-transition:leave-end="opacity-0 scale-y-95"
                 x-cloak>
                <ul class="pl-11 pr-3 py-2 space-y-0.5 border-l-2 border-slate-100 dark:border-slate-800 ml-[18px] mt-1">
                    <li><a href="/hethongdiemdanh/modules/events" @click="$store.sidebar.close()" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-calendar-event mr-2"></i> Sự kiện</a></li>
                    <li><a href="/hethongdiemdanh/modules/students" @click="$store.sidebar.close()" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-people mr-2"></i> Học sinh</a></li>
                    <li><a href="/hethongdiemdanh/modules/attendance" @click="$store.sidebar.close()" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-clipboard-check mr-2"></i> Điểm danh</a></li>
                    <li><a href="/hethongdiemdanh/modules/report" @click="$store.sidebar.close()" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-bar-chart-line mr-2"></i> Thống kê</a></li>
                    <li><a href="/hethongdiemdanh/modules/team" @click="$store.sidebar.close()" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-people-fill mr-2"></i> Chia đội</a></li>
                </ul>
            </div>
        </div>

        <!-- Divider + Label: Hệ thống -->
        <div class="pt-3 pb-1">
            <p class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Hệ thống</p>
        </div>

        <!-- Users -->
        <a href="/hethongdiemdanh/modules/users" @click="$store.sidebar.close()" class="flex items-center gap-3 px-3 py-3 sm:py-2.5 rounded-l-lg transition-colors <?= isActive('users.php', $currentPage) ?>">
            <i class="bi bi-person-gear text-lg"></i>
            <span>Quản lý tài khoản</span>
        </a>

        <!-- Tiện ích (Dropdown) -->
        <div x-data="{ expanded: false }" class="pt-1">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-3 py-3 sm:py-2.5 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-primary-600 dark:hover:text-primary-400 font-medium rounded-l-lg border-r-4 border-transparent transition-colors focus:outline-none">
                <div class="flex items-center gap-3">
                    <i class="bi bi-tools text-lg"></i>
                    <span>Tiện ích</span>
                </div>
                <i class="bi bi-chevron-down text-xs transition-transform duration-300" :class="expanded ? 'rotate-180' : ''"></i>
            </button>
            
            <div x-show="expanded" 
                 x-transition:enter="transition ease-out duration-200 origin-top"
                 x-transition:enter-start="opacity-0 scale-y-95"
                 x-transition:enter-end="opacity-100 scale-y-100"
                 x-transition:leave="transition ease-in duration-150 origin-top"
                 x-transition:leave-start="opacity-100 scale-y-100"
                 x-transition:leave-end="opacity-0 scale-y-95"
                 x-cloak>
                <ul class="pl-11 pr-3 py-2 space-y-0.5 border-l-2 border-slate-100 dark:border-slate-800 ml-[18px] mt-1">
                    <li><a href="https://www.online-stopwatch.com/" target="_blank" rel="noopener noreferrer" class="block px-2 py-2.5 sm:py-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50/50 dark:hover:bg-slate-800 rounded-md transition-colors"><i class="bi bi-stopwatch mr-2"></i> Trò chơi</a></li>
                </ul>
            </div>
        </div>

    </nav>
    
    <!-- Footer / Bottom Links in Sidebar -->
    <div class="px-4 py-4 border-t border-slate-100 dark:border-slate-800 mt-auto bg-slate-50/50 dark:bg-slate-900/50">
        <!-- Info Modal Trigger -->
        <a href="#" data-bs-toggle="modal" data-bs-target="#softInfoModal" class="flex items-center gap-3 px-3 py-2.5 mb-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-200 text-sm font-medium rounded-lg transition-colors">
            <i class="bi bi-info-circle text-lg"></i>
            <span>Thông tin phần mềm</span>
        </a>
        <a href="/hethongdiemdanh/logout" class="flex items-center justify-center gap-2 w-full py-2.5 text-sm font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 rounded-lg transition-colors">
            <i class="bi bi-box-arrow-right text-lg"></i>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>

