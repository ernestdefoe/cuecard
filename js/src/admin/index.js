import app from 'flarum/admin/app';

app.initializers.add('ernestdefoe-cuecard', () => {
  const t = (key) => app.translator.trans('ernestdefoe-cuecard.admin.' + key);

  app.registry
    .for('ernestdefoe-cuecard')
    .registerSetting(() => <p className="helpText">{t('intro')}</p>)
    .registerPermission({ icon: 'fas fa-layer-group', label: t('permissions.use'), permission: 'ernestdefoe-cuecard.use' }, 'reply')
    .registerPermission(
      { icon: 'far fa-floppy-disk', label: t('permissions.save_personal'), permission: 'ernestdefoe-cuecard.savePersonal' },
      'reply'
    )
    .registerPermission(
      { icon: 'fas fa-layer-group', label: t('permissions.manage_global'), permission: 'ernestdefoe-cuecard.manageGlobal' },
      'moderate'
    );
});
