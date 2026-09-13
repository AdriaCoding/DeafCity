<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\DeafcityMapAddHandler;
use Studio\DeafcityMapStore;

class DeafcityMapAddHandlerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/deafcity-map-' . uniqid() . '.json';
        file_put_contents($this->path, "[]\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        @unlink($this->path . '.lock');
    }

    public function test_adds_location_and_makes_it_retrievable(): void
    {
        $store = new DeafcityMapStore($this->path);
        $handler = new DeafcityMapAddHandler($store);

        $result = $handler->handle('Lisboa', 'LGP', '2027', '38.7223', '-9.1393');

        $this->assertTrue($result['ok']);
        $this->assertSame('2027-lisboa', $result['id']);
        $this->assertSame('DEAF.city LISBOA LGP', $result['label']);

        $added = $store->all()[0];
        $this->assertSame('2027-lisboa', $added['id']);
        $this->assertSame('Lisboa', $added['city']);
        $this->assertSame('LGP', $added['sign_language_code']);
        $this->assertSame([-9.1393, 38.7223], $added['coordinates']);
    }

    public function test_rejects_duplicate_city_year(): void
    {
        $handler = new DeafcityMapAddHandler(new DeafcityMapStore($this->path));

        $this->assertTrue($handler->handle('Lisboa', 'LGP', '2027', '38.7223', '-9.1393')['ok']);
        $again = $handler->handle('Lisboa', 'LGP', '2027', '38.7', '-9.1');

        $this->assertFalse($again['ok']);
        $this->assertNotEmpty($again['errors']);
    }

    public function test_rejects_invalid_coordinates(): void
    {
        $handler = new DeafcityMapAddHandler(new DeafcityMapStore($this->path));

        $result = $handler->handle('Lisboa', 'LGP', '2027', '99', '0');

        $this->assertFalse($result['ok']);
    }
}
