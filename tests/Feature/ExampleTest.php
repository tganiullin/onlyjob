<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_from_root_to_aya(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('https://aya.ru');
    }
}
