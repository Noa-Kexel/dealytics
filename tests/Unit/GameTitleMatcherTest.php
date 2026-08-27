<?php

namespace Tests\Unit;

use App\Support\GameTitleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameTitleMatcherTest extends TestCase
{
    #[DataProvider('matchCases')]
    public function test_matches(string $wanted, string $candidate, bool $expected): void
    {
        $this->assertSame($expected, GameTitleMatcher::matches($wanted, $candidate));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function matchCases(): array
    {
        return [
            'titre identique' => ['Hades', 'Hades', true],
            'casse et symboles ignorés' => ['EA SPORTS FC 26', 'EA SPORTS FC™ 26', true],
            'ponctuation ignorée' => ["Baldur's Gate 3", 'Baldurs Gate 3', true],
            'accents ignorés' => ['Pokémon Legends', 'Pokemon Legends', true],
            'suffixe d\'édition toléré' => [
                'The Elder Scrolls V: Skyrim',
                'The Elder Scrolls V: Skyrim Special Edition',
                true,
            ],
            'édition côté demande' => [
                'Grand Theft Auto V: Premium Edition',
                'Grand Theft Auto V',
                true,
            ],
            'suite différente refusée' => ['Hades', 'Hades II', false],
            'préfixe trompeur refusé' => [
                'Grand Theft Auto VI',
                'Grand Theft Auto: Vice City – The Definitive Edition',
                false,
            ],
            'sous-titre en trop refusé' => [
                'Call of Duty: Black Ops 6',
                'Call of Duty: Black Ops 6 - Cross-Gen Bundle',
                false,
            ],
            'jeu sans rapport refusé' => ['Elden Ring', 'Dark Souls III', false],
            'titre vide refusé' => ['', 'Hades', false],
        ];
    }

    public function test_normalize_strips_case_accents_and_punctuation(): void
    {
        $this->assertSame('the witcher 3 wild hunt', GameTitleMatcher::normalize('The Witcher 3: Wild Hunt'));
        $this->assertSame('', GameTitleMatcher::normalize('  -- '));
    }
}
