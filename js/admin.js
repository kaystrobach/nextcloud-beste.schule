/**
 * beste.schule – Admin account management
 * Communicates with the OCS API at /apps/beste_schule/api/v1/
 */
(function () {
    'use strict';

    const OCS_BASE = OC.linkToOCS('apps/beste_schule/api/v1', 2);
    const headers  = { 'OCS-APIREQUEST': 'true', 'Accept': 'application/json' };

    // ── API helpers ───────────────────────────────────────────────────────────

    async function apiFetch(method, path, body)
    {
        const opts = { method, headers: { ...headers } };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(OCS_BASE + path, opts);
        const json = await res.json();
        if (!res.ok) {
            const errorMsg = (json.ocs && json.ocs.meta && json.ocs.meta.message) || (json.ocs && json.ocs.data && json.ocs.data.message) || `HTTP ${res.status}`;
            throw new Error(errorMsg);
        }
        return (json.ocs && json.ocs.data) ? json.ocs.data : json;
    }

    // ── Load accounts table ───────────────────────────────────────────────────

    async function loadAccounts()
    {
        const loading = document.getElementById('bs-accounts-loading');
        const table   = document.getElementById('bs-accounts-table');
        const noMsg   = document.getElementById('bs-no-accounts');
        const tbody   = document.getElementById('bs-accounts-tbody');

        if (!loading) {
            return;
        }
        loading.style.display = '';
        table.style.display   = 'none';
        noMsg.style.display   = 'none';

        try {
            const accounts = await apiFetch('GET', '/admin/accounts');
            tbody.innerHTML = '';

            if (!accounts || accounts.length === 0) {
                noMsg.style.display = '';
            } else {
                accounts.forEach(renderAccountRow);
                table.style.display = '';
            }
        } catch (e) {
            showError('bs-accounts-loading', e.message);
        } finally {
            loading.style.display = 'none';
        }
    }

    async function loadCalendars()
    {
        const sel = document.getElementById('bs-calendarUri');
        if (!sel) {
            return;
        }
        try {
            const calendars = await apiFetch('GET', '/calendars');
            (calendars || []).forEach(cal => {
                const opt = document.createElement('option');
                opt.value = cal.uri;
                opt.textContent = cal.displayname;
                sel.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load calendars', e);
        }
    }

    function renderAccountRow(account)
    {
        const tbody = document.getElementById('bs-accounts-tbody');
        const tr    = document.createElement('tr');
        tr.dataset.id = account.id;

        const lastSync = account.lastSyncAt
            ? new Date(account.lastSyncAt).toLocaleString()
            : t('beste_schule', 'Never');

        const statusBadge = account.lastSyncError
            ? `<span class="bs-badge bs-badge-error" title="${escHtml(account.lastSyncError)}">${t('beste_schule', 'Error')}</span>`
            : account.lastSyncAt
                ? `<span class="bs-badge bs-badge-ok">${t('beste_schule', 'OK')}</span>`
                : `<span class="bs-badge bs-badge-none">${t('beste_schule', 'Pending')}</span>`;

        tr.innerHTML = `
            <td>${escHtml(account.userId)}</td>
            <td>${escHtml(account.studentName)} <small>(ID: ${account.studentId})</small></td>
            <td>${account.calendarUri ? escHtml(account.calendarUri) : '<em>—</em>'}</td>
            <td>${account.syncInterval}h</td>
            <td>${escHtml(lastSync)}</td>
            <td>${statusBadge}</td>
            <td>
                <button class="button bs-sync-btn" data-id="${account.id}">${t('beste_schule', 'Sync')}</button>
                <button class="button bs-delete-btn" data-id="${account.id}">${t('beste_schule', 'Delete')}</button>
            </td>`;

        tbody.appendChild(tr);
    }

    // ── Validate token → populate student dropdown ────────────────────────────

    async function validateToken(token, studentSelectId)
    {
        const data = await apiFetch('POST', '/validate', { token });
        const students = data.students || [];
        const sel = document.getElementById(studentSelectId);
        sel.innerHTML = '';
        students.forEach(s => {
            const opt = document.createElement('option');
            opt.value       = s.id;
            opt.textContent = `${s.forename} ${s.name}`;
            sel.appendChild(opt);
        });
        document.getElementById('bs-student-select-row').style.display = '';
        return students;
    }

    // ── Event bindings ────────────────────────────────────────────────────────

    const btnVal = document.getElementById('bs-validate-btn');
    if (btnVal) {
        btnVal.addEventListener('click', async() => {
            const token = document.getElementById('bs-token').value.trim();
            if (!token) {
                return;
            }
            try {
                await validateToken(token, 'bs-studentId');
            } catch (e) {
                showError('bs-add-error', t('beste_schule', 'Token validation failed: ') + e.message);
            }
        });
    }

    const formAdd = document.getElementById('bs-add-account-form');
    if (formAdd) {
        formAdd.addEventListener('submit', async(ev) => {
            ev.preventDefault();
            const form = ev.target;
            const data = {
                userId: form.userId.value.trim(),
                token: form.token.value.trim(),
                studentId: parseInt((form.studentId && form.studentId.value) || '0', 10),
                calendarUri: form.calendarUri.value.trim(),
                syncInterval: parseInt(form.syncInterval.value, 10),
            };
            try {
                await apiFetch('POST', '/admin/accounts', data);
                form.reset();
                document.getElementById('bs-student-select-row').style.display = 'none';
                document.getElementById('bs-add-error').style.display = 'none';
                await loadAccounts();
            } catch (e) {
                showError('bs-add-error', e.message);
            }
        });
    }

    const tableBody = document.getElementById('bs-accounts-tbody');
    if (tableBody) {
        tableBody.addEventListener('click', async(ev) => {
            const btn = ev.target.closest('button');
            if (!btn) {
                return;
            }
            const id = btn.dataset.id;

            if (btn.classList.contains('bs-sync-btn')) {
                btn.disabled = true;
                btn.textContent = t('beste_schule', 'Syncing…');
                try {
                    await apiFetch('POST', ` / admin / accounts / ${id} / sync`);
                    await loadAccounts();
                } catch (e) {
                    alert(t('beste_schule', 'Sync failed: ') + e.message);
                    btn.disabled = false;
                    btn.textContent = t('beste_schule', 'Sync');
                }
            }

            if (btn.classList.contains('bs-delete-btn')) {
                if (!confirm(t('beste_schule', 'Delete this account and all its cached data?'))) {
                    return;
                }
                try {
                    await apiFetch('DELETE', ` / admin / accounts / ${id}`);
                    await loadAccounts();
                } catch (e) {
                    alert(t('beste_schule', 'Delete failed: ') + e.message);
                }
            }
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    function showError(containerId, msg)
    {
        const el = document.getElementById(containerId);
        if (!el) {
            return;
        }
        el.textContent    = msg;
        el.style.display  = '';
    }

    function escHtml(str)
    {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        loadAccounts();
        loadCalendars();
    });
})();
