/**
 * HICM V2025 - Form Validation & Handling
 * ระบบจัดการ validation และ error handling แบบเรียลไทม์
 */

class FormValidator {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) return;
        this.setupListeners();
    }

    setupListeners() {
        const inputs = this.form.querySelectorAll('.form-input, .form-select, .form-textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('change', () => this.validateField(input));
            
            // Real-time validation for email and password
            if (input.type === 'email' || input.type === 'password') {
                input.addEventListener('input', () => this.validateField(input));
            }
        });
    }

    validateField(field) {
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;

        let isValid = true;
        let errorMessage = '';

        // Required validation
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            errorMessage = 'ช่องนี้จำเป็นต้องกรอก';
        }

        // Email validation
        if (field.type === 'email' && field.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                errorMessage = 'กรุณากรอกอีเมลที่ถูกต้อง';
            }
        }

        // Password validation
        if (field.type === 'password' && field.value.trim()) {
            const minLength = parseInt(field.dataset.minLength || 8);
            if (field.value.length < minLength) {
                isValid = false;
                errorMessage = `รหัสผ่านต้องมีอย่างน้อย ${minLength} ตัวอักษร`;
            }
        }

        // Phone validation
        if (field.type === 'tel' && field.value.trim()) {
            const phoneRegex = /^[0-9\-\+\(\)\s]+$/;
            if (!phoneRegex.test(field.value)) {
                isValid = false;
                errorMessage = 'กรุณากรอกหมายเลขโทรศัพท์ที่ถูกต้อง';
            }
        }

        // Min length validation
        if (field.dataset.minLength) {
            const minLength = parseInt(field.dataset.minLength);
            if (field.value.length > 0 && field.value.length < minLength) {
                isValid = false;
                errorMessage = `ต้องมีอย่างน้อย ${minLength} ตัวอักษร`;
            }
        }

        // Max length validation
        if (field.dataset.maxLength) {
            const maxLength = parseInt(field.dataset.maxLength);
            if (field.value.length > maxLength) {
                isValid = false;
                errorMessage = `ต้องมีไม่เกิน ${maxLength} ตัวอักษร`;
            }
        }

        // Custom validation pattern
        if (field.dataset.pattern) {
            const pattern = new RegExp(field.dataset.pattern);
            if (field.value.trim() && !pattern.test(field.value)) {
                isValid = false;
                errorMessage = field.dataset.patternMessage || 'รูปแบบไม่ถูกต้อง';
            }
        }

        this.updateFieldStatus(field, formGroup, isValid, errorMessage);
        return isValid;
    }

    updateFieldStatus(field, formGroup, isValid, errorMessage) {
        // Remove old error/success messages
        const oldError = formGroup.querySelector('.form-error');
        const oldSuccess = formGroup.querySelector('.form-success');
        if (oldError) oldError.remove();
        if (oldSuccess) oldSuccess.remove();

        // Update field classes
        field.classList.remove('is-invalid', 'is-valid');
        
        if (isValid && field.value.trim()) {
            field.classList.add('is-valid');
            const successMsg = document.createElement('div');
            successMsg.className = 'form-success';
            successMsg.textContent = '✓ ถูกต้อง';
            formGroup.appendChild(successMsg);
        } else if (!isValid && field.value.trim()) {
            field.classList.add('is-invalid');
            const errorMsg = document.createElement('div');
            errorMsg.className = 'form-error';
            errorMsg.textContent = '✗ ' + errorMessage;
            formGroup.appendChild(errorMsg);
        }
    }

    validateForm() {
        const inputs = this.form.querySelectorAll('.form-input, .form-select, .form-textarea');
        let isFormValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isFormValid = false;
            }
        });

        return isFormValid;
    }
}

// Initialize all forms with validation
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(form => {
        new FormValidator(form);
    });
});

// Export for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FormValidator;
}
