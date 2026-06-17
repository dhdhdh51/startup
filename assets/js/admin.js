/**
 * Bharat SEO CRM - Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    let overlay = document.querySelector('.sidebar-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    overlay.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });

    // Select All Checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }

    // Individual checkboxes
    document.querySelectorAll('.lead-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    // Copy to clipboard
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const el = document.getElementById(target) || this.parentElement.querySelector('.outreach-box, .outreach-text, pre');
            if (el) {
                const text = el.textContent || el.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const original = this.textContent;
                    this.textContent = 'Copied!';
                    setTimeout(() => this.textContent = original, 2000);
                });
            }
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});


function updateSelectedCount() {
    const checked = document.querySelectorAll('.lead-checkbox:checked');
    const counter = document.getElementById('selectedCount');
    if (counter) {
        counter.textContent = checked.length + ' selected';
    }
}

function getSelectedLeadIds() {
    const checked = document.querySelectorAll('.lead-checkbox:checked');
    return Array.from(checked).map(cb => cb.value);
}

function confirmDelete(leadId) {
    if (confirm('Are you sure you want to delete this lead? This cannot be undone.')) {
        window.location.href = 'leads.php?action=delete&id=' + leadId + '&csrf=' + getCSRFToken();
    }
}

function getCSRFToken() {
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

// Bulk audit via AJAX
function runBulkAudit() {
    const ids = getSelectedLeadIds();
    if (ids.length === 0) {
        alert('Please select at least one lead to audit.');
        return;
    }
    
    const progressBar = document.getElementById('auditProgress');
    const statusText = document.getElementById('auditStatus');
    const progressFill = document.querySelector('#auditProgress .fill');
    
    if (progressBar) progressBar.style.display = 'block';
    
    let completed = 0;
    const total = ids.length;
    
    function auditNext() {
        if (completed >= total) {
            if (statusText) statusText.textContent = 'Audit complete! ' + total + ' leads audited.';
            setTimeout(() => location.reload(), 2000);
            return;
        }
        
        const id = ids[completed];
        if (statusText) statusText.textContent = 'Auditing lead ' + (completed + 1) + ' of ' + total + '...';
        if (progressFill) progressFill.style.width = ((completed + 1) / total * 100) + '%';
        
        fetch('lead-audit.php?ajax=1&id=' + id)
            .then(response => response.json())
            .then(data => {
                completed++;
                setTimeout(auditNext, 2000); // Delay between audits
            })
            .catch(err => {
                completed++;
                setTimeout(auditNext, 2000);
            });
    }
    
    auditNext();
}

// Generate outreach messages in bulk
function generateBulkMessages() {
    const ids = getSelectedLeadIds();
    if (ids.length === 0) {
        alert('Please select at least one lead.');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'outreach.php';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = getCSRFToken();
    form.appendChild(csrfInput);
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'generate';
    form.appendChild(actionInput);
    
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'lead_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

// WhatsApp link opener
function openWhatsApp(phone, message) {
    phone = phone.replace(/[^0-9]/g, '');
    if (phone.length === 10) phone = '91' + phone;
    const url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
    window.open(url, '_blank');
}

// Mark as contacted
function markContacted(leadId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'leads.php';
    
    const inputs = [
        { name: 'csrf_token', value: getCSRFToken() },
        { name: 'action', value: 'update_status' },
        { name: 'lead_id', value: leadId },
        { name: 'status', value: 'Contacted' }
    ];
    
    inputs.forEach(i => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = i.name;
        input.value = i.value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}
