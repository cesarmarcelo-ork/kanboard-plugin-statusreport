<?php

namespace Kanboard\Plugin\StatusReport\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\StatusReport\Core\OverrideGuard;

/**
 * Template helper.
 *
 * Lookups are cached per request and per task: rendering a task with fifty
 * comments triggers a single query.
 */
class StatusReportHelper extends Base
{
    private $commentIds = array();
    private $currentIds = array();

    /**
     * @param  integer $task_id
     * @return array
     */
    public function getCommentIds($task_id)
    {
        if (! isset($this->commentIds[$task_id])) {
            $ids = $this->statusReportModel->getCommentIds($task_id);
            $this->commentIds[$task_id] = array_map('intval', $ids);
            $this->currentIds[$task_id] = empty($ids) ? 0 : (int) $ids[0];
        }

        return $this->commentIds[$task_id];
    }

    /**
     * @param  integer $task_id
     * @param  integer $comment_id
     * @return boolean
     */
    public function isStatusReport($task_id, $comment_id)
    {
        return in_array((int) $comment_id, $this->getCommentIds($task_id), true);
    }

    /**
     * @param  integer $task_id
     * @param  integer $comment_id
     * @return boolean
     */
    public function isCurrent($task_id, $comment_id)
    {
        $this->getCommentIds($task_id);

        return $this->currentIds[$task_id] === (int) $comment_id;
    }

    /**
     * @param  integer $task_id
     * @return array
     */
    public function getCurrent($task_id)
    {
        return $this->statusReportModel->getCurrentByTask($task_id);
    }

    /**
     * True when the comment/show override could not be installed because
     * another plugin already overrides that template.
     *
     * @param  string $template
     * @return boolean
     */
    public function hasOverrideConflict($template)
    {
        return OverrideGuard::hasConflict($template);
    }

    /**
     * @return array
     */
    public function getOverrideConflicts()
    {
        return OverrideGuard::getConflicts();
    }

    /**
     * @param  integer $timestamp
     * @return string
     */
    public function datetime($timestamp)
    {
        return date($this->configModel->get('application_datetime_format', 'd/m/Y H:i'), $timestamp);
    }
}
