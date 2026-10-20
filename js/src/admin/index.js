import app from 'flarum/admin/app';

app.initializers.add('ernestdefoe-cuecard', () => {
  const t = (key) => app.translator.trans('ernestdefoe-cuecard.admin.permissions.' + key);

  // No settings: macros are written and managed from the forum's composer.
  app.registry
    .for('ernestdefoe-cuecard')
    .registerPermission({ icon: 'fas fa-layer-group', label: t('use'), permission: 'ernestdefoe-cuecard.use' }, 'reply')
    .registerPermission({ icon: 'far fa-floppy-disk', label: t('save_personal'), permission: 'ernestdefoe-cuecard.savePersonal' }, 'reply')
    .registerPermission({ icon: 'fas fa-layer-group', label: t('manage_global'), permission: 'ernestdefoe-cuecard.manageGlobal' }, 'moderate');
});
