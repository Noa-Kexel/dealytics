<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les pages maison ne remplacent l'écran de Laravel qu'en dehors du debug,
     * sinon les développeurs perdraient la trace détaillée.
     */
    private function withoutDebug(): void
    {
        config(['app.debug' => false]);
    }

    public function test_an_unknown_url_renders_the_branded_page(): void
    {
        $this->withoutDebug();

        $this->get('/cette-page-nexiste-pas')
            ->assertStatus(404)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 404),
            );
    }

    public function test_a_forbidden_page_renders_the_branded_page(): void
    {
        $this->withoutDebug();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertStatus(403)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 403),
            );
    }

    public function test_debug_mode_keeps_laravels_detailed_output(): void
    {
        config(['app.debug' => true]);

        $response = $this->get('/cette-page-nexiste-pas');

        $response->assertStatus(404);
        $this->assertNull($response->headers->get('x-inertia'));
    }

    public function test_api_calls_keep_a_json_error(): void
    {
        $this->withoutDebug();

        $this->getJson('/api/cette-route-nexiste-pas')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_the_maintenance_page_is_self_contained(): void
    {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('Maintenance en cours', $html);
        $this->assertStringContainsString('DEALYTICS', $html);
        // Aucune dépendance à Vite : la page doit s'afficher application éteinte.
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('/build/', $html);
    }

    public function test_the_last_resort_500_page_is_self_contained(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Une erreur est survenue', $html);
        $this->assertStringNotContainsString('/build/', $html);
    }

    public function test_error_previews_are_not_exposed_in_production(): void
    {
        // app()->isLocal() est faux dans l'environnement « testing », donc les
        // routes d'aperçu ne doivent pas être enregistrées ici non plus.
        $this->withoutDebug();

        $this->get('/_erreur/404')->assertStatus(404);
    }
}
