<?php
declare(strict_types=1);
/** @var \OCP\IL10N $l */
/** @var array $_ */

\OCP\Util::addScript('beste_schule', 'personal');
\OCP\Util::addStyle('beste_schule', 'style');
?>

<div id="beste-schule-personal" class="section">
    <h2><?php p($l->t('beste.schule')); ?></h2>
    <p class="settings-hint">
        <?php p($l->t('Connect your beste.schule account to sync grades and journal entries.')); ?>
    </p>

    <!-- Add account -->
    <div class="bs-card" id="bs-personal-add-section">
        <h3><?php p($l->t('Add Account')); ?></h3>
        <p class="settings-hint">
            <?php p($l->t('Get your Personal Access Token from beste.schule → Benutzerkonto → API → Personal Access Token.')); ?>
        </p>
        <form id="bs-personal-form" class="bs-form">
            <div class="bs-form-row">
                <label for="bs-p-token"><?php p($l->t('Access Token')); ?></label>
                <input type="password" id="bs-p-token" name="token"
                       placeholder="<?php p($l->t('Paste your Personal Access Token')); ?>" required />
                <button type="button" id="bs-p-validate-btn" class="button">
                    <?php p($l->t('Validate')); ?>
                </button>
            </div>
            <div id="bs-p-student-row" class="bs-form-row" style="display:none">
                <label for="bs-p-studentId"><?php p($l->t('Student')); ?></label>
                <select id="bs-p-studentId" name="studentId"></select>
            </div>
            <div class="bs-form-row">
                <label for="bs-p-calendarUri"><?php p($l->t('Calendar for journal')); ?></label>
                <select id="bs-p-calendarUri" name="calendarUri">
                    <option value=""><?php p($l->t('Disabled')); ?></option>
                </select>
            </div>
            <div class="bs-form-row">
                <label for="bs-p-syncInterval"><?php p($l->t('Sync every (hours)')); ?></label>
                <input type="number" id="bs-p-syncInterval" name="syncInterval"
                       value="24" min="1" max="168" />
            </div>
            <div class="bs-form-row">
                <label for="bs-p-address"><?php p($l->t('School Address (optional)')); ?></label>
                <input type="text" id="bs-p-address" name="address"
                       placeholder="<?php p($l->t('e.g. Musterstraße 1, 01234 Musterstadt')); ?>" />
            </div>
            <div class="bs-form-row">
                <button type="submit" class="button primary"><?php p($l->t('Save')); ?></button>
            </div>
            <div id="bs-p-error" class="bs-error" style="display:none"></div>
        </form>
    </div>

    <!-- Existing accounts -->
    <div class="bs-card">
        <h3><?php p($l->t('Connected Accounts')); ?></h3>
        <div id="bs-p-accounts-loading" class="icon-loading"></div>
        <div id="bs-p-accounts-list"></div>
    </div>
</div>
