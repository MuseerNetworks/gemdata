function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden', 'opacity-0');
    setTimeout(() => overlay.classList.add('opacity-100'), 10);
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar || !overlay) return;
    sidebar.classList.add('-translate-x-full');
    overlay.classList.remove('opacity-100');
    setTimeout(() => overlay.classList.add('hidden', 'opacity-0'), 300);
}

function openFundModal() {
    const modal = document.getElementById('fund-modal');
    if (!modal) {
        window.location.href = `${window.GEMDATA_RUNTIME?.baseUrl || '/'}user/fund-wallet.php`;
        return;
    }
    modal.classList.remove('hidden');
    setTimeout(() => modal.querySelector('.bg-white')?.classList.add('animate-fadeUp'), 10);
}

function closeFundModal() {
    document.getElementById('fund-modal')?.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', openSidebar);
    });
    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });
    document.querySelectorAll('[data-fund-modal-open]').forEach((button) => {
        button.addEventListener('click', openFundModal);
    });
    document.querySelectorAll('[data-fund-modal-close]').forEach((button) => {
        button.addEventListener('click', closeFundModal);
    });
});
