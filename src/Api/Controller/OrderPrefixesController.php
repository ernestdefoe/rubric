<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Psr\Http\Message\ServerRequestInterface;

/** The order from a drag: { "order": [3, 1, 2] }. */
class OrderPrefixesController extends AbstractPrefixController
{
    protected function act(ServerRequestInterface $request): void
    {
        $order = array_values(array_map('intval', (array) (((array) $request->getParsedBody())['order'] ?? [])));

        foreach ($order as $position => $id) {
            Prefix::query()->where('id', $id)->update(['position' => $position]);
        }
    }
}
