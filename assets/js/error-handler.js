/**
 * HICM V2025 - Error Handling & Display
 * จัดการข้อผิดพลาด alert และ toast messages
 */

class ErrorHandler {
    static showError(message, duration = 5000) {
        const container = this.getContainer();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger error-toast';
        alert.setAttribute('role', 'alert');
        alert.setAttribute('aria-live', 'assertive');
        alert.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <strong style="font-weight: 600;">ข้อผิดพลาด</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">${message}</p>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()" 
                        aria-label="ปิด" style="background: none; border: none; color: inherit; font-size: 1.5rem; cursor: pointer;">
                    ×
                </button>
            </div>
        `;
        container.appendChild(alert);

        if (duration) {
            setTimeout(() => {
                alert.remove();
            }, duration);
        }

        return alert;
    }

    static showSuccess(message, duration = 3000) {
        const container = this.getContainer();
        const alert = document.createElement('div');
        alert.className = 'alert alert-success success-toast';
        alert.setAttribute('role', 'status');
        alert.setAttribute('aria-live', 'polite');
        alert.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <strong style="font-weight: 600;">สำเร็จ</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">${message}</p>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()" 
                        aria-label="ปิด" style="background: none; border: none; color: inherit; font-size: 1.5rem; cursor: pointer;">
                    ×
                </button>
            </div>
        `;
        container.appendChild(alert);

        if (duration) {
            setTimeout(() => {
                alert.remove();
            }, duration);
        }

        return alert;
    }

    static showWarning(message, duration = 4000) {
        const container = this.getContainer();
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning warning-toast';
        alert.setAttribute('role', 'alert');
        alert.setAttribute('aria-live', 'polite');
        alert.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <strong style="font-weight: 600;">คำเตือน</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">${message}</p>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()" 
                        aria-label="ปิด" style="background: none; border: none; color: inherit; font-size: 1.5rem; cursor: pointer;">
                    ×
                </button>
            </div>
        `;
        container.appendChild(alert);

        if (duration) {
            setTimeout(() => {
                alert.remove();
            }, duration);
        }

        return alert;
    }

    static getContainer() {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
                display: flex;
                flex-direction: column;
                gap: 12px;
            `;
            document.body.appendChild(container);
        }
        return container;
    }
}

// Handle AJAX errors globally
document.addEventListener('DOMContentLoaded', function() {
    // Setup for fetch API
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            if (!response.ok && response.status >= 400) {
                ErrorHandler.showError(`เกิดข้อผิดพลาด (${response.status}): ${response.statusText}`);
            }
            return response;
        }).catch(error => {
            ErrorHandler.showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            console.error('Fetch error:', error);
            throw error;
        });
    };
});

// Export for external use
if (typeof window !== 'undefined') {
    window.ErrorHandler = ErrorHandler;
}
