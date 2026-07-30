<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_is_public_and_exposes_the_contact_email(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Faq')
                ->where('contactEmail', config('legal.editor.email')),
            );
    }
}
