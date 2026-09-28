<?php

/**
 * Functional test runner for the StatusReport plugin.
 *
 * Usage, from the Kanboard root directory:
 *
 *     php plugins/StatusReport/Test/run.php
 *
 * It runs against the configured database of the installation: it creates one
 * temporary project, removes it at the end and restores every setting it
 * changed. Existing projects, tasks, comments, users and plugins are never
 * touched, and no schema operation is performed.
 */

use Symfony\Contracts\EventDispatcher\Event;
use Kanboard\Core\Plugin\Version;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\StatusReport\Core\OverrideGuard;
use Kanboard\Plugin\StatusReport\Model\StatusReportMirrorModel;

if (php_sapi_name() !== 'cli') {
    die('This script runs only from the command line');
}

$kanboardRoot = realpath(__DIR__.'/../../..');
$bootstrap = $kanboardRoot !== false ? $kanboardRoot.'/app/common.php' : '';

if ($bootstrap === '' || ! is_file($bootstrap)) {
    fwrite(STDERR, "StatusReport: app/common.php não foi encontrado neste ambiente.\n");
    fwrite(STDERR, "Execute o runner dentro do runtime real do Kanboard (por exemplo, dentro do container Docker), a partir da raiz da aplicação.\n");
    exit(2);
}

require $bootstrap;

$container['dispatcher']->dispatch(new Event, 'app.bootstrap');

$failures = 0;
$checks = 0;

function check($label, $condition)
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? '  [OK] ' : '  [FALHOU] ').$label."\n";

    if (! $condition) {
        $failures++;
    }
}

/**
 * Effective foreign key support of the connection actually in use.
 */
function foreignKeyStatus(PDO $pdo, $driver)
{
    try {
        switch ($driver) {
            case 'sqlite':
                return array((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'PRAGMA foreign_keys');
            case 'mysql':
                return array((int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn() === 1, '@@foreign_key_checks (InnoDB)');
            case 'postgres':
                return array(true, 'nativo');
        }
    } catch (Exception $e) {
        return array(false, 'indeterminado: '.$e->getMessage());
    }

    return array(false, 'driver desconhecido');
}

$driver = defined('DB_DRIVER') ? DB_DRIVER : 'desconhecido';
$driverLabel = array('sqlite' => 'SQLite', 'mysql' => 'MySQL/MariaDB', 'postgres' => 'PostgreSQL');
$pluginVersion = (new Kanboard\Plugin\StatusReport\Plugin($container))->getPluginVersion();
list($foreignKeys, $foreignKeysSource) = foreignKeyStatus($container['db']->getConnection(), $driver);

echo "\nStatusReport — Validação do Ambiente\n\n";
echo '  Kanboard:      '.APP_VERSION."\n";
echo '  StatusReport:  '.$pluginVersion."\n";
echo '  PHP:           '.PHP_VERSION."\n";
echo '  Driver:        '.(isset($driverLabel[$driver]) ? $driverLabel[$driver] : $driver)."\n";
echo '  Foreign Keys:  '.($foreignKeys ? 'ENABLED' : 'DISABLED').' ('.$foreignKeysSource.")\n";

echo "\nPré-requisitos\n";

if (! Version::isCompatible('>=1.2.50', APP_VERSION)) {
    echo '  [FALHOU] Kanboard '.APP_VERSION." não é suportado.\n";
    echo "           Versão mínima: 1.2.50.\n";
    echo "\nTestes interrompidos: nenhum recurso temporário foi criado.\n";
    exit(1);
}

check('Kanboard '.APP_VERSION.' atende a versão mínima 1.2.50', true);

if (! $foreignKeys) {
    echo "  [FALHOU] Foreign Keys desabilitadas neste ambiente.\n";
    echo "           A restauração automática do Informe de Situação anterior depende de ON DELETE CASCADE.\n";
    echo "\nTestes interrompidos: nenhum recurso temporário foi criado.\n";
    exit(1);
}

check('Foreign Keys habilitadas (ON DELETE CASCADE confiável)', true);

$projectModel = $container['projectModel'];
$commentModel = $container['commentModel'];
$reportModel = $container['statusReportModel'];
$mirrorModel = $container['statusReportMirrorModel'];
$taskFinderModel = $container['taskFinderModel'];
$configModel = $container['configModel'];

$projectId = 0;
$originalMirror = null;
$mirrorSettingExisted = false;

try {
    $mirrorSettingExisted = $configModel->exists(StatusReportMirrorModel::CONFIG_KEY);
    $originalMirror = $configModel->get(StatusReportMirrorModel::CONFIG_KEY, 1);

    $userId = $container['userModel']->getAll()[0]['id'];
    $projectId = $projectModel->create(array('name' => 'StatusReport Test '.date('Y-m-d H:i:s')));
    $description = "Contexto da demanda escrito manualmente.\n\nSegunda linha importante.";
    $taskId = $container['taskCreationModel']->create(array(
        'project_id'  => $projectId,
        'title'       => 'Tarefa de teste',
        'description' => $description,
    ));

    $comment = function ($text) use ($commentModel, $taskId, $userId) {
        return $commentModel->create(array(
            'task_id'    => $taskId,
            'user_id'    => $userId,
            'comment'    => $text,
            'visibility' => Role::APP_USER,
        ));
    };

    $report = function ($text) use ($comment, $reportModel, $taskId, $userId) {
        $id = $comment($text);
        $reportModel->create($taskId, $id, $userId);
        return $id;
    };

    $currentText = function () use ($reportModel, $taskId) {
        $current = $reportModel->getCurrentByTask($taskId);
        return empty($current) ? null : $current['comment'];
    };

    echo "\nCenário 1 — Comentário comum\n";
    $c1 = $comment('Encaminhado e-mail ao responsável.');
    check('comentário registrado', ! empty($c1));
    check('situação atual permanece inexistente', $currentText() === null);

    echo "\nCenário 2 — Primeiro Informe de Situação\n";
    $a = $report('Processo encaminhado à área jurídica e aguardando análise.');
    check('situação atual criada', $currentText() === 'Processo encaminhado à área jurídica e aguardando análise.');

    echo "\nCenário 3 — Comentário após Informe\n";
    $b = $comment('Responsável informou que analisará até sexta-feira.');
    check('situação atual permanece a anterior', $currentText() === 'Processo encaminhado à área jurídica e aguardando análise.');
    check('comentário comum não é informe', $reportModel->isStatusReport($b) === false);

    echo "\nCenário 4 — Nova Situação\n";
    $d = $report('Análise jurídica iniciada, com previsão de conclusão até 14/08/2026.');
    check('nova situação substitui a anterior', $currentText() === 'Análise jurídica iniciada, com previsão de conclusão até 14/08/2026.');
    check('ambos os informes permanecem no histórico', count($reportModel->getAllByTask($taskId)) === 2);
    check('todos os comentários permanecem no histórico', count($commentModel->getAll($taskId)) === 4);

    echo "\nCenário 5 — Edição do informe vigente\n";
    // A classificação é envelhecida artificialmente para tornar a verificação de
    // data determinística sem depender da passagem real de tempo.
    $container['db']->table('status_reports')->eq('comment_id', $d)->update(array('date_creation' => time() - 3600));
    $originalDate = $reportModel->getCurrentByTask($taskId);
    $commentModel->update(array('id' => $d, 'comment' => 'Análise jurídica concluída em 13/08/2026.'));
    $updated = $reportModel->getCurrentByTask($taskId);
    check('situação atual reflete a edição', $updated['comment'] === 'Análise jurídica concluída em 13/08/2026.');
    check('data exibida acompanha a edição do comentário', $updated['date_update'] > $originalDate['date_creation']);
    check('data exibida é a da modificação do comentário', (int) $updated['date_update'] === (int) $updated['comment_date_modification']);

    echo "\nCenário 6 — Edição de informe histórico\n";
    $commentModel->update(array('id' => $a, 'comment' => 'Texto histórico corrigido.'));
    check('situação vigente não é modificada', $currentText() === 'Análise jurídica concluída em 13/08/2026.');

    echo "\nCenário 9 — Descrição preexistente + espelho no cartão\n";
    $configModel->save(array(StatusReportMirrorModel::CONFIG_KEY => 1));
    $container['memoryCache']->flush();
    $mirrorModel->sync($taskId);
    $task = $taskFinderModel->getById($taskId);
    check('descrição original preservada', strpos($task['description'], 'Segunda linha importante.') !== false);
    check('bloco espelhado presente', strpos($task['description'], 'Análise jurídica concluída em 13/08/2026.') !== false);
    check('marcadores únicos', substr_count($task['description'], StatusReportMirrorModel::MARKER_START) === 1);

    $commentModel->update(array('id' => $d, 'comment' => 'Texto atualizado por evento.'));
    $task = $taskFinderModel->getById($taskId);
    check('espelho sincronizado pelo evento comment.update', strpos($task['description'], 'Texto atualizado por evento.') !== false);
    check('sem duplicação de marcadores', substr_count($task['description'], StatusReportMirrorModel::MARKER_START) === 1);
    check('texto manual intacto', strpos($task['description'], 'Contexto da demanda escrito manualmente.') !== false);

    $visualBlock = $mirrorModel->buildBlock($reportModel->getCurrentByTask($taskId));
    check('título da situação usa negrito sem heading Markdown', strpos($visualBlock, '**'.t('Current Status').'**') !== false && strpos($visualBlock, '## ') === false);
    check('marcadores atuais não usam comentários HTML visíveis', strpos($visualBlock, '<!-- STATUS_REPORT_') === false);

    $legacyDescription = "Texto manual\n\n".StatusReportMirrorModel::LEGACY_MARKER_START."\nconteúdo legado\n".StatusReportMirrorModel::LEGACY_MARKER_END;
    $migratedDescription = $mirrorModel->replaceRegion($legacyDescription, $visualBlock);
    check('marcadores legados migram automaticamente', strpos($migratedDescription, StatusReportMirrorModel::LEGACY_MARKER_START) === false && strpos($migratedDescription, StatusReportMirrorModel::MARKER_START) !== false);
    check('migração de marcador preserva texto manual', strpos($migratedDescription, 'Texto manual') !== false);

    echo "\nCenário 7 — Exclusão do comentário que representa a situação\n";
    $commentModel->remove($d);
    check('informe anterior volta a vigorar', $currentText() === 'Texto histórico corrigido.');
    check('nenhuma referência quebrada (ON DELETE CASCADE)', $reportModel->getByCommentId($d) === array());
    check('histórico de informes reduzido para 1', count($reportModel->getAllByTask($taskId)) === 1);
    $task = $taskFinderModel->getById($taskId);
    check('espelho aponta para o informe restaurado', strpos($task['description'], 'Texto histórico corrigido.') !== false);

    echo "\nCenário 8 — Permissões\n";
    $authorization = $container['projectAuthorization'];
    check('PROJECT_VIEWER bloqueado', $authorization->isAllowed('StatusReportController', 'save', Role::PROJECT_VIEWER) === false);
    check('PROJECT_MEMBER autorizado', $authorization->isAllowed('StatusReportController', 'save', Role::PROJECT_MEMBER) === true);
    check('promoção também restrita', $authorization->isAllowed('StatusReportController', 'promote', Role::PROJECT_VIEWER) === false);

    echo "\nCenário 10 — Múltiplos ciclos\n";
    for ($i = 1; $i <= 5; $i++) {
        $comment('Desdobramento '.$i);
        $report('Informe '.$i);
    }
    check('somente um estado é situação atual', $currentText() === 'Informe 5');
    check('histórico de informes preservado', count($reportModel->getAllByTask($taskId)) === 6);
    check('histórico de comentários preservado', count($commentModel->getAll($taskId)) === 13);

    echo "\nCenário adicional — Repromoção de Informe histórico\n";
    $beforeRepromotionCount = count($reportModel->getAllByTask($taskId));
    $previousCurrent = $reportModel->getCurrentByTask($taskId);
    $historicalIdBefore = $reportModel->getByCommentId($a)['id'];
    $repromotedId = $reportModel->promote($taskId, $a, $userId);
    check('Informe histórico pode voltar a ser Situação Atual', $currentText() === 'Texto histórico corrigido.');
    check('repromoção não duplica a classificação', count($reportModel->getAllByTask($taskId)) === $beforeRepromotionCount);
    check('repromoção recebe nova ordenação determinística', $repromotedId !== false && (int) $repromotedId > (int) $historicalIdBefore);
    check('situação anteriormente vigente continua no histórico', $reportModel->isStatusReport($previousCurrent['comment_id']) === true);
    $reportModel->promote($taskId, $previousCurrent['comment_id'], $userId);
    check('situação anterior pode ser promovida novamente', $currentText() === 'Informe 5');

    echo "\nIntegridade adicional\n";
    $last = $reportModel->getCurrentByTask($taskId);
    $reportModel->create($taskId, $last['comment_id'], $userId);
    check('marcar o mesmo comentário duas vezes não duplica', count($reportModel->getAllByTask($taskId)) === 6);
    check('situação inalterada após remarcação', $currentText() === 'Informe 5');

    $configModel->save(array(StatusReportMirrorModel::CONFIG_KEY => 0));
    $container['memoryCache']->flush();
    check('espelho desligado não altera a tarefa', $mirrorModel->sync($taskId) === false);

    echo "\nCompatibilidade de instalação\n";
    check('nenhum conflito de template detectado nesta instalação', OverrideGuard::hasConflicts() === false);

    foreach (OverrideGuard::getConflicts() as $conflict) {
        echo '  -> modo restrito: '.$conflict['template'].' também sobrescrito por '.$conflict['plugin']."\n";
        echo "     indisponível apenas a integração visual com o histórico de comentários\n";
    }
} finally {
    if ($originalMirror !== null) {
        if ($mirrorSettingExisted) {
            $configModel->save(array(StatusReportMirrorModel::CONFIG_KEY => $originalMirror));
        } else {
            $configModel->remove(StatusReportMirrorModel::CONFIG_KEY);
        }
        $container['memoryCache']->flush();
        echo "\nConfiguração restaurada.\n";
    }

    if ($projectId > 0) {
        $projectModel->remove($projectId);
        echo "Projeto temporário removido.\n";
    }
}

echo "\n----------------------------------------\n";
echo $failures === 0 ? "TODOS OS $checks TESTES PASSARAM\n" : "$failures de $checks TESTES FALHARAM\n";
exit($failures === 0 ? 0 : 1);
