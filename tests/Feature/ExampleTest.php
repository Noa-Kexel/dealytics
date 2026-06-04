<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response(): void
    {
        $this->get(route('home'))
            ->assertOk();
    }

    public function test_search_redirects_to_home(): void
    {
        $this->get('/search')
            ->assertRedirect(route('home'));
    }
}
