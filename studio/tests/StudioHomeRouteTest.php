<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioHomeRoute;

class StudioHomeRouteTest extends TestCase
{
    public function test_default_view_is_always_continguts(): void
    {
        $this->assertSame(
            StudioHomeRoute::VIEW_CONTINGUTS,
            StudioHomeRoute::resolveDefaultView(false, false),
        );
        $this->assertSame(
            StudioHomeRoute::VIEW_CONTINGUTS,
            StudioHomeRoute::resolveDefaultView(true, false),
        );
        $this->assertSame(
            StudioHomeRoute::VIEW_CONTINGUTS,
            StudioHomeRoute::resolveDefaultView(true, true),
        );
    }
}
