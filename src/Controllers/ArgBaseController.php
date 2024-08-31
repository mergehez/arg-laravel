<?php

namespace Arg\Laravel\Controllers;

use Inertia\Inertia;
use Inertia\Response;

abstract class ArgBaseController
{
    protected function inertia(string $component, $pageData = []): Response
    {
        return Inertia::render($component, [
            'pageData' => $pageData
        ]);
    }
}
