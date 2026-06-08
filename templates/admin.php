<?php
declare(strict_types=1);
/** @var \OCP\IL10N $l */
/** @var array $_ */

\OCP\Util::addScript('beste_schule', 'admin');
\OCP\Util::addStyle('beste_schule', 'style');
?>

<script>
    if (typeof OCS === 'undefined') {
        window.OCS = {
            linkTo: function(path) {
                return OC.linkToOCS('beste_schule/api/v1', 2) + path;
            }
        };
    }
</script>

<div id="beste-schule-admin" class="section">
    <h2><?php p($l->t('beste.schule – Account Management')); ?></h2>
    <p class="settings-hint">
        <?php p($l->t('Map beste.schule Personal Access Tokens to Nextcloud users. Background sync will keep grades and calendar events up to date.')); ?>
    </p>

    <!-- Add account form -->
    <div class="bs-card" id="bs-add-account-section">
        <h3><?php p($l->t('Add Account')); ?></h3>
        <form id="bs-add-account-form" class="bs-form">
            <div class="bs-form-row">
                <label for="bs-userId"><?php p($l->t('Nextcloud User')); ?></label>
                <input type="text" id="bs-userId" name="userId"
                       placeholder="<?php p($l->t('username')); ?>" required />
            </div>
            <div class="bs-form-row">
                <label for="bs-token"><?php p($l->t('beste.schule Access Token')); ?></label>
                <input type="password" id="bs-token" name="token"
                       placeholder="<?php p($l->t('Personal Access Token from beste.schule')); ?>" required />
                <button type="button" id="bs-validate-btn" class="button">
                    <?php p($l->t('Validate')); ?>
                </button>
            </div>
            <div id="bs-student-select-row" class="bs-form-row" style="display:none">
                <label for="bs-studentId"><?php p($l->t('Student')); ?></label>
                <select id="bs-studentId" name="studentId"></select>
            </div>
            <div class="bs-form-row">
                <label for="bs-calendarUri"><?php p($l->t('Calendar (for journal sync)')); ?></label>
                <select id="bs-calendarUri" name="calendarUri">
                    <option value=""><?php p($l->t('Disabled')); ?></option>
                </select>
            </div>
            <div class="bs-form-row">
                <label for="bs-syncInterval"><?php p($l->t('Sync interval (hours)')); ?></label>
                <input type="number" id="bs-syncInterval" name="syncInterval"
                       value="24" min="1" max="168" />
            </div>
            <div class="bs-form-row">
                <button type="submit" class="button primary"><?php p($l->t('Save Account')); ?></button>
            </div>
            <div id="bs-add-error" class="bs-error" style="display:none"></div>
        </form>
    </div>

    <!-- Accounts table -->
    <div class="bs-card">
        <h3><?php p($l->t('Registered Accounts')); ?></h3>
        <div id="bs-accounts-loading" class="icon-loading"></div>
        <table id="bs-accounts-table" class="bs-table" style="display:none">
            <thead>
                <tr>
                    <th><?php p($l->t('User')); ?></th>
                    <th><?php p($l->t('Student')); ?></th>
                    <th><?php p($l->t('Calendar')); ?></th>
                    <th><?php p($l->t('Sync every')); ?></th>
                    <th><?php p($l->t('Last sync')); ?></th>
                    <th><?php p($l->t('Status')); ?></th>
                    <th><?php p($l->t('Actions')); ?></th>
                </tr>
            </thead>
            <tbody id="bs-accounts-tbody"></tbody>
        </table>
        <p id="bs-no-accounts" style="display:none">
            <?php p($l->t('No accounts configured yet.')); ?>
        </p>
    </div>
</div>
