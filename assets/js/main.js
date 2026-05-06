// Al-Anfas POS — Main JS

// Auto-hide alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(a) {
        setTimeout(function() {
            a.style.transition = 'opacity .4s';
            a.style.opacity = '0';
            setTimeout(function() { a.remove(); }, 400);
        }, 4000);
    });
});
