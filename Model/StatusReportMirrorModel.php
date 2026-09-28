<?php

namespace Kanboard\Plugin\StatusReport\Model;

use Kanboard\Core\Base;

/**
 * Mirror of the current status inside the task description.
 *
 * Enabled by default. Only the region delimited by the internal Markdown
 * reference markers is rewritten; anything manually entered by the user
 * outside this region remains untouched.
 */
class StatusReportMirrorModel extends Base
{
    const CONFIG_KEY = 'statusreport_mirror_description';

    /*
     * Markdown reference definitions are intentionally used as internal
     * delimiters because Parsedown does not render them in the card view.
     */
    const MARKER_START = '[status-report-start]: #';
    const MARKER_END   = '[status-report-end]: #';

    /*
     * Legacy delimiters used by the first implementation. They are recognized
     * only for migration: the next synchronization replaces them automatically
     * with the invisible Markdown reference markers above.
     */
    const LEGACY_MARKER_START = '<!-- STATUS_REPORT_START -->';
    const LEGACY_MARKER_END   = '<!-- STATUS_REPORT_END -->';

    /**
     * Recursion guard (the task is also updated without events).
     *
     * @var boolean
     */
    private static $running = false;

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return (bool) $this->configModel->get(self::CONFIG_KEY, 1);
    }

    /**
     * Rebuild the mirrored region of a task description.
     *
     * @param  integer $task_id
     * @param  integer $excluded_comment_id Comment being deleted, if any
     * @return boolean
     */
    public function sync($task_id, $excluded_comment_id = 0)
    {
        if (! $this->isEnabled() || self::$running) {
            return false;
        }

        $task = $this->taskFinderModel->getById($task_id);

        if (empty($task)) {
            return false;
        }

        $report = $this->statusReportModel->getCurrentByTask($task_id, $excluded_comment_id);
        $description = $this->replaceRegion($task['description'], $this->buildBlock($report));

        if ($description === $task['description']) {
            return true;
        }

        self::$running = true;

        try {
            $result = $this->taskModificationModel->update(array(
                'id'          => $task_id,
                'description' => $description,
            ), false);
        } catch (\Exception $e) {
            self::$running = false;
            throw $e;
        }

        self::$running = false;

        return $result;
    }

    /**
     * @param array $comment
     */
    public function onCommentChanged(array $comment)
    {
        if (! empty($comment['id']) && $this->statusReportModel->isStatusReport($comment['id'])) {
            $this->sync($comment['task_id']);
        }
    }

    /**
     * Called before the comment row is removed.
     *
     * @param array $comment
     */
    public function onCommentRemoved(array $comment)
    {
        if (! empty($comment['id']) && $this->statusReportModel->isStatusReport($comment['id'])) {
            $this->sync($comment['task_id'], $comment['id']);
        }
    }

    /**
     * Build the description block.
     *
     * The title uses the normal card font, only in bold. Internal delimiters
     * are Markdown reference definitions and therefore do not appear in the
     * rendered card.
     *
     * @param  array $report
     * @return string
     */
    public function buildBlock(array $report)
    {
        if (empty($report)) {
            return '';
        }

        $format = $this->configModel->get('application_datetime_format', 'd/m/Y H:i');
        $author = $report['name'] ?: $report['username'];
        $date = isset($report['date_update']) ? $report['date_update'] : $report['date_creation'];

        return self::MARKER_START."\n\n".
            '**'.t('Current Status').'**'."\n\n".
            trim($report['comment'])."\n\n".
            t('Updated on').': '.date($format, $date)."  \n".
            t('Updated by').': '.$author."\n\n".
            self::MARKER_END;
    }

    /**
     * Replace only the delimited region, never anything else.
     *
     * Both the current invisible Markdown markers and the legacy HTML-comment
     * markers are recognized so older mirrored descriptions migrate on their
     * next synchronization without manual editing.
     *
     * @param  string $description
     * @param  string $block
     * @return string
     */
    public function replaceRegion($description, $block)
    {
        $description = (string) $description;

        $patterns = array(
            '/'.preg_quote(self::MARKER_START, '/').'.*?'.preg_quote(self::MARKER_END, '/').'/s',
            '/'.preg_quote(self::LEGACY_MARKER_START, '/').'.*?'.preg_quote(self::LEGACY_MARKER_END, '/').'/s',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description)) {
                if ($block === '') {
                    return rtrim(preg_replace($pattern, '', $description));
                }

                return preg_replace_callback($pattern, function () use ($block) {
                    return $block;
                }, $description);
            }
        }

        if ($block === '') {
            return $description;
        }

        return $description === '' ? $block : rtrim($description)."\n\n".$block;
    }
}
