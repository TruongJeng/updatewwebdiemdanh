<!-- Nội dung trang kết thúc -->
</div>
<footer class="fixed bottom-0 left-0 right-0 bg-white/80 backdrop-blur-sm border-t border-slate-200 h-8 flex items-center justify-end px-3 sm:px-6 z-[1000] text-[10px] sm:text-xs text-slate-500 shadow-[0_-2px_10px_rgba(0,0,0,0.02)] pb-safe">
    2025 &copy; Phát triển bởi
    <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" class="text-primary-600 hover:text-primary-800 hover:underline font-medium ml-1.5 transition-colors">
        CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt
    </a>
</footer>
<!-- Global Confirm Modal (Alpine.js) -->
<div x-data="{ 
        open: false, 
        message: '', 
        actionUrl: null, 
        actionFormId: null,
        actionCallback: null,
        title: 'Xác nhận',
        confirmText: 'Đồng ý',
        cancelText: 'Hủy bỏ',
        type: 'danger'
    }" 
    @open-confirm.window="
        open = true; 
        message = $event.detail.message; 
        actionUrl = $event.detail.url || null; 
        actionFormId = $event.detail.formId || null;
        actionCallback = $event.detail.callback || null;
        title = $event.detail.title || 'Xác nhận';
        confirmText = $event.detail.confirmText || 'Xác nhận';
        cancelText = $event.detail.cancelText || 'Hủy bỏ';
        type = $event.detail.type || 'danger';
    "
    class="relative z-[2500]" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
    
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 @click.away="open = false"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 w-full max-w-sm sm:max-w-md border border-slate-100">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10 mb-4 sm:mb-0"
                             :class="type === 'danger' ? 'bg-red-100' : 'bg-amber-100'">
                            <i class="bi text-lg" 
                               :class="type === 'danger' ? 'bi-exclamation-triangle text-red-600' : 'bi-info-circle text-amber-600'"></i>
                        </div>
                        <div class="text-center sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg font-extrabold leading-6 text-slate-900" id="modal-title" x-text="title"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-slate-500 font-medium whitespace-pre-line" x-text="message"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                    <button type="button" 
                            @click="if (actionUrl) { window.location.href = actionUrl; } else if (actionFormId) { document.getElementById(actionFormId).submit(); } else if (actionCallback) { actionCallback(); } open = false;" 
                            class="inline-flex w-full justify-center items-center rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-sm sm:ml-3 sm:w-auto transition-all"
                            :class="type === 'danger' ? 'bg-red-600 hover:bg-red-700 active:bg-red-800' : 'bg-primary-600 hover:bg-primary-700 active:bg-primary-800'"
                            x-text="confirmText">
                    </button>
                    <button type="button" @click="open = false" 
                            class="mt-3 inline-flex w-full justify-center items-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900 sm:mt-0 sm:w-auto transition-all"
                            x-text="cancelText">
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>