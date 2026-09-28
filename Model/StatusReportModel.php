<?php

namespace Kanboard\Plugin\StatusReport\Model;

use Kanboard\Core\Base;
use Kanboard\Model\CommentModel;
use Kanboard\Model\UserModel;

/**
 * Status reports.
 *
 * This table only classifies comments: it never stores the status text.
 * The current status of a task is therefore derived, not persisted:
 *
 *     current status = most recent surviving row for the task
 *                      (ordered by status_reports.id DESC)
 *                      + current text of the related comment
 *
 * Consequences: editing the comment updates the status for free, deleting it
 * makes the previous report current again (ON DELETE CASCADE), and two
 * concurrent reports cannot leave the task in an ambiguous state.
 */
class StatusReportModel extends Base
{
    const TABLE = 'status_reports';

    /**
     * Columns of a status report joined with its comment and its author.
     */
    private function getQuery()
    {
        return $this->db
            ->table(self::TABLE)
            ->columns(
                self::TABLE.'.id',
                self::TABLE.'.task_id',
                self::TABLE.'.comment_id',
                self::TABLE.'.user_id',
                self::TABLE.'.date_creation',
                CommentModel::TABLE.'.comment',
                CommentModel::TABLE.'.visibility',
                CommentModel::TABLE.'.date_modification AS comment_date_modification',
                UserModel::TABLE.'.username',
                UserModel::TABLE.'.name',
                UserModel::TABLE.'.email',
                UserModel::TABLE.'.avatar_path'
            )
            ->join(CommentModel::TABLE, 'id', 'comment_id', self::TABLE)
            ->join(UserModel::TABLE, 'id', 'user_id', self::TABLE);
    }

    /**
     * Current status of a task.
     *
     * Ordering is deterministic and defined by the plugin (id DESC), so it does
     * not depend on the timestamp resolution nor on any database behaviour.
     *
     * @param  integer $task_id
     * @param  integer $excluded_comment_id  Comment about to be deleted
     * @return array
     */
    public function getCurrentByTask($task_id, $excluded_comment_id = 0)
    {
        $query = $this->getQuery()
            ->eq(self::TABLE.'.task_id', $task_id)
            ->desc(self::TABLE.'.id');

        if ($excluded_comment_id > 0) {
            $query->neq(self::TABLE.'.comment_id', $excluded_comment_id);
        }

        $report = $query->findOne();

        return empty($report) ? array() : $this->decorate($report);
    }

    /**
     * Full history of status reports, most recent first.
     *
     * @param  integer $task_id
     * @return array
     */
    public function getAllByTask($task_id)
    {
        return array_map(array($this, 'decorate'), $this->getQuery()
            ->eq(self::TABLE.'.task_id', $task_id)
            ->desc(self::TABLE.'.id')
            ->findAll());
    }

    /**
     * Date shown as "updated on".
     *
     * The status text lives in the comment, so editing the comment updates the
     * status: the effective date is the most recent of the classification date
     * and the last modification of the comment. Using the maximum keeps both
     * cases correct: editing a current report moves the date forward, and
     * promoting an old comment keeps the date of the classification.
     *
     * @param  array $report
     * @return array
     */
    private function decorate(array $report)
    {
        $dates = array((int) $report['date_creation']);

        if (! empty($report['comment_date_modification'])) {
            $dates[] = (int) $report['comment_date_modification'];
        }

        $report['date_update'] = max($dates);

        return $report;
    }

    /**
     * Comment ids classified as status report for a task.
     *
     * @param  integer $task_id
     * @return array
     */
    public function getCommentIds($task_id)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('task_id', $task_id)
            ->desc('id')
            ->findAllByColumn('comment_id');
    }

    /**
     * @param  integer $comment_id
     * @return array
     */
    public function getByCommentId($comment_id)
    {
        $report = $this->db
            ->table(self::TABLE)
            ->eq('comment_id', $comment_id)
            ->findOne();

        return empty($report) ? array() : $report;
    }

    /**
     * @param  integer $comment_id
     * @return boolean
     */
    public function isStatusReport($comment_id)
    {
        return $this->db->table(self::TABLE)->eq('comment_id', $comment_id)->exists();
    }

    /**
     * True when this comment is the current status of its task.
     *
     * @param  integer $task_id
     * @param  integer $comment_id
     * @return boolean
     */
    public function isCurrent($task_id, $comment_id)
    {
        $current = $this->getCurrentByTask($task_id);

        return ! empty($current) && (int) $current['comment_id'] === (int) $comment_id;
    }

    /**
     * Classify an existing comment as a status report.
     *
     * Idempotent: marking the same comment twice does not create a duplicate
     * and does not change the ordering.
     *
     * @param  integer $task_id
     * @param  integer $comment_id
     * @param  integer $user_id
     * @return boolean|integer
     */
    public function create($task_id, $comment_id, $user_id)
    {
        $existing = $this->getByCommentId($comment_id);

        if (! empty($existing)) {
            return (int) $existing['id'];
        }

        return $this->db->table(self::TABLE)->persist(array(
            'task_id'       => $task_id,
            'comment_id'    => $comment_id,
            'user_id'       => $user_id,
            'date_creation' => time(),
        ));
    }

    /**
     * Promote a comment to the current status.
     *
     * A comment that is already a historical status report is reclassified
     * inside a transaction so that it receives the newest deterministic id and
     * becomes current again. The comment itself is never changed or duplicated.
     * If it is already current, the operation is idempotent.
     *
     * @param  integer $task_id
     * @param  integer $comment_id
     * @param  integer $user_id
     * @return boolean|integer
     */
    public function promote($task_id, $comment_id, $user_id)
    {
        $existing = $this->getByCommentId($comment_id);

        if (! empty($existing) && $this->isCurrent($task_id, $comment_id)) {
            return (int) $existing['id'];
        }

        $this->db->startTransaction();

        try {
            if (! empty($existing)) {
                if (! $this->db
                    ->table(self::TABLE)
                    ->eq('comment_id', $comment_id)
                    ->remove()) {
                    $this->db->cancelTransaction();
                    return false;
                }
            }

            $reportId = $this->db->table(self::TABLE)->persist(array(
                'task_id'       => $task_id,
                'comment_id'    => $comment_id,
                'user_id'       => $user_id,
                'date_creation' => time(),
            ));

            if ($reportId === false) {
                $this->db->cancelTransaction();
                return false;
            }

            $this->db->closeTransaction();

            return $reportId;
        } catch (\Exception $e) {
            $this->db->cancelTransaction();
            $this->logger->error('StatusReport promote: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @param  integer $task_id
     * @return integer
     */
    public function countByTask($task_id)
    {
        return $this->db->table(self::TABLE)->eq('task_id', $task_id)->count();
    }
}
