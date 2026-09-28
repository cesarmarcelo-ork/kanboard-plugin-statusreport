<?php
/**
 * Wrapper override of the core template "comment/show".
 *
 * It does not copy the core markup: the core template is rendered as-is and the
 * plugin only prepends a discreet badge. This keeps the plugin compatible with
 * future changes of the core comment layout.
 */

$__srVars = get_defined_vars();
unset($__srVars['__template_name'], $__srVars['__template_args'], $__srVars['__srVars']);

$__srOutput = $this->render('kanboard:comment/show', $__srVars);

// The core template returns nothing when the comment is not visible for the
// current role: in that case the badge must not be rendered either.
if (trim($__srOutput) === '') {
    return;
}

$__srIsReport = isset($task) && $this->StatusReportHelper->isStatusReport($task['id'], $comment['id']);
$__srIsCurrent = $__srIsReport && $this->StatusReportHelper->isCurrent($task['id'], $comment['id']);
$__srShowActions = isset($task)
    && ! isset($hide_actions)
    && ! isset($preview)
    && empty($is_public)
    && $this->user->hasProjectAccess('StatusReportController', 'promote', $task['project_id']);

?>
<?php if ($__srIsReport || $__srShowActions): ?>
    <div class="status-report-flag">
        <?php if ($__srIsCurrent): ?>
            <span class="status-report-badge status-report-badge-current">
                <i class="fa fa-flag" aria-hidden="true"></i> <?= t('Current Status') ?>
            </span>
        <?php elseif ($__srIsReport): ?>
            <span class="status-report-badge">
                <i class="fa fa-flag-o" aria-hidden="true"></i> <?= t('Status Report') ?>
            </span>
        <?php endif ?>

        <?php if ($__srShowActions && ! $__srIsCurrent): ?>
            <span class="status-report-flag-action">
                <?= $this->url->icon('check-square-o', t('Set as current status'), 'StatusReportController', 'promote', array(
                    'plugin'     => 'StatusReport',
                    'task_id'    => $task['id'],
                    'comment_id' => $comment['id'],
                    'csrf_token' => $this->app->getToken()->getReusableCSRFToken(),
                )) ?>
            </span>
        <?php endif ?>
    </div>
<?php endif ?>
<?= $__srOutput ?>
