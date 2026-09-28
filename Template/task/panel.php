<?php

use Kanboard\Core\Security\Role;

$srRole = $this->user->getRole();
$srVisible = ! empty($status_report);

if ($srVisible && $srRole === Role::APP_USER && $status_report['visibility'] !== Role::APP_USER) {
    $srVisible = false;
}

if ($srVisible && $srRole === Role::APP_MANAGER && $status_report['visibility'] === Role::APP_ADMIN) {
    $srVisible = false;
}

$srCanWrite = $this->user->hasProjectAccess('StatusReportController', 'create', $task['project_id']);

?>
<div class="status-report-panel">
    <h3 class="status-report-panel-title">
        <i class="fa fa-flag fa-fw" aria-hidden="true"></i> <?= t('Current Status') ?>
    </h3>

    <?php if (! $srVisible): ?>
        <p class="status-report-empty"><?= t('No status report recorded.') ?></p>
    <?php else: ?>
        <div class="markdown status-report-content">
            <?= $this->text->markdown($status_report['comment']) ?>
        </div>
        <p class="status-report-meta">
            <?= t('Updated on') ?>: <?= $this->dt->datetime($status_report['date_update']) ?>
            &mdash;
            <?= t('Updated by') ?>: <?= $this->text->e($status_report['name'] ?: $status_report['username']) ?>
            &mdash;
            <?= $this->url->link(t('View the original comment'), 'TaskViewController', 'show', array('task_id' => $task['id']), false, '', '', false, 'comment-'.$status_report['comment_id']) ?>
        </p>
    <?php endif ?>

    <?php if ($srCanWrite): ?>
        <p class="status-report-actions">
            <?= $this->modal->medium('flag', t('Add a status report'), 'StatusReportController', 'create', array('plugin' => 'StatusReport', 'task_id' => $task['id'])) ?>
            <?= $this->modal->medium('check-square-o', t('Set an existing comment as current status'), 'StatusReportController', 'choose', array('plugin' => 'StatusReport', 'task_id' => $task['id'])) ?>
        </p>
    <?php endif ?>
</div>
