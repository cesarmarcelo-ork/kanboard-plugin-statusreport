<?php

namespace Kanboard\Plugin\StatusReport;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;
use Kanboard\Model\CommentModel;
use Kanboard\Plugin\StatusReport\Core\OverrideGuard;

/**
 * Status Report plugin.
 *
 * Lets a comment be explicitly classified as a Status Report, which then
 * becomes the official current status of the task. The comment remains the
 * single source of truth: the plugin only stores the classification.
 */
class Plugin extends Base
{
    /**
     * Core templates overridden by this plugin.
     *
     * Keep this list minimal: every entry is a potential collision with
     * another plugin (see Core\OverrideGuard).
     */
    public static $overriddenTemplates = array('comment/show');

    public function initialize()
    {
        OverrideGuard::reset(__DIR__);

        $this->registerAcl();
        $this->registerOverrides();
        $this->registerTemplateHooks();
        $this->registerEventListeners();
    }

    /**
     * Executed on app.bootstrap, once every plugin has been initialized.
     */
    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');

        foreach (self::$overriddenTemplates as $template) {
            if (! OverrideGuard::hasConflict($template)) {
                $effective = $this->template->getTemplateFile($template);

                if (! OverrideGuard::isOwnedByPlugin($effective)) {
                    OverrideGuard::addConflict($template, $effective);
                }
            }
        }

        foreach (OverrideGuard::getConflicts() as $conflict) {
            $this->logger->error(sprintf(
                'StatusReport: restricted mode. Template "%s" is also overridden by plugin "%s" (%s), '.
                'so the comment history integration was not activated. '.
                'Current status panel, status report creation and promotion remain available.',
                $conflict['template'],
                $conflict['plugin'],
                $conflict['file']
            ));
        }
    }

    /**
     * The default role of an unknown controller is PROJECT_VIEWER,
     * so the controller must be registered explicitly.
     */
    protected function registerAcl()
    {
        $this->projectAccessMap->add('StatusReportController', '*', Role::PROJECT_MEMBER);
    }

    /**
     * Only override a template when nobody else did it before us.
     *
     * Registration is all-or-nothing for the template itself: either the
     * override is installed, or it is not installed at all and the plugin runs
     * in restricted mode -- every other feature stays active.
     */
    protected function registerOverrides()
    {
        foreach (self::$overriddenTemplates as $template) {
            $currentFile = $this->template->getTemplateFile($template);

            if (OverrideGuard::isCoreTemplate($currentFile)) {
                $this->template->setTemplateOverride($template, 'StatusReport:'.$template);
            } else {
                OverrideGuard::addConflict($template, $currentFile);
            }
        }
    }

    protected function registerTemplateHooks()
    {
        $this->template->hook->attachCallable(
            'template:task:show:before-description',
            'StatusReport:task/panel',
            function ($task, $project) {
                return array(
                    'status_report' => $this->statusReportModel->getCurrentByTask($task['id']),
                );
            }
        );

        $this->template->hook->attach('template:task:dropdown:after-add-comment', 'StatusReport:task/menu');
        $this->template->hook->attach('template:task:sidebar:after-add-comment', 'StatusReport:task/menu');
        $this->template->hook->attach('template:config:application', 'StatusReport:config/settings');
        $this->template->hook->attach('template:layout:top', 'StatusReport:layout/warning');

        $this->hook->on(
            'template:layout:css',
            array('template' => 'plugins/StatusReport/Asset/css/status-report.css')
        );
    }

    /**
     * Events are used only to keep the optional description mirror in sync.
     * The plugin's own consistency never depends on them.
     */
    protected function registerEventListeners()
    {
        // Base::on() only forwards the container, not the event payload,
        // so the dispatcher is used directly here.
        $container = $this->container;

        $this->dispatcher->addListener(CommentModel::EVENT_UPDATE, function ($event) use ($container) {
            $container['statusReportMirrorModel']->onCommentChanged($event['comment']);
        });

        $this->dispatcher->addListener(CommentModel::EVENT_DELETE, function ($event) use ($container) {
            $container['statusReportMirrorModel']->onCommentRemoved($event['comment']);
        });
    }

    public function getClasses()
    {
        return array(
            'Plugin\StatusReport\Model' => array(
                'StatusReportModel',
                'StatusReportMirrorModel',
            ),
        );
    }

    public function getHelpers()
    {
        return array(
            'Plugin\StatusReport\Helper' => array('StatusReportHelper'),
        );
    }

    public function getPluginName()
    {
        return 'StatusReport';
    }

    public function getPluginDescription()
    {
        return t('Classify a comment as a status report so it becomes the official current status of the task');
    }

    public function getPluginAuthor()
    {
        return 'OrkFlowTech';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/cesarmarcelo-ork/kanboard-plugin-statusreport';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.50';
    }
}
