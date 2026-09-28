<?php $srConflicts = $this->StatusReportHelper->getOverrideConflicts(); ?>
<?php if (! empty($srConflicts) && $this->user->isAdmin()): ?>
    <div class="alert alert-error status-report-conflict">
        <p><strong><?= t('Status Report is running in restricted mode') ?></strong></p>
        <ul>
            <?php foreach ($srConflicts as $srConflict): ?>
                <li>
                    <?= t('Conflicting plugin: %s', $srConflict['plugin']) ?><br>
                    <?= t('Disputed template: %s', $srConflict['template']) ?><br>
                    <small><?= $this->text->e($srConflict['file']) ?></small>
                </li>
            <?php endforeach ?>
        </ul>
        <p><?= t('Reason: two plugins cannot replace the same template, so the override was not installed.') ?></p>
        <p><?= t('Unavailable: the visual integration with the comment history (status report badge and the "Set as current status" link on each comment).') ?></p>
        <p><?= t('Still available: the "Current Status" panel, adding status reports and promoting an existing comment from that panel.') ?></p>
    </div>
<?php endif ?>
