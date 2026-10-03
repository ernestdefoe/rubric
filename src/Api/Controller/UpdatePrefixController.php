<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Psr\Http\Message\ServerRequestInterface;

class UpdatePrefixController extends AbstractPrefixController
{
    protected function act(ServerRequestInterface $request): void
    {
        $prefix = Prefix::query()->findOrFail($this->routeId($request));
        $this->fill($prefix, (array) $request->getParsedBody());
        $prefix->save();
    }
}
