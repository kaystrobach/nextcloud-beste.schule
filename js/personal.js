/**
 * beste.schule – Personal settings (connect your own account)
 */
(function () {
    'use strict';

    const OCS_BASE = OC.linkToOCS('apps/beste_schule/api/v1', 2);
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
                item.id = `bs-account-${a.id}`;
                item.className = 'bs-account-item';
                const lastSync = a.lastSyncAt
                ? new Date(a.lastSyncAt).toLocaleString()
                : t('beste_schule', 'Never');
                item.innerHTML = `
                <div class="bs-account-info">
                    <div class="bs-account-name">${escHtml(a.studentName)}</div>
                    <div class="bs-account-meta">
                        ${t('beste_schule', 'Last sync')}: ${escHtml(lastSync)}
                        ${a.lastSyncError ? `<span style="color:var(--color-error)">⚠ ${escHtml(a.lastSyncError)}</span>` : ''}
                    </div>
                    <div class="bs-account-settings" style="font-size: 0.85em; margin-top: 5px;">
                        <div><strong>${t('beste_schule', 'Calendar')}:</strong> ${escHtml(a.calendarUri || t('beste_schule', 'None'))}</div>
                        <div><strong>${t('beste_schule', 'Sync Interval')}:</strong> ${a.syncInterval}h</div>
                        <div><strong>${t('beste_schule', 'Address')}:</strong> ${escHtml(a.address || '-')}</div>
                    </div>
                    <div class="bs-account-logs" id="bs-logs-${a.id}" style="font-size: 0.8em; margin-top: 5px; color: var(--color-text-maxcontrast); display: none;">
                    </div>
                </div>
                <button class="button bs-p-edit-btn" data-id="${a.id}">${t('beste_schule', 'Edit')}</button>
                <button class="button bs-p-logs-btn" data-id="${a.id}">${t('beste_schule', 'Logs')}</button>
                <button class="button bs-p-sync-btn" data-id="${a.id}">${t('beste_schule', 'Sync')}</button>
                <button class="button bs-p-delete-btn" data-id="${a.id}">${t('beste_schule', 'Remove')}</button>`;
                container.appendChild(item);
                });

            if (!accounts || accounts.length === 0) {
                container.innerHTML = `<p>${t('beste_schule', 'No accounts connected yet.')}</p>`;
            }
        } catch (e) {
            container.innerHTML = `<p style="color:var(--color-error)">${escHtml(e.message)}</p>`;
        } finally {
            loading.style.display = 'none';
        }
    }

    let allCalendars = [];

    async function loadCalendars()
    {
        const sel = document.getElementById('bs-p-calendarUri');
        if (!sel) {
            return;
        }
        try {
            allCalendars = await apiFetch('GET', '/calendars');
            allCalendars.forEach(cal => {
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
                    address:      form.address.value.trim(),
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

            if (btn.classList.contains('bs-p-edit-btn')) {
                const item = document.getElementById(`bs-account-${id}`);
                const account = await apiFetch('GET', `/accounts`); // Find the one
                const a = (account || []).find(acc => acc.id == id);
                if (!a) return;

                item.innerHTML = `
                <form class="bs-edit-form" style="width:100%">
                    <h4>${t('beste_schule', 'Edit Settings')}</h4>
                    <div class="bs-form-row">
                        <label>${t('beste_schule', 'Calendar')}</label>
                        <select name="calendarUri">
                            <option value="" ${!a.calendarUri ? 'selected' : ''}>${t('beste_schule', 'Disabled')}</option>
                            ${allCalendars.map(c => `<option value="${c.uri}" ${c.uri === a.calendarUri ? 'selected' : ''}>${escHtml(c.displayname)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="bs-form-row">
                        <label>${t('beste_schule', 'Sync Interval')}</label>
                        <input type="number" name="syncInterval" value="${a.syncInterval}" min="1" max="168" />
                    </div>
                    <div class="bs-form-row">
                        <label>${t('beste_schule', 'Address')}</label>
                        <input type="text" name="address" value="${escHtml(a.address || '')}" />
                    </div>
                    <div class="bs-form-row">
                        <button type="submit" class="button primary">${t('beste_schule', 'Save')}</button>
                        <button type="button" class="button bs-p-cancel-btn">${t('beste_schule', 'Cancel')}</button>
                    </div>
                </form>`;

                const editForm = item.querySelector('form');
                editForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    try {
                        await apiFetch('PUT', `/accounts/${id}`, {
                            calendar_uri: editForm.calendarUri.value,
                            sync_interval: parseInt(editForm.syncInterval.value, 10),
                            address: editForm.address.value.trim(),
                        });
                        await loadMyAccounts();
                    } catch (err) {
                        alert(err.message);
                    }
                });
                item.querySelector('.bs-p-cancel-btn').addEventListener('click', () => loadMyAccounts());
                return;
            }

            if (btn.classList.contains('bs-p-sync-btn')) {
                btn.disabled    = true;
                btn.textContent = t('beste_schule', 'Syncing…');
                try {
                    await apiFetch('POST', `/accounts/${id}/sync`);
                    await loadMyAccounts();
                } catch (e) {
                    alert(e.message);
                    btn.disabled    = false;
                    btn.textContent = t('beste_schule', 'Sync');
                }
            }
            if (btn.classList.contains('bs-p-logs-btn')) {
                const logDiv = document.getElementById(`bs-logs-${id}`);
                if (logDiv.style.display === 'none') {
                    try {
                        const logs = await apiFetch('GET', `/accounts/${id}/logs`);
                        logDiv.innerHTML = logs.map(l => `[${l.createdAt}] ${l.level.toUpperCase()}: ${escHtml(l.message)}`).join('<br>');
                        logDiv.style.display = 'block';
                    } catch (e) {
                        alert(e.message);
                    }
                } else {
                    logDiv.style.display = 'none';
                }
            }
            if (btn.classList.contains('bs-p-delete-btn')) {
                if (!confirm(t('beste_schule', 'Remove this account?'))) {
                    return;
                }
                try {
                    await apiFetch('DELETE', `/accounts/${id}`);
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
