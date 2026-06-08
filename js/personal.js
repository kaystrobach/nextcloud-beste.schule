/**
 * beste.schule – Personal settings (connect your own account)
 */
(function () {
    'use strict';

    const OCS_BASE = (window.OCS && window.OCS.linkTo) ? window.OCS.linkTo('') : OC.generateUrl('/ocs/v2.php/apps/beste_schule/api/v1', {});
    const headers  = { 'OCS-APIREQUEST': 'true', 'Accept': 'application/json' };

    async function apiFetch(method, path, body)
    {
        const opts = { method, headers: { ...headers } };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res  = await fetch(OCS_BASE + path, opts);
        const json = await res.json();
        if (!res.ok) {
            const errorMsg = (json.ocs && json.ocs.meta && json.ocs.meta.message) || (json.ocs && json.ocs.data && json.ocs.data.message) || `HTTP ${res.status}`;
            throw new Error(errorMsg);
        }
        return (json.ocs && json.ocs.data) ? json.ocs.data : json;
    }

    async function loadMyAccounts()
    {
        const container = document.getElementById('bs-p-accounts-list');
        const loading   = document.getElementById('bs-p-accounts-loading');
        if (!container) {
            return;
        }
        container.innerHTML   = '';
        loading.style.display = '';

        try {
            const accounts = await apiFetch('GET', '/accounts');
            (accounts || []).forEach(a => {
                const item = document.createElement('div');
                item.className = 'bs-account-item';
                const lastSync = a.lastSyncAt
                ? new Date(a.lastSyncAt).toLocaleString()
                : t('beste_schule', 'Never');
                item.innerHTML = `
                < div class = "bs-account-info" >
                    < div class = "bs-account-name" > ${escHtml(a.studentName)} < / div >
                    < div class = "bs-account-meta" >
                        ${t('beste_schule', 'Last sync')}: ${escHtml(lastSync)}
                        ${a.lastSyncError ? ` < span style = "color:var(--color-error)" > ⚠ ${escHtml(a.lastSyncError)} < / span > ` : ''}
                    <  / div >
                <  / div >
                < button class = "button bs-p-sync-btn" data - id = "${a.id}" > ${t('beste_schule', 'Sync')} < / button >
                < button class = "button bs-p-delete-btn" data - id = "${a.id}" > ${t('beste_schule', 'Remove')} < / button > `;
                container.appendChild(item);
                });

            if (!accounts || accounts.length === 0) {
                container.innerHTML = ` < p > ${t('beste_schule', 'No accounts connected yet.')} < / p > `;
            }
        } catch (e) {
            container.innerHTML = ` < p style = "color:var(--color-error)" > ${escHtml(e.message)} < / p > `;
        } finally {
            loading.style.display = 'none';
        }
    }

    async function loadCalendars()
    {
        const sel = document.getElementById('bs-p-calendarUri');
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

    const btnPop = document.getElementById('bs-p-validate-btn');
    if (btnPop) {
        btnPop.addEventListener('click', async() => {
            const token = document.getElementById('bs-p-token').value.trim();
            if (!token) {
                return;
            }
            try {
                const data = await apiFetch('POST', '/validate', { token });
                const students = data.students || [];
                const sel = document.getElementById('bs-p-studentId');
                sel.innerHTML = '';
                students.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.forename} ${s.name}`;
                    sel.appendChild(opt);
                });
                document.getElementById('bs-p-student-row').style.display = '';
            } catch (e) {
                showError('bs-p-error', e.message);
            }
        });
    }

    const formPersonal = document.getElementById('bs-personal-form');
    if (formPersonal) {
        formPersonal.addEventListener('submit', async(ev) => {
            ev.preventDefault();
            const form = ev.target;
            try {
                await apiFetch('POST', '/accounts', {
                    token:        form.token.value.trim(),
                    studentId: parseInt((form.studentId && form.studentId.value) || '0', 10),
                    calendarUri:  form.calendarUri.value.trim(),
                    syncInterval: parseInt(form.syncInterval.value, 10),
                });
                form.reset();
                document.getElementById('bs-p-student-row').style.display = 'none';
                document.getElementById('bs-p-error').style.display       = 'none';
                await loadMyAccounts();
            } catch (e) {
                showError('bs-p-error', e.message);
            }
        });
    }

    const listAccounts = document.getElementById('bs-p-accounts-list');
    if (listAccounts) {
        listAccounts.addEventListener('click', async(ev) => {
            const btn = ev.target.closest('button');
            if (!btn) {
                return;
            }
            const id = btn.dataset.id;

            if (btn.classList.contains('bs-p-sync-btn')) {
                btn.disabled    = true;
                btn.textContent = t('beste_schule', 'Syncing…');
                try {
                    await apiFetch('POST', ` / accounts / ${id} / sync`);
                    await loadMyAccounts();
                } catch (e) {
                    alert(e.message);
                    btn.disabled    = false;
                    btn.textContent = t('beste_schule', 'Sync');
                }
            }
            if (btn.classList.contains('bs-p-delete-btn')) {
                if (!confirm(t('beste_schule', 'Remove this account?'))) {
                    return;
                }
                try {
                    await apiFetch('DELETE', ` / accounts / ${id}`);
                    await loadMyAccounts();
                } catch (e) {
                    alert(e.message);
                }
            }
        });
    }

    function showError(id, msg)
    {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent   = msg;
        el.style.display = '';
    }

    function escHtml(str)
    {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadMyAccounts();
        loadCalendars();
    });
})();
