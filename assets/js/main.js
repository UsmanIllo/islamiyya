/**
 * Islamiyya School Management System (ISMS)
 * Interactive UI Engine — Friendly & Modern Edition
 */

(function () {
    'use strict';

    // Global ISMS Namespace
    window.ISMS = window.ISMS || {};

    /**
     * 1. Toast Notification System
     * Displays elegant, accessible Islamic-themed floating toasts
     */
    ISMS.showToast = function (message, type = 'success', duration = 4000) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container-custom';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast-islamic toast-${type}`;
        
        const iconMap = {
            success: 'bi-check2-circle',
            error: 'bi-exclamation-octagon-fill',
            danger: 'bi-exclamation-octagon-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        const icon = iconMap[type] || 'bi-info-circle-fill';

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="bi ${icon}"></i>
            </div>
            <div class="toast-body">
                <div class="toast-msg">${message}</div>
            </div>
            <button type="button" class="toast-close" aria-label="Close">&times;</button>
            <div class="toast-progress"></div>
        `;

        container.appendChild(toast);

        const closeBtn = toast.querySelector('.toast-close');
        function removeToast() {
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }

        closeBtn.addEventListener('click', removeToast);

        if (duration > 0) {
            setTimeout(removeToast, duration);
        }
    };

    /**
     * 2. One-Click Copy to Clipboard
     */
    ISMS.copyToClipboard = function (text, label = 'Copied to clipboard!') {
        if (!navigator.clipboard) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                ISMS.showToast(label, 'success');
            } catch (err) {
                ISMS.showToast('Could not copy text', 'error');
            }
            document.body.removeChild(textarea);
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
            ISMS.showToast(label, 'success');
        }).catch(() => {
            ISMS.showToast('Failed to copy', 'error');
        });
    };

    /**
     * 3. Number Counter Animation
     */
    ISMS.animateNumber = function (elem, target, prefix = '', suffix = '', duration = 1000) {
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const ease = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * ease);

            elem.textContent = prefix + current.toLocaleString() + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                elem.textContent = prefix + target.toLocaleString() + suffix;
            }
        }

        requestAnimationFrame(update);
    };

    /**
     * Confirmation Modal / Prompt
     */
    window.confirmAction = function (message) {
        return confirm(message || 'Are you sure you want to proceed with this action?');
    };

    // DOM Ready Initializations
    document.addEventListener('DOMContentLoaded', function () {

        // --- A. Initialize Back to Top Button ---
        let backToTop = document.getElementById('backToTop');
        if (!backToTop) {
            backToTop = document.createElement('button');
            backToTop.id = 'backToTop';
            backToTop.type = 'button';
            backToTop.title = 'Back to Top';
            backToTop.innerHTML = '<i class="bi bi-chevron-up"></i>';
            document.body.appendChild(backToTop);
        }

        window.addEventListener('scroll', function () {
            if (window.scrollY > 280) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // --- B. Wire Copy-to-Clipboard Buttons ---
        document.querySelectorAll('[data-copy], .copy-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const copyText = this.getAttribute('data-copy') || this.getAttribute('data-clipboard') || this.textContent.trim();
                const originalHtml = this.innerHTML;

                ISMS.copyToClipboard(copyText, `Copied: "${copyText}"`);

                this.classList.add('copied');
                this.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied!';

                setTimeout(() => {
                    this.classList.remove('copied');
                    this.innerHTML = originalHtml;
                }, 2200);
            });
        });

        // --- C. Password Visibility Toggle ---
        document.querySelectorAll('.password-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = targetId ? document.getElementById(targetId) : this.closest('.input-group').querySelector('input');
                if (!input) return;

                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            });
        });

        // --- D. Demo Credentials Quick Fill on Login Page ---
        document.querySelectorAll('.demo-cred-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const role = this.getAttribute('data-role');
                const userField = document.querySelector('input[name="login_input"]');
                const passField = document.querySelector('input[name="password"]');

                if (userField && passField) {
                    if (role === 'admin') {
                        userField.value = 'admin';
                        passField.value = 'Admin@123';
                        ISMS.showToast('Loaded Administrator demo credentials', 'info');
                    } else if (role === 'parent') {
                        userField.value = 'parent_ibrahim';
                        passField.value = 'Parent@123';
                        ISMS.showToast('Loaded Parent demo credentials', 'info');
                    }
                    userField.focus();
                }
            });
        });

        // --- E. Dynamic Multi-Child Registration Form Generator ---
        const numChildrenSelect = document.getElementById('num_children_select');
        const childrenFormsContainer = document.getElementById('children_forms_container');
        const classOptionsTemplate = document.getElementById('class_options_template');

        function renderChildForms(count) {
            if (!childrenFormsContainer) return;
            childrenFormsContainer.innerHTML = '';
            const classOptions = classOptionsTemplate ? classOptionsTemplate.innerHTML : '<option value="">Select Level</option>';

            for (let i = 1; i <= count; i++) {
                const childCard = document.createElement('div');
                childCard.className = 'child-form-card mb-4 animate-fade-in-up';
                childCard.style.animationDelay = `${(i - 1) * 0.08}s`;
                childCard.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="mb-0 text-islamic fw-bold">
                            <i class="bi bi-person-badge me-2 text-gold"></i> Child #${i} Registration Details
                        </h5>
                        <span class="badge bg-islamic-soft text-islamic fw-semibold">Form ${i} of ${count}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="children[${i}][first_name]" class="form-control" required placeholder="e.g. Ibrahim">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name (Surname) <span class="text-danger">*</span></label>
                            <input type="text" name="children[${i}][last_name]" class="form-control" required placeholder="e.g. Danladi">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Other Name</label>
                            <input type="text" name="children[${i}][other_name]" class="form-control" placeholder="e.g. Al-Mansur">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="children[${i}][date_of_birth]" class="form-control" required max="${new Date().toISOString().split('T')[0]}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="children[${i}][gender]" class="form-select" required>
                                <option value="">-- Choose Gender --</option>
                                <option value="Male">Male (Boy)</option>
                                <option value="Female">Female (Girl)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Requested Islamiyya Class <span class="text-danger">*</span></label>
                            <select name="children[${i}][requested_class_id]" class="form-select" required>
                                ${classOptions}
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Previous Islamiyya / Quranic Education</label>
                            <textarea name="children[${i}][previous_islamiyya_education]" class="form-control" rows="2" placeholder="Mention prior madrasah, Juz memorized (e.g., Juz Amma), or if starting as beginner..."></textarea>
                        </div>
                    </div>
                `;
                childrenFormsContainer.appendChild(childCard);
            }
        }

        if (numChildrenSelect) {
            numChildrenSelect.addEventListener('change', function () {
                const count = parseInt(this.value, 10) || 1;
                renderChildForms(count);
                ISMS.showToast(`Updated forms for ${count} child(ren)`, 'info', 2500);
            });
            // Initial render
            renderChildForms(parseInt(numChildrenSelect.value, 10) || 1);
        }

        // --- F. Real-Time Table Search & Empty State ---
        document.querySelectorAll('.table-search-input').forEach(input => {
            const tableId = input.getAttribute('data-target-table');
            const table = document.getElementById(tableId);
            if (!table) return;

            const countBadge = input.closest('.table-search-wrap')?.querySelector('.table-search-results');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const allRows = Array.from(tbody.querySelectorAll('tr:not(.table-empty-search)'));
            const totalCount = allRows.length;

            input.addEventListener('input', function () {
                const query = this.value.trim().toLowerCase();
                let matchCount = 0;

                allRows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    if (rowText.includes(query)) {
                        row.style.display = '';
                        matchCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update badge
                if (countBadge) {
                    if (query) {
                        countBadge.textContent = `${matchCount} of ${totalCount}`;
                        countBadge.style.display = 'block';
                    } else {
                        countBadge.textContent = `${totalCount} records`;
                    }
                }

                // Handle empty state
                let emptyRow = tbody.querySelector('.table-empty-search');
                if (matchCount === 0 && query) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'table-empty-search';
                        const colCount = table.querySelectorAll('thead th').length || 6;
                        emptyRow.innerHTML = `
                            <td colspan="${colCount}" class="text-center py-4 text-muted">
                                <i class="bi bi-search fs-2 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold">No records match "${query}"</div>
                                <small>Try checking the spelling or searching another keyword</small>
                            </td>
                        `;
                        tbody.appendChild(emptyRow);
                    }
                } else if (emptyRow) {
                    emptyRow.remove();
                }
            });
        });

        // --- G. Interactive Drag-and-Drop File Upload with Live Preview ---
        document.querySelectorAll('input[type="file"].dropzone-input, .file-dropzone-input').forEach(fileInput => {
            const wrap = fileInput.closest('.file-dropzone-wrap');
            if (!wrap) return;

            const previewArea = wrap.parentElement.querySelector('.dropzone-preview-card') || document.createElement('div');
            previewArea.className = 'dropzone-preview-card';
            previewArea.style.display = 'none';
            wrap.parentElement.appendChild(previewArea);

            ['dragenter', 'dragover'].forEach(eventName => {
                wrap.addEventListener(eventName, e => {
                    e.preventDefault();
                    wrap.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                wrap.addEventListener(eventName, e => {
                    e.preventDefault();
                    wrap.classList.remove('drag-over');
                }, false);
            });

            wrap.addEventListener('drop', e => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    handleFiles(files[0]);
                }
            });

            fileInput.addEventListener('change', function () {
                if (this.files.length) {
                    handleFiles(this.files[0]);
                }
            });

            function handleFiles(file) {
                const isImage = file.type.startsWith('image/');
                const isPdf = file.type === 'application/pdf' || file.name.endsWith('.pdf');
                const fileSize = (file.size / (1024 * 1024)).toFixed(2); // MB

                if (!isImage && !isPdf) {
                    ISMS.showToast('Please select a JPG, PNG, or PDF file only', 'error');
                    fileInput.value = '';
                    previewArea.style.display = 'none';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    ISMS.showToast('File size must not exceed 5MB', 'error');
                    fileInput.value = '';
                    previewArea.style.display = 'none';
                    return;
                }

                previewArea.style.display = 'flex';
                let iconHtml = isPdf 
                    ? '<div class="dropzone-preview-thumb d-flex align-items-center justify-content-center bg-danger text-white fs-3"><i class="bi bi-file-earmark-pdf"></i></div>'
                    : `<img src="${URL.createObjectURL(file)}" class="dropzone-preview-thumb" alt="Preview">`;

                previewArea.innerHTML = `
                    ${iconHtml}
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate text-islamic">${file.name}</div>
                        <div class="small text-muted">${fileSize} MB &bull; Ready to upload</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-preview" title="Remove file">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;

                previewArea.querySelector('.btn-remove-preview').addEventListener('click', function () {
                    fileInput.value = '';
                    previewArea.style.display = 'none';
                    ISMS.showToast('Uploaded slip removed', 'info', 2000);
                });

                ISMS.showToast(`Selected proof: ${file.name} (${fileSize}MB)`, 'success', 2500);
            }
        });

        // --- H. Fee Payment Real-Time Balance Calculator & Quick Pills ---
        const amountInput = document.getElementById('payment_amount_input') || document.querySelector('input[name="amount"]');
        const calcIndicator = document.getElementById('fee_calc_indicator');
        const quickPills = document.querySelectorAll('.fee-pill-btn');

        if (amountInput) {
            const maxBalance = parseFloat(amountInput.getAttribute('max')) || 0;

            function updateFeeCalc() {
                const paying = parseFloat(amountInput.value) || 0;
                const remaining = Math.max(0, maxBalance - paying);

                if (calcIndicator) {
                    if (paying <= 0) {
                        calcIndicator.innerHTML = `<span class="text-muted"><i class="bi bi-info-circle me-1"></i> Enter an amount above to preview your remaining balance.</span>`;
                        calcIndicator.className = 'fee-calc-indicator';
                    } else if (remaining === 0) {
                        calcIndicator.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center text-success">
                                <div><i class="bi bi-check-circle-fill me-1"></i> <strong>Full Payment:</strong> Invoice will be completely settled (₦0.00 debt)!</div>
                                <span class="badge bg-success">100% Paid</span>
                            </div>
                        `;
                        calcIndicator.className = 'fee-calc-indicator cleared';
                    } else {
                        const pct = Math.round((paying / maxBalance) * 100);
                        calcIndicator.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Paying: <strong class="text-islamic">₦${paying.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong> &bull; Remaining: <strong class="text-danger">₦${remaining.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong></div>
                                <span class="badge bg-warning text-dark">${pct}% of invoice</span>
                            </div>
                        `;
                        calcIndicator.className = 'fee-calc-indicator';
                    }
                }
            }

            amountInput.addEventListener('input', updateFeeCalc);

            quickPills.forEach(pill => {
                pill.addEventListener('click', function () {
                    quickPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');

                    const pct = parseFloat(this.getAttribute('data-pct')) || 100;
                    const calculatedAmount = (maxBalance * (pct / 100)).toFixed(2);
                    amountInput.value = calculatedAmount;
                    updateFeeCalc();
                    ISMS.showToast(`Selected ${pct}% instalment (₦${Number(calculatedAmount).toLocaleString()})`, 'info', 2000);
                });
            });

            // Initial calc
            if (maxBalance > 0) updateFeeCalc();
        }

        // --- I. Form Submit Loading Spinner & Prevent Double Submit ---
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                // If form has invalid HTML5 fields, don't trigger spinner
                if (!this.checkValidity()) return;

                const submitBtn = this.querySelector('button[type="submit"]:not([name="cancel"])');
                if (submitBtn && !submitBtn.classList.contains('no-spin')) {
                    submitBtn.classList.add('is-submitting');
                    submitBtn.disabled = true;
                    const origHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Please wait...`;

                    // Safety timeout in case browser prevents submit or navigation delays
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('is-submitting');
                        submitBtn.innerHTML = origHtml;
                    }, 8000);
                }
            });
        });

        // --- J. Payment Method Switcher in Pay Fee ---
        const paymentMethodSelect = document.getElementById('payment_method_select');
        const bankTransferSection = document.getElementById('bank_transfer_section');
        const onlineGatewaySection = document.getElementById('online_gateway_section');
        const payMethodCards = document.querySelectorAll('#payment_method_cards .pay-method-card');
        const payBtnLabel = document.getElementById('pay_btn_label');

        function applyPaymentMethod(val, showToast) {
            // Update hidden select value
            if (paymentMethodSelect) paymentMethodSelect.value = val;

            // Toggle sections
            if (val === 'bank_transfer') {
                if (bankTransferSection) {
                    bankTransferSection.style.display = 'block';
                    // Re-enable form inputs inside bank section
                    bankTransferSection.querySelectorAll('input, textarea').forEach(el => el.disabled = false);
                }
                if (onlineGatewaySection) onlineGatewaySection.style.display = 'none';
                if (payBtnLabel) payBtnLabel.textContent = 'Submit Bank Transfer Claim';
                if (showToast) ISMS.showToast('Switched to Direct Bank Transfer', 'info', 2000);
            } else {
                if (bankTransferSection) {
                    bankTransferSection.style.display = 'none';
                    // Disable file/text inputs so they don't affect form validation when hidden
                    bankTransferSection.querySelectorAll('input, textarea').forEach(el => el.disabled = true);
                }
                if (onlineGatewaySection) onlineGatewaySection.style.display = 'block';
                const gatewayName = val.includes('flutterwave') ? 'Flutterwave' : 'Paystack';
                if (payBtnLabel) payBtnLabel.textContent = `Pay Now via ${gatewayName}`;
                if (showToast) ISMS.showToast(`Switched to ${gatewayName} — Instant Payment`, 'info', 2000);
            }

            // Update visual card styles
            payMethodCards.forEach(card => {
                const cardVal = card.getAttribute('data-value');
                const checkIcon = card.querySelector('i.ms-auto');
                if (cardVal === val) {
                    card.style.border = '2px solid var(--islamic-green)';
                    card.style.background = '#eaf4f0';
                    if (checkIcon) {
                        checkIcon.className = 'bi bi-check-circle-fill text-islamic ms-auto';
                        checkIcon.style.fontSize = '1.1rem';
                    }
                } else {
                    card.style.border = '2px solid #dee2e6';
                    card.style.background = '#ffffff';
                    if (checkIcon) {
                        checkIcon.className = 'bi bi-circle text-muted ms-auto';
                        checkIcon.style.fontSize = '1.1rem';
                    }
                }
            });
        }

        // Wire up clickable cards
        payMethodCards.forEach(card => {
            card.addEventListener('click', function () {
                applyPaymentMethod(this.getAttribute('data-value'), true);
            });
        });

        // Apply correct initial state on page load (no toast)
        if (paymentMethodSelect) {
            applyPaymentMethod(paymentMethodSelect.value, false);
        }

        // --- K. Initialize Bootstrap Tooltips ---
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                new bootstrap.Tooltip(el);
            });
        }

        // --- L. Auto-dismiss Flash Alerts after 7 seconds ---
        document.querySelectorAll('.alert.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                const bsAlert = bootstrap?.Alert ? bootstrap.Alert.getOrCreateInstance(alert) : null;
                if (bsAlert) {
                    bsAlert.close();
                } else {
                    alert.classList.add('fade');
                    setTimeout(() => alert.remove(), 400);
                }
            }, 7000);
        });

    });
})();
