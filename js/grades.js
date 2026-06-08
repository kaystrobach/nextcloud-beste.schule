/**
 * beste.schule – Grades view
 * Shows Noten and Endnoten for all linked accounts of the current user.
 */
(function () {
    'use strict';

    const OCS_BASE = OC.linkToOCS('apps/beste_schule/api/v1', 2);
    const headers  = { 'OCS-APIREQUEST': 'true', 'Accept': 'application/json' };

    let allAccounts   = [];
    let currentSection = 'grades'; // 'grades' | 'finalgrades'

    // ── API helpers ───────────────────────────────────────────────────────────

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

    // ── Navigation ────────────────────────────────────────────────────────────

    function setSection(name)
    {
        currentSection = name;
        document.getElementById('bs-section-grades').style.display     = name === 'grades'      ? '' : 'none';
        document.getElementById('bs-section-finalgrades').style.display = name === 'finalgrades' ? '' : 'none';
        document.getElementById('bs-nav-grades').classList.toggle('active',      name === 'grades');
        document.getElementById('bs-nav-finalgrades').classList.toggle('active', name === 'finalgrades');

        if (name === 'grades') {
            loadGrades();
        } else {
            loadFinalGrades();
        }
    }

    // ── Account dropdown ──────────────────────────────────────────────────────

    async function loadAccounts()
    {
        const data = await apiFetch('GET', '/accounts');
        allAccounts = data || [];
        const sel   = document.getElementById('bs-account-select');
        sel.innerHTML = '';

        const allOpt = document.createElement('option');
        allOpt.value       = '0';
        allOpt.textContent = t('beste_schule', 'All accounts');
        sel.appendChild(allOpt);

        allAccounts.forEach(a => {
            const opt = document.createElement('option');
            opt.value       = a.id;
            opt.textContent = a.studentName;
            sel.appendChild(opt);
        });

        if (allAccounts.length > 0) {
            updateLastSync(allAccounts[0]);
        }
    }

    function selectedAccountId()
    {
        return parseInt(document.getElementById('bs-account-select').value, 10) || 0;
    }

    function updateLastSync(account)
    {
        const el = document.getElementById('bs-last-sync');
        if (!el) {
            return;
        }
        if (account && account.lastSyncAt) {
            el.textContent = t('beste_schule', 'Last sync: ') + new Date(account.lastSyncAt).toLocaleString();
        } else {
            el.textContent = t('beste_schule', 'Not yet synced');
        }
        if (account && account.lastSyncError) {
            el.textContent += ' ⚠ ' + account.lastSyncError;
            el.style.color = 'var(--color-error)';
        } else {
            el.style.color = '';
        }
    }

    // ── Grades ────────────────────────────────────────────────────────────────

    async function loadGrades()
    {
        const tbody   = document.getElementById('bs-grades-tbody');
        const loading = document.getElementById('bs-grades-loading');
        const noMsg   = document.getElementById('bs-no-grades');
        const avgEl   = document.getElementById('bs-grade-average');

        tbody.innerHTML   = '';
        loading.style.display = '';
        noMsg.style.display   = 'none';
        avgEl.textContent     = '';

        try {
            const accountId = selectedAccountId();
            const path      = accountId > 0 ? ` / grades ? accountId = ${accountId}` : '/grades';
            const results   = await apiFetch('GET', path);

            let totalNumerator = 0, totalCount = 0;

            (results || []).forEach(result => {
                const grades = result.grades || [];
                grades.forEach(g => {
                    tbody.appendChild(renderGradeRow(g));
                    const n = parseGrade(g.value);
                    if (n !== null) {
                        totalNumerator += n;
                        totalCount++;
                    }
                });
            });

            if (tbody.children.length === 0) {
                noMsg.style.display = '';
            } else if (totalCount > 0) {
                const avg = (totalNumerator / totalCount).toFixed(2);
                avgEl.textContent = `Ø ${avg}`;
            }
        } catch (e) {
            showBanner(e.message);
        } finally {
            loading.style.display = 'none';
        }
    }

    function renderGradeRow(g)
    {
        const tr = document.createElement('tr');
        const gradeClass = gradeColorClass(g.value);
        tr.innerHTML = `
            < td > ${g.givenAt ? formatDate(g.givenAt) : '—'} < / td >
            < td > ${escHtml(g.subjectName)} < / td >
            < td class = "${gradeClass}" > ${escHtml(g.value)} < / td >
            < td > ${g.collectionName ? escHtml(g.collectionName) : '—'} < / td >
            < td > ${g.teacherName ? escHtml(g.teacherName) : '—'} < / td >
            < td > ${g.weight !== null ? g.weight : '—'} < / td > `;
        return tr;
    }

    // ── Final grades ──────────────────────────────────────────────────────────

    async function loadFinalGrades()
    {
        const container = document.getElementById('bs-finalgrades-container');
        const loading   = document.getElementById('bs-finalgrades-loading');
        const noMsg     = document.getElementById('bs-no-finalgrades');

        container.innerHTML   = '';
        loading.style.display = '';
        noMsg.style.display   = 'none';

        try {
            const accountId = selectedAccountId();
            const path      = accountId > 0 ? ` / finalgrades ? accountId = ${accountId}` : '/finalgrades';
            const results   = await apiFetch('GET', path);

            let any = false;
            (results || []).forEach(result => {
                const finalGrades = result.finalgrades || [];
                if (finalGrades.length === 0) {
                    return;
                }
                any = true;
                container.appendChild(renderFinalGradesBlock(result.account, finalGrades));
            });

            if (!any) {
                noMsg.style.display = '';
            }
        } catch (e) {
            showBanner(e.message);
        } finally {
            loading.style.display = 'none';
        }
    }

    function renderFinalGradesBlock(account, finalGrades)
    {
        // Group by interval
        const bySemester = {};
        finalGrades.forEach(fg => {
            const key  = (fg.intervalId !== undefined && fg.intervalId !== null) ? fg.intervalId : 0;
            const name = fg.intervalName || `Halbjahr ${key}`;
            if (!bySemester[key]) {
                bySemester[key] = { name, items: [] };
            }
            bySemester[key].items.push(fg);
        });

        const wrapper = document.createElement('div');
        if (account) {
            const h = document.createElement('h3');
            h.textContent = account.studentName;
            wrapper.appendChild(h);
        }

        Object.values(bySemester).forEach(sem => {
            const block = document.createElement('div');
            block.className = 'bs-semester-block';
            block.innerHTML = ` < h3 > ${escHtml(sem.name)} < / h3 > `;

            const table = document.createElement('table');
            table.className = 'bs-table';
            table.innerHTML = `
                < thead >
                    < tr >
                        < th > ${t('beste_schule', 'Fach')} < / th >
                        < th > ${t('beste_schule', 'Endnote')} < / th >
                    <  / tr >
                <  / thead > `;
            const tbody = document.createElement('tbody');
            sem.items.forEach(fg => {
                const displayValue = fg.value || fg.valueCalc || '—';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    < td > ${escHtml(fg.subjectName)} < / td >
                    < td class = "${gradeColorClass(displayValue)}" > ${escHtml(displayValue)} < / td > `;
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            block.appendChild(table);
            wrapper.appendChild(block);
        });

        return wrapper;
    }

    // ── Sync button ───────────────────────────────────────────────────────────

    const btnSync = document.getElementById('bs-sync-btn');
    if (btnSync) {
        btnSync.addEventListener('click', async() => {
            const btn = document.getElementById('bs-sync-btn');
            const accountId = selectedAccountId();
            if (accountId === 0) {
                // Sync all accounts
                btn.disabled = true;
                try {
                    for (let i = 0; i < allAccounts.length; i++) {
                        const a = allAccounts[i];
                        await apiFetch('POST', ` / accounts / ${a.id} / sync`);
                    }
                } catch (e) {
                    showBanner(e.message);
                } finally {
                    btn.disabled = false;
                    setSection(currentSection);
                }
                return;
            }
            btn.disabled = true;
            try {
                await apiFetch('POST', ` / accounts / ${accountId} / sync`);
                const account = allAccounts.find(a => a.id === accountId);
                if (account) {
                    updateLastSync(account);
                }
                setSection(currentSection);
            } catch (e) {
                showBanner(e.message);
            } finally {
                btn.disabled = false;
            }
        });
    }

    const navGrades = document.getElementById('bs-nav-grades');
    if (navGrades) {
        navGrades.addEventListener('click', (e) => {
            e.preventDefault();
            setSection('grades');
        });
    }

    const navFinal = document.getElementById('bs-nav-finalgrades');
    if (navFinal) {
        navFinal.addEventListener('click', (e) => {
            e.preventDefault();
            setSection('finalgrades');
        });
    }

    const accSelect = document.getElementById('bs-account-select');
    if (accSelect) {
        accSelect.addEventListener('change', () => {
            const id = selectedAccountId();
            const account = allAccounts.find(a => a.id === id);
            if (account) {
                updateLastSync(account);
            } else {
                // "All accounts" selected
                const el = document.getElementById('bs-last-sync');
                if (el) {
                    el.textContent = '';
                }
            }
            setSection(currentSection);
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    function parseGrade(value)
    {
        const m = String(value).trim().match(/^([1-6])([+\-]?)(\+\/\-)?$/);
        if (!m) {
            return null;
        }
        let base = parseFloat(m[1]);
        if (m[3]) {
            return base; // +/- modifier
        }
        if (m[2] === '+') {
            return base - 0.25;
        }
        if (m[2] === '-') {
            return base + 0.25;
        }
        return base;
    }

    function gradeColorClass(value)
    {
        const n = parseGrade(value);
        if (n === null) {
            return '';
        }
        if (n <= 1.5) {
            return 'bs-grade-1';
        }
        if (n <= 2.5) {
            return 'bs-grade-2';
        }
        if (n <= 3.5) {
            return 'bs-grade-3';
        }
        if (n <= 4.5) {
            return 'bs-grade-4';
        }
        return 'bs-grade-5';
    }

    function formatDate(dateStr)
    {
        if (!dateStr) {
            return '';
        }
        try {
            return new Date(dateStr).toLocaleDateString();
        } catch {
            return dateStr;
        }
    }

    function escHtml(str)
    {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showBanner(msg)
    {
        const el = document.getElementById('bs-error-banner');
        if (!el) {
            return;
        }
        el.textContent   = msg;
        el.style.display = '';
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', async() => {
        try {
            await loadAccounts();
            setSection('grades');
        } catch (e) {
            showBanner(e.message);
        }
    });
})();
