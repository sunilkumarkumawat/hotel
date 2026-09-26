<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_login_screen_is_reachable(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
