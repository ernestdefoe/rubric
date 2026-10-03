<?php

namespace Ernestdefoe\Rubric;

use Flarum\Foundation\AbstractServiceProvider;

class RubricServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // One instance per request, so the list is read from the database once
        // however many places ask for it.
        $this->container->singleton(PrefixRepository::class);
    }
}
