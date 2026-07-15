/**
 * Shop Management System - Global JavaScript File (ES6)
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide Icons if loaded
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Initialize UI features
    initSidebar();
    initTheme();
    initDropdowns();
});

/**
 * Sidebar Collapse/Expand Toggle
 */
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');
    const appContainer = document.querySelector('.app-container');

    if (!appContainer) return;

    // Load saved state
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isCollapsed && window.innerWidth > 1024) {
        appContainer.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            appContainer.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', appContainer.classList.contains('sidebar-collapsed'));
            // Re-render Lucide icons if alignment needs adjusting
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    }

    // Mobile Sidebar Toggles
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', () => {
            appContainer.classList.add('sidebar-open');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            appContainer.classList.remove('sidebar-open');
        });
    }
}

/**
 * Theme Toggler (Light / Dark)
 */
function initTheme() {
    const themeBtnLight = document.getElementById('theme-btn-light');
    const themeBtnDark = document.getElementById('theme-btn-dark');

    if (!themeBtnLight || !themeBtnDark) return;

    // Read current theme state
    const savedTheme = localStorage.getItem('theme') || 'dark'; // Dark mode is our default
    setTheme(savedTheme);

    themeBtnLight.addEventListener('click', () => setTheme('light'));
    themeBtnDark.addEventListener('click', () => setTheme('dark'));
}

function setTheme(theme) {
    const htmlElement = document.documentElement;
    const btnLight = document.getElementById('theme-btn-light');
    const btnDark = document.getElementById('theme-btn-dark');

    if (theme === 'dark') {
        htmlElement.classList.add('dark');
        if (btnLight) btnLight.classList.remove('active');
        if (btnDark) btnDark.classList.add('active');
    } else {
        htmlElement.classList.remove('dark');
        if (btnLight) btnLight.classList.add('active');
        if (btnDark) btnDark.classList.remove('active');
    }

    localStorage.setItem('theme', theme);
    
    // Optional: Send this preference to DB via fetch so it's persisted in the user settings
    fetch('/shop-system/ajax/settings.php?action=set_theme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: theme })
    }).catch(e => { /* Silently fail if endpoint doesn't exist yet */ });
}

/**
 * General Dropdowns Behavior (Profile, Notifications, etc.)
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close other dropdowns
                dropdowns.forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });
                dropdown.classList.toggle('active');
            });
        }
    });

    // Close on click outside
    document.addEventListener('click', () => {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
}

/**
 * Global Toast Notification Generator
 * @param {string} message - Message to display
 * @param {string} type - 'success' | 'danger' | 'warning' | 'info'
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'info';
    if (type === 'success') icon = 'check-circle';
    if (type === 'danger') icon = 'alert-triangle';
    if (type === 'warning') icon = 'alert-circle';

    toast.innerHTML = `
        <span class="toast-icon"><i data-lucide="${icon}"></i></span>
        <div class="toast-message">${message}</div>
        <button class="toast-close">&times;</button>
    `;

    container.appendChild(toast);
    
    // Render the newly created Lucide icon inside the toast
    if (typeof lucide !== 'undefined') {
        lucide.createIcons({
            attrs: {
                class: 'toast-icon-svg'
            },
            nameAttr: 'data-lucide'
        });
    }

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.style.animation = 'fadeOut 0.2s ease-out forwards';
        setTimeout(() => toast.remove(), 200);
    });

    // Auto dismiss after 4 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'fadeOut 0.2s ease-out forwards';
            setTimeout(() => toast.remove(), 200);
        }
    }, 4000);
}

/**
 * Reusable Confirmation Dialog Modal
 * @param {string} title
 * @param {string} message
 * @param {function} onConfirm
 */
function showConfirmModal(title, message, onConfirm) {
    let overlay = document.getElementById('confirm-modal-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'confirm-modal-overlay';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal-window">
                <div class="modal-header">
                    <h3 id="confirm-modal-title"></h3>
                    <button class="btn btn-secondary p-0 cursor-pointer" id="confirm-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="confirm-modal-message"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirm-modal-cancel">Cancel</button>
                    <button class="btn btn-danger" id="confirm-modal-ok">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Bind static close handlers
        const closeBtn = overlay.querySelector('#confirm-modal-close');
        const cancelBtn = overlay.querySelector('#confirm-modal-cancel');
        const closeAction = () => {
            overlay.classList.remove('active');
        };
        closeBtn.addEventListener('click', closeAction);
        cancelBtn.addEventListener('click', closeAction);
    }

    // Set dynamic text
    overlay.querySelector('#confirm-modal-title').innerText = title;
    overlay.querySelector('#confirm-modal-message').innerText = message;

    // Handle confirm action
    const confirmBtn = overlay.querySelector('#confirm-modal-ok');
    
    // Remove previous listener (clone node to clear event listeners)
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.replaceWith(newConfirmBtn);
    
    newConfirmBtn.addEventListener('click', () => {
        overlay.classList.remove('active');
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });

    overlay.classList.add('active');
}

/**
 * Custom Fetch AJAX Wrapper
 */
async function ajaxRequest(url, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    options.headers = {
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
    };

    if (csrfToken && (options.method === 'POST' || options.method === 'PUT' || options.method === 'DELETE')) {
        // If it's a FormData object
        if (options.body instanceof FormData) {
            options.body.append('csrf_token', csrfToken);
        } else if (typeof options.body === 'string') {
            // If it's a JSON string, parsed at server side
            try {
                const parsed = JSON.parse(options.body);
                parsed.csrf_token = csrfToken;
                options.body = JSON.stringify(parsed);
            } catch(e) {
                // If it's a query string format
                options.body += `&csrf_token=${encodeURIComponent(csrfToken)}`;
            }
        } else if (options.body && typeof options.body === 'object') {
            options.body.csrf_token = csrfToken;
        } else {
            options.body = `csrf_token=${encodeURIComponent(csrfToken)}`;
        }
    }

    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('AJAX Error:', error);
        showToast('An error occurred during transaction processing.', 'danger');
        return { success: false, message: error.message };
    }
}
