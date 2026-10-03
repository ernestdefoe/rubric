import app from 'flarum/admin/app';
import RubricPage from './components/RubricPage';

app.initializers.add('ernestdefoe-rubric', () => {
  app.registry
    .for('ernestdefoe-rubric')
    .registerPage(RubricPage)
    .registerPermission(
      {
        icon: 'fas fa-bookmark',
        label: app.translator.trans('ernestdefoe-rubric.admin.permission_staff_prefixes'),
        permission: 'rubric.useStaffPrefixes',
      },
      'moderate'
    );
});
