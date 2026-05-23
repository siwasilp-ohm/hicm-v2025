/**
 * HICM V2025 - Button Loading States & Async Handling
 * จัดการ loading states สำหรับปุ่มและ async operations
 */

class ButtonHandler {
    constructor() {
        this.loadingButtons = new Map();
        this.setupListeners();
    }

    setupListeners() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('button[type="submit"], .btn-submit');
            if (button && button.form) {
                button.addEventListener('click', (event) => {
                    const form = button.form;
                    // Only add loading if form is valid
                    if (form.checkValidity()) {
                        this.setLoading(button);
                    }
                });
            }
        });
    }

    setLoading(button) {
        // Prevent multiple clicks
        if (button.hasAttribute('data-loading')) return;

        button.setAttribute('data-loading', 'true');
        button.classList.add('loading');
        button.disabled = true;

        // Store original content
        const originalContent = button.innerHTML;
        this.loadingButtons.set(button, originalContent);

        // Optional: Update button text
        if (button.dataset.loadingText) {
            button.innerHTML = `<span>${button.dataset.loadingText}</span>`;
        }
    }

    resetLoading(button) {
        button.removeAttribute('data-loading');
        button.classList.remove('loading');
        button.disabled = false;

        if (this.loadingButtons.has(button)) {
            button.innerHTML = this.loadingButtons.get(button);
        }
    }

    // Reset loading state after successful submission
    onSuccess(button) {
        setTimeout(() => {
            this.resetLoading(button);
        }, 500);
    }

    // Reset loading state on error
    onError(button) {
        this.resetLoading(button);
        button.classList.add('btn-danger');
    }
}

// Initialize button handler
const buttonHandler = new ButtonHandler();

// Export for external use
if (typeof window !== 'undefined') {
    window.buttonHandler = buttonHandler;
}
