<?php
declare(strict_types=1);
/** @var \OCP\IL10N $l */
/** @var array $_ */

\OCP\Util::addScript('beste_schule', 'grades');
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

<div id="app-navigation">
    <ul>
        <li id="bs-nav-grades" class="active">
            <a href="#grades"><?php p($l->t('Noten')); ?></a>
        </li>
        <li id="bs-nav-finalgrades">
            <a href="#finalgrades"><?php p($l->t('Endnoten')); ?></a>
        </li>
    </ul>
</div>

<div id="app-content">
    <!-- Header -->
    <div id="app-content-wrapper">
        <div id="bs-toolbar">
            <div id="bs-account-switcher">
                <label for="bs-account-select"><?php p($l->t('Account:')); ?></label>
                <select id="bs-account-select"></select>
            </div>
            <button id="bs-sync-btn" class="button">
                <span class="icon-history"></span>
                <?php p($l->t('Sync now')); ?>
            </button>
            <span id="bs-last-sync"></span>
        </div>

        <!-- Grades section -->
        <section id="bs-section-grades" class="bs-section">
            <div class="bs-section-header">
                <h2><?php p($l->t('Noten')); ?></h2>
                <span id="bs-grade-average" class="bs-average"></span>
            </div>

            <div id="bs-grades-loading" class="icon-loading" style="display:none"></div>

            <table id="bs-grades-table" class="bs-table">
                <thead>
                    <tr>
                        <th><?php p($l->t('Datum')); ?></th>
                        <th><?php p($l->t('Fach')); ?></th>
                        <th><?php p($l->t('Note')); ?></th>
                        <th><?php p($l->t('Art')); ?></th>
                        <th><?php p($l->t('Lehrer')); ?></th>
                        <th><?php p($l->t('Gewichtung')); ?></th>
                    </tr>
                </thead>
                <tbody id="bs-grades-tbody"></tbody>
            </table>
            <p id="bs-no-grades" style="display:none">
                <?php p($l->t('Keine Noten gefunden.')); ?>
            </p>
        </section>

        <!-- Final grades section -->
        <section id="bs-section-finalgrades" class="bs-section" style="display:none">
            <h2><?php p($l->t('Endnoten')); ?></h2>
            <div id="bs-finalgrades-loading" class="icon-loading" style="display:none"></div>
            <div id="bs-finalgrades-container"></div>
            <p id="bs-no-finalgrades" style="display:none">
                <?php p($l->t('Keine Endnoten gefunden.')); ?>
            </p>
        </section>

        <div id="bs-error-banner" class="bs-error" style="display:none"></div>
    </div>
</div>
