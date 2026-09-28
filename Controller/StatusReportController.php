<?php

namespace Kanboard\Plugin\StatusReport\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Core\Security\Role;
use Kanboard\Model\CommentModel;

/**
 * Status report controller.
 *
 * Access is restricted to PROJECT_MEMBER by the project access map declared in
 * Plugin::registerAcl(). The comment and its classification are written in the
 * same logical flow (and in the same transaction when the driver supports it),
 * so the plugin never depends on the asynchronous comment.create event to stay
 * consistent.
 */
class StatusReportController extends BaseController
{
    /**
     * Form to add a comment classified as status report.
     */
    public function create(array $values = array(), array $errors = array())
    {
        $task = $this->getTask();

        $this->response->html($this->template->render('StatusReport:status_report/create', array(
            'values' => $values + array('task_id' => $task['id']),
            'errors' => $errors,
            'task'   => $task,
        )));
    }

    /**
     * Create the comment and classify it as status report.
     */
    public function save()
    {
        $task = $this->getTask();
        $form = $this->request->getValues();

        if (empty($form)) {
            throw new AccessForbiddenException();
        }

        // Whitelist: no unexpected form field can ever reach the comments table.
        $values = array(
            'task_id'    => $task['id'],
            'user_id'    => $this->userSession->getId(),
            'comment'    => isset($form['comment']) ? $form['comment'] : '',
            'visibility' => $this->filterVisibility(isset($form['visibility']) ? $form['visibility'] : Role::APP_USER),
        );

        list($valid, $errors) = $this->commentValidator->validateCreation($values);

        if (! $valid) {
            $this->create($values, $errors);
            return;
        }

        if ($this->createStatusReport($values)) {
            $this->flash->success(t('Status report added successfully.'));
        } else {
            $this->flash->failure(t('Unable to add this status report.'));
        }

        $this->response->redirect($this->helper->url->to(
            'TaskViewController',
            'show',
            array('task_id' => $task['id']),
            'comments'
        ), true);
    }

    /**
     * Modal listing the comments of the task, to promote one of them.
     */
    public function choose()
    {
        $task = $this->getTask();
        $comments = $this->commentModel->getAll($task['id'], 'DESC');
        $reportIds = array_map('intval', $this->statusReportModel->getCommentIds($task['id']));

        $this->response->html($this->template->render('StatusReport:status_report/choose', array(
            'task'       => $task,
            'comments'   => $comments,
            'report_ids' => $reportIds,
        )));
    }

    /**
     * Classify an existing comment as the current status.
     */
    public function promote()
    {
        $this->checkReusableGETCSRFParam();

        $task = $this->getTask();
        $comment = $this->getComment($task);

        if ($this->statusReportModel->promote($task['id'], $comment['id'], $this->userSession->getId()) !== false) {
            $this->statusReportMirrorModel->sync($task['id']);
            $this->flash->success(t('Current status updated successfully.'));
        } else {
            $this->flash->failure(t('Unable to update the current status.'));
        }

        $this->response->redirect($this->helper->url->to(
            'TaskViewController',
            'show',
            array('task_id' => $task['id'])
        ), true);
    }

    /**
     * Create comment + classification atomically.
     *
     * @param  array $values
     * @return boolean
     */
    protected function createStatusReport(array $values)
    {
        $this->db->startTransaction();

        try {
            $commentId = $this->commentModel->create($values);

            if ($commentId === false) {
                $this->db->cancelTransaction();
                return false;
            }

            if ($this->statusReportModel->create($values['task_id'], $commentId, $values['user_id']) === false) {
                $this->db->cancelTransaction();
                return false;
            }

            $this->db->closeTransaction();
        } catch (\Exception $e) {
            $this->db->cancelTransaction();
            $this->logger->error('StatusReport: '.$e->getMessage());
            return false;
        }

        $this->statusReportMirrorModel->sync($values['task_id']);

        return true;
    }

    /**
     * A user must not be able to widen the visibility of a comment through a
     * forged parameter.
     *
     * @param  string $visibility
     * @return string
     */
    protected function filterVisibility($visibility)
    {
        $allowed = array(Role::APP_USER);
        $role = $this->userSession->getRole();

        if ($role === Role::APP_MANAGER || $role === Role::APP_ADMIN) {
            $allowed[] = Role::APP_MANAGER;
        }

        if ($role === Role::APP_ADMIN) {
            $allowed[] = Role::APP_ADMIN;
        }

        return in_array($visibility, $allowed, true) ? $visibility : Role::APP_USER;
    }

    /**
     * @param  array $task
     * @return array
     * @throws PageNotFoundException
     */
    protected function getComment(array $task)
    {
        $comment = $this->commentModel->getById($this->request->getIntegerParam('comment_id'));

        if (empty($comment) || (int) $comment['task_id'] !== (int) $task['id']) {
            throw new PageNotFoundException();
        }

        return $comment;
    }
}
