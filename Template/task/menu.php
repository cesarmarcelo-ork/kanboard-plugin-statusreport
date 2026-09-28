<?php if ($this->user->hasProjectAccess('StatusReportController', 'create', $task['project_id'])): ?>
    <li>
        <?= $this->modal->medium('flag', t('Add a status report'), 'StatusReportController', 'create', array('plugin' => 'StatusReport', 'task_id' => $task['id'])) ?>
    </li>
<?php endif ?>
