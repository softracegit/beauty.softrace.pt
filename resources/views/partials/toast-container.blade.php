<!-- Toast container: canto inferior esquerdo (evita sobrepor offcanvas à direita) -->
<div id="appToastContainer" class="toast-container position-fixed bottom-0 start-0 p-3" style="z-index: 9999;"></div>
<script>
(function() {
    var icons = { error: 'ph ph-x-circle', success: 'ph ph-check-circle', warning: 'ph ph-warning', info: 'ph ph-info-circle' };
    var classes = { error: 'bg-danger-light text-danger', success: 'bg-success-light text-success', warning: 'bg-warning-light text-warning', info: 'bg-info-light text-info' };
    window.showToast = function(message, type) {
        type = type || 'info';
        var icon = icons[type] || icons.info;
        var cls = classes[type] || classes.info;
        var container = document.getElementById('appToastContainer');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center ' + cls + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="' + icon + ' me-2"></i>' + (message || '') + '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>';
        container.appendChild(toast);
        var bsToast = new bootstrap.Toast(toast, { delay: 5000, autohide: true });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    };
})();
</script>
@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.showToast === 'function') {
        window.showToast(@json($errors->first()), 'error');
    }
});
</script>
@endif
