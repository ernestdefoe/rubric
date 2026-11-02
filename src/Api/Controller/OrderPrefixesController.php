<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Illuminate\Database\Query\Expression;
use Psr\Http\Message\ServerRequestInterface;

/** The order from a drag: { "order": [3, 1, 2] }. */
class OrderPrefixesController extends AbstractPrefixController
{
    protected function act(ServerRequestInterface $request): void
    {
        $order = array_values(array_unique(array_filter(array_map('intval', (array) (((array) $request->getParsedBody())['order'] ?? [])))));

        if (! $order) {
            return;
        }

        // One UPDATE for the whole list. Every value is an int (intval above), so inlining is safe.
        $case = 'CASE id';
        foreach ($order as $position => $id) {
            $case .= " WHEN $id THEN $position";
        }

        Prefix::query()->whereIn('id', $order)->update(['position' => new Expression($case.' END')]);
    }
}
