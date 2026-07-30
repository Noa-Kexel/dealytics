<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'mentions légales' => ['legal.notice', 'legal/Notice'],
            'confidentialité' => ['legal.privacy', 'legal/Privacy'],
            'CGU' => ['legal.terms', 'legal/Terms'],
        ];
    }

    #[DataProvider('pages')]
    public function test_legal_pages_are_public_and_render_their_component(string $route, string $component): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component($component)
                ->has('legal.editor.name')
                ->has('legal.host.name')
                ->has('legal.dataSources', 4)
                ->where('legal.updatedAt', config('legal.updated_at')),
            );
    }

    public function test_legal_routes_use_readable_french_urls(): void
    {
        $this->assertSame(url('/mentions-legales'), route('legal.notice'));
        $this->assertSame(url('/confidentialite'), route('legal.privacy'));
        $this->assertSame(url('/conditions-generales'), route('legal.terms'));
    }
}
