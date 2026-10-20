import Model from 'flarum/common/Model';

export default class Macro extends Model {}

Object.assign(Macro.prototype, {
  title: Model.attribute('title'),
  content: Model.attribute('content'),
  isGlobal: Model.attribute('isGlobal'),
  lock: Model.attribute('lock'),
  tagIds: Model.attribute('tagIds'),
  replaceTags: Model.attribute('replaceTags'),
  bestAnswer: Model.attribute('bestAnswer'),
  canEdit: Model.attribute('canEdit'),
  groups: Model.hasMany('groups'),
});
