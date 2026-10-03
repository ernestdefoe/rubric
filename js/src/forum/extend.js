import Extend from 'flarum/common/extenders';
import PrefixGambit from '../common/PrefixGambit';

export default [
  new Extend.Search() //
    .gambit('discussions', PrefixGambit),
];
