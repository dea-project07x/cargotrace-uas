<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_url_redirects_to_the_shipment_list(): void
    {
        $this->get('/')->assertRedirect('/shipments');
    }
}