<hr>
<h3><?= t('Status Report') ?></h3>
<div class="form-group">
    <input type="hidden" name="statusreport_mirror_description" value="0">
    <?= $this->form->checkbox(
        'statusreport_mirror_description',
        t('Mirror the current status inside the task description'),
        1,
        ! isset($values['statusreport_mirror_description']) || $values['statusreport_mirror_description'] == 1
    ) ?>
    <p class="form-help">
        <?= t('Enabled by default. The current status is also written into the task description, between hidden markers, while manual text outside that region is preserved.') ?>
    </p>
</div>
