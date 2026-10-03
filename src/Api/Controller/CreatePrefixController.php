<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Psr\Http\Message\ServerRequestInterface;

class CreatePrefixController extends AbstractPrefixController
{
    protected function act(ServerRequestInterface $request): void
    {
        $prefix = new Prefix();
        $this->fill($prefix, (array) $request->getParsedBody());

        // A new prefix goes to the end of the list.
        $prefix->position = (int) Prefix::query()->max('position') + 1;
        $prefix->save();
    }
}
