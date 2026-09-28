<?php use Kanboard\Core\Security\Role; ?>
<div class="page-header">
    <h2><?= t('Add a status report') ?></h2>
</div>

<p class="alert alert-info">
    <?= t('This entry will be saved as a comment and will become the current status of the task.') ?>
    <?= t('A regular comment (Comment / Follow-up) does not change the current status.') ?>
</p>

<form method="post" action="<?= $this->url->href('StatusReportController', 'save', array('plugin' => 'StatusReport', 'task_id' => $task['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <?= $this->form->textEditor('comment', $values, $errors, array('autofocus' => true, 'required' => true, 'aria-label' => t('Status Report'))) ?>

    <?php
    $srAttributes = array('hidden');
    $srVisibility = array(Role::APP_USER => t('Standard users'));

    if ($this->user->getRole() !== Role::APP_USER) {
        echo $this->form->label(t('Visibility:'), 'visibility');
        $srAttributes = array();
        $srVisibility[Role::APP_MANAGER] = t('Application managers or more');
    }

    if ($this->user->getRole() === Role::APP_ADMIN) {
        $srVisibility[Role::APP_ADMIN] = t('Administrators');
    }
    ?>

    <?= $this->form->select('visibility', $srVisibility, $values, array(), $srAttributes) ?>

    <?= $this->modal->submitButtons() ?>
</form>
