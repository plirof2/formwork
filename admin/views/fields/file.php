<?= $this->insert('fields.label') ?>
<input <?= $this->attr([
    'type'   => 'file',
    'class'  => 'file-input',
    'id'     => $field->name(),
    'name'   => $field->formName(),
    'accept' => $field->get('accept'),
]) ?>>
<label for="<?= $field->name() ?>" class="file-input-label">
    <span><?= $this->label('pages.files.upload-label') ?></span>
</label>
