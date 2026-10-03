import app from 'flarum/common/app';
import { KeyValueGambit } from 'flarum/common/query/IGambit';

/** prefix:rumor in the search box, the same as filter[prefix]=rumor. */
export default class PrefixGambit extends KeyValueGambit {
  key() {
    return app.translator.trans('ernestdefoe-rubric.lib.gambits.prefix.key', {}, true);
  }

  hint() {
    return app.translator.trans('ernestdefoe-rubric.lib.gambits.prefix.hint', {}, true);
  }

  filterKey() {
    return 'prefix';
  }
}
