<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Flarum\Discussion\Discussion;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Discussions that had the prefix keep their titles and simply lose the label;
 * the foreign key sets their rubric_prefix_id to null.
 */
class DeletePrefixController extends AbstractPrefixController
{
    protected function act(ServerRequestInterface $request): void
    {
        $prefix = Prefix::query()->findOrFail($this->routeId($request));

        // The foreign key does this too; doing it here as well covers a
        // database that does not enforce foreign keys.
        Discussion::query()->where('rubric_prefix_id', $prefix->id)->update(['rubric_prefix_id' => null]);

        $prefix->delete();
    }
}
