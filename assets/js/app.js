// Universal right-click disable
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
}, false);

window.Modus = {
    // Fetch helper that automatically includes CSRF token
    async request(url, options = {}) {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta ? csrfMeta.content : (window.CSRF_TOKEN || '');
        
        options.headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': token,
            ...(options.headers || {})
        };
        
        const response = await fetch(url, options);
        return response.json();
    },

    // Confirm dialog helper
    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alerts after 5 seconds if marked
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});