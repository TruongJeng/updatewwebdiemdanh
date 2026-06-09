<?php
// sidebar.php – FINAL
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to check active state
function isActive($path, $currentPage) {
    return $currentPage === $path ? 'bg-primary-50 text-primary-700 font-semibold border-r-4 border-primary-600' : 'text-slate-600 hover:bg-slate-50 hover:text-primary-600 font-medium border-r-4 border-transparent';
}
?>

<!-- Alpine Store for Sidebar State -->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('sidebar', {
            open: false,
            toggle() { this.open = !this.open }
        })
    })
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
     class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[1080] lg:hidden" 
     x-cloak></div>

<!-- Sidebar -->
<aside :class="$store.sidebar.open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed top-16 left-0 z-[1090] w-64 h-[calc(100vh-4rem)] bg-white border-r border-slate-200 shadow-sm transition-transform duration-300 ease-in-out overflow-y-auto flex flex-col pb-20 lg:pb-0">
    
    <nav class="flex-1 px-4 py-6 space-y-1.5">
        
        <!-- Dashboard -->
        <a href="/hethongdiemdanh/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('dashboard.php', $currentPage) ?>">
            <i class="bi bi-house-door text-lg"></i>
            <span>Trang chủ</span>
        </a>

        <!-- Events -->
        <a href="/hethongdiemdanh/modules/events.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('events.php', $currentPage) ?>">
            <i class="bi bi-calendar-event text-lg"></i>
            <span>Quản lý sự kiện</span>
        </a>

        <!-- Students -->
        <a href="/hethongdiemdanh/modules/students.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('students.php', $currentPage) ?>">
            <i class="bi bi-people text-lg"></i>
            <span>Quản lý học sinh</span>
        </a>

        <!-- Attendance -->
        <a href="/hethongdiemdanh/modules/attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('attendance.php', $currentPage) ?>">
            <i class="bi bi-clipboard-check text-lg"></i>
            <span>Điểm danh</span>
        </a>

        <!-- Điểm danh trại sinh (Dropdown) -->
        <div x-data="{ expanded: false }" class="pt-1">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-primary-600 font-medium rounded-l-lg border-r-4 border-transparent transition-colors focus:outline-none">
                <div class="flex items-center gap-3">
                    <i class="bi bi-grid text-lg"></i>
                    <span>Trại sinh</span>
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
                <ul class="pl-11 pr-3 py-2 space-y-1 border-l-2 border-slate-100 ml-[18px] mt-1">
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/views/create_pin.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-key mr-2"></i> Tạo mã PIN</a></li>
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/views/enter_pin.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-person-check mr-2"></i> Điểm danh</a></li>
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/views/attendance_list.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-list-check mr-2"></i> Kiểm tra</a></li>
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/modules/manage_campers.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-pencil mr-2"></i> QL trại sinh</a></li>
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/modules/chiadoi.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-diagram-3 mr-2"></i> Chia đội</a></li>
                    <li><a href="/hethongdiemdanh/attendanceTraiSinh/views/report_attendance.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-archive-fill mr-2"></i> Thống kê</a></li>
                </ul>
            </div>
        </div>

        <!-- Tiện ích (Dropdown) -->
        <div x-data="{ expanded: false }" class="pt-1">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-primary-600 font-medium rounded-l-lg border-r-4 border-transparent transition-colors focus:outline-none">
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
                <ul class="pl-11 pr-3 py-2 space-y-1 border-l-2 border-slate-100 ml-[18px] mt-1">
                    <li><a href="/hethongdiemdanh/modules/team.php" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-people-fill mr-2"></i> Đội</a></li>
                    <li><a href="https://www.online-stopwatch.com/" target="_blank" rel="noopener noreferrer" class="block px-2 py-1.5 text-sm text-slate-500 hover:text-primary-600 hover:bg-primary-50/50 rounded-md transition-colors"><i class="bi bi-stopwatch mr-2"></i> Trò chơi</a></li>
                </ul>
            </div>
        </div>

        <!-- Report -->
        <a href="/hethongdiemdanh/modules/report.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('report.php', $currentPage) ?>">
            <i class="bi bi-bar-chart-line text-lg"></i>
            <span>Thống kê</span>
        </a>

        <!-- Users -->
        <a href="/hethongdiemdanh/modules/users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-l-lg transition-colors <?= isActive('users.php', $currentPage) ?>">
            <i class="bi bi-person-gear text-lg"></i>
            <span>Quản lý tài khoản</span>
        </a>
    </nav>
    
    <!-- Footer / Bottom Links in Sidebar -->
    <div class="px-4 py-4 border-t border-slate-100 mt-auto bg-slate-50/50">
        <!-- Info Modal Trigger -->
        <a href="#" data-bs-toggle="modal" data-bs-target="#softInfoModal" class="flex items-center gap-3 px-3 py-2.5 mb-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 text-sm font-medium rounded-lg transition-colors">
            <i class="bi bi-info-circle text-lg"></i>
            <span>Thông tin phần mềm</span>
        </a>
        <a href="/hethongdiemdanh/logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
            <i class="bi bi-box-arrow-right text-lg"></i>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>

