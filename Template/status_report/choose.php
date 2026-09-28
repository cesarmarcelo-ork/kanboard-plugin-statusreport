<?php

use Kanboard\Core\Security\Role;

$srRole = $this->user->getRole();

?>
<div class="page-header">
    <h2><?= t('Set an existing comment as current status') ?></h2>
</div>

<?php if (empty($comments)): ?>
    <p class="alert"><?= t('There is no comment on this task.') ?></p>
<?php else: ?>
    <table class="table-striped table-scrolling status-report-choose">
        <tr>
            <th class="column-20"><?= t('Date') ?></th>
            <th><?= t('Comment') ?></th>
            <th class="column-20"><?= t('Action') ?></th>
        </tr>
        <?php foreach ($comments as $srComment): ?>
            <?php
            if ($srRole === Role::APP_USER && $srComment['visibility'] !== Role::APP_USER) {
                continue;
            }

            if ($srRole === Role::APP_MANAGER && $srComment['visibility'] === Role::APP_ADMIN) {
                continue;
            }

            $srIsReport = in_array((int) $srComment['id'], $report_ids, true);
            $srIsCurrent = ! empty($report_ids) && (int) $report_ids[0] === (int) $srComment['id'];
            ?>
            <tr>
                <td>
                    <?= $this->dt->datetime($srComment['date_creation']) ?><br>
                    <small><?= $this->text->e($srComment['name'] ?: $srComment['username']) ?></small>
                </td>
                <td>
                    <?= $this->text->e(mb_substr(trim(preg_replace('/\s+/', ' ', $srComment['comment'])), 0, 180)) ?>
                    <?php if ($srIsReport): ?>
                        <span class="status-report-badge"><?= t('Status Report') ?></span>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($srIsCurrent): ?>
                        <em><?= t('Current Status') ?></em>
                    <?php else: ?>
                        <?= $this->url->icon('check-square-o', t('Set as current status'), 'StatusReportController', 'promote', array(
                            'plugin'     => 'StatusReport',
                            'task_id'    => $task['id'],
                            'comment_id' => $srComment['id'],
                            'csrf_token' => $this->app->getToken()->getReusableCSRFToken(),
                        )) ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    </table>
<?php endif ?>
