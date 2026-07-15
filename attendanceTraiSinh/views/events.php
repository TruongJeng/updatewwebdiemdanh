<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../../dashboard.php");
    exit();
}

$pageTitle = "QUẢN LÝ SỰ KIỆN";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8"
      x-data="eventsApp()" x-init="loadEvents()">
    <div class="max-w-7xl mx-auto pb-12">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-calendar-event text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">SỰ KIỆN ĐIỂM DANH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Quản lý sự kiện và chọn phương thức điểm danh</p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-initial sm:w-64">
                    <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" x-model="search" @input.debounce.300ms="loadEvents()" placeholder="Tìm sự kiện..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 shadow-sm transition-all">
                </div>
                <button @click="openCreateModal()" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl font-semibold transition-all shadow-sm hover:shadow-md text-sm whitespace-nowrap">
                    <i class="bi bi-plus-circle"></i> Tạo sự kiện
                </button>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="eventsGrid">
            <template x-for="event in events" :key="event.id">
                <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 group">
                    <!-- Card Header -->
                    <div class="p-5 pb-3">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-slate-800 text-lg truncate" x-text="event.title"></h3>
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2" x-text="event.description || 'Chưa có mô tả'"></p>
                            </div>
                            <div class="flex items-center gap-1 ml-3 shrink-0">
                                <button @click="toggleActive(event)" :class="event.is_active == 1 ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors mr-1" :title="event.is_active == 1 ? 'Sự kiện đang mở (Nhấn để đóng)' : 'Sự kiện đã đóng (Nhấn để mở)'">
                                    <i class="text-sm bi" :class="event.is_active == 1 ? 'bi-unlock-fill' : 'bi-lock-fill'"></i>
                                </button>
                                <button @click="openEditModal(event)" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-primary-50 hover:text-primary-600 flex items-center justify-center transition-colors" title="Sửa">
                                    <i class="bi bi-pencil-square text-sm"></i>
                                </button>
                                <button @click="deleteEvent(event)" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-red-50 hover:text-red-600 flex items-center justify-center transition-colors" title="Xóa">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Meta -->
                        <div class="flex items-center gap-2 flex-wrap text-xs text-slate-500 mb-4">
                            <span :class="event.is_active == 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'" class="inline-flex items-center gap-1 px-2 py-1 rounded-md font-bold text-[10px] uppercase tracking-wider">
                                <i class="bi" :class="event.is_active == 1 ? 'bi-circle-fill text-[6px] animate-pulse text-emerald-500' : 'bi-dash-circle'"></i>
                                <span x-text="event.is_active == 1 ? 'Đang mở' : 'Đã đóng'"></span>
                            </span>
                            <template x-if="event.event_date">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-slate-50 border border-slate-100">
                                    <i class="bi bi-calendar-event"></i>
                                    <span x-text="formatDate(event.event_date)"></span>
                                </span>
                            </template>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-primary-50 text-primary-700 border border-primary-100">
                                <i class="bi bi-people-fill"></i>
                                <span x-text="event.checkin_count + ' đã điểm danh'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="border-t border-slate-100 bg-slate-50/50 p-3 grid grid-cols-4 gap-2 transition-all" :class="event.is_active == 0 ? 'opacity-50 pointer-events-none grayscale' : ''">
                        <a :href="'create_pin.php?event_id=' + event.id" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-white hover:bg-primary-50 border border-slate-100 hover:border-primary-200 transition-colors group/btn">
                            <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center group-hover/btn:scale-110 transition-transform">
                                <i class="bi bi-qr-code-scan text-lg"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-600 group-hover/btn:text-primary-700 uppercase tracking-wider text-center leading-tight">BTC<br>Quét QR</span>
                        </a>

                        <a :href="'student_checkin.php?event_id=' + event.id" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-white hover:bg-purple-50 border border-slate-100 hover:border-purple-200 transition-colors group/btn">
                            <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center group-hover/btn:scale-110 transition-transform">
                                <i class="bi bi-phone text-lg"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-600 group-hover/btn:text-purple-700 uppercase tracking-wider text-center leading-tight">HS tự<br>điểm danh</span>
                        </a>

                        <a :href="'manual_attendance.php?event_id=' + event.id" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-white hover:bg-amber-50 border border-slate-100 hover:border-amber-200 transition-colors group/btn">
                            <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center group-hover/btn:scale-110 transition-transform">
                                <i class="bi bi-hand-index text-lg"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-600 group-hover/btn:text-amber-700 uppercase tracking-wider text-center leading-tight">Điểm danh<br>thủ công</span>
                        </a>

                        <a :href="'ts_admin_map.php?event_id=' + event.id" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-white hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 transition-colors group/btn">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center group-hover/btn:scale-110 transition-transform">
                                <i class="bi bi-geo-alt text-lg"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-600 group-hover/btn:text-emerald-700 uppercase tracking-wider text-center leading-tight">Bản đồ<br>GPS</span>
                        </a>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <template x-if="events.length === 0 && !loading">
                <div class="col-span-full py-16 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 text-slate-300 flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-calendar-x text-4xl"></i>
                    </div>
                    <h3 class="font-bold text-slate-700 mb-2 text-lg">Chưa có sự kiện nào</h3>
                    <p class="text-slate-500 text-sm mb-6">Tạo sự kiện đầu tiên để bắt đầu điểm danh</p>
                    <button @click="openCreateModal()" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-sm">
                        <i class="bi bi-plus-circle"></i> Tạo sự kiện mới
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-[2000]" role="dialog" aria-modal="true">
        <div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div x-show="showModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                     @click.away="showModal = false"
                     class="relative bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-100 overflow-hidden">

                    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
                        <h3 class="text-lg font-extrabold text-slate-800" x-text="editId ? 'Sửa sự kiện' : 'Tạo sự kiện mới'"></h3>
                    </div>

                    <form @submit.prevent="saveEvent()" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tên sự kiện *</label>
                            <input type="text" x-model="form.title" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all" placeholder="Ví dụ: Trại hè 2026">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Ngày diễn ra</label>
                            <input type="datetime-local" x-model="form.event_date" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Mô tả</label>
                            <textarea x-model="form.description" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all resize-none" placeholder="Mô tả ngắn về sự kiện..."></textarea>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</button>
                            <button type="submit" :disabled="saving" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-bold shadow-sm transition-all flex items-center gap-2 disabled:opacity-60">
                                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : (editId ? 'bi-save' : 'bi-plus-circle')"></i>
                                <span x-text="saving ? 'Đang lưu...' : (editId ? 'Lưu thay đổi' : 'Tạo sự kiện')"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-[2000]" role="dialog" aria-modal="true">
        <div x-show="showDeleteModal" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDeleteModal = false"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div x-show="showDeleteModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                     class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 pt-6 pb-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="bi bi-exclamation-triangle text-red-600"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Xóa sự kiện</h3>
                                <p class="mt-1 text-sm text-slate-500 font-medium">Xóa sự kiện "<span x-text="deleteTarget?.title" class="font-bold text-slate-700"></span>"? Toàn bộ dữ liệu điểm danh liên quan sẽ bị xóa.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-3 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button @click="confirmDelete()" :disabled="saving" class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                            <i class="bi bi-trash"></i> Xóa
                        </button>
                        <button @click="showDeleteModal = false" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 transition-all">
                            Hủy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function eventsApp() {
    return {
        events: [],
        search: '',
        loading: false,
        showModal: false,
        showDeleteModal: false,
        editId: null,
        deleteTarget: null,
        saving: false,
        form: { title: '', description: '', event_date: '' },

        async loadEvents() {
            this.loading = true;
            try {
                const res = await fetch(`../api/events_api.php?action=list&q=${encodeURIComponent(this.search)}`);
                const data = await res.json();
                if (data.success) this.events = data.events;
            } catch (e) {
                console.error(e);
            }
            this.loading = false;
        },

        openCreateModal() {
            this.editId = null;
            this.form = { title: '', description: '', event_date: '' };
            this.showModal = true;
        },

        openEditModal(event) {
            this.editId = event.id;
            this.form = {
                title: event.title,
                description: event.description || '',
                event_date: event.event_date ? event.event_date.replace(' ', 'T').slice(0, 16) : ''
            };
            this.showModal = true;
        },

        async saveEvent() {
            if (!this.form.title.trim()) return;
            this.saving = true;

            const fd = new FormData();
            fd.append('action', this.editId ? 'update' : 'create');
            fd.append('title', this.form.title);
            fd.append('description', this.form.description);
            fd.append('event_date', this.form.event_date);
            if (this.editId) fd.append('id', this.editId);

            try {
                const res = await fetch('../api/events_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.showModal = false;
                    this.loadEvents();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Lỗi kết nối server');
            }
            this.saving = false;
        },

        deleteEvent(event) {
            this.deleteTarget = event;
            this.showDeleteModal = true;
        },

        async confirmDelete() {
            if (!this.deleteTarget) return;
            this.saving = true;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', this.deleteTarget.id);

            try {
                const res = await fetch('../api/events_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.showDeleteModal = false;
                    this.deleteTarget = null;
                    this.loadEvents();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Lỗi kết nối server');
            }
            this.saving = false;
        },

        async toggleActive(event) {
            const newActive = event.is_active == 1 ? 0 : 1;
            const confirmMsg = newActive === 0 ? 'Bạn có chắc chắn muốn ĐÓNG sự kiện này không? Khi đóng, học sinh sẽ không thể điểm danh sự kiện này nữa.' : 'Bạn muốn MỞ LẠI sự kiện này?';
            if (!confirm(confirmMsg)) return;
            
            const fd = new FormData();
            fd.append('action', 'toggle_active');
            fd.append('id', event.id);
            fd.append('active', newActive);

            try {
                const res = await fetch('../api/events_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    event.is_active = newActive;
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Lỗi kết nối server');
            }
        },

        formatDate(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            return d.toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
        }
    };
}
</script>
