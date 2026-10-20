<?php

namespace Ernestdefoe\Cuecard;

use Flarum\Foundation\AbstractServiceProvider;

class Provider extends AbstractServiceProvider
{
    public function register(): void
    {
        // One instance per process: it remembers, for the length of a request,
        // which reply actions were refused so the response can say so.
        $this->container->singleton(ReplyActions::class);
    }
}
