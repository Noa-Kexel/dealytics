<?php

namespace Tests\Unit;

use App\Support\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PriceTest extends TestCase
{
    #[DataProvider('normalPriceCases')]
    public function test_derive_normal_price(
        float $lowest,
        int $discount,
        ?float $highest,
        ?float $expected,
    ): void {
        $this->assertSame(
            $expected,
            Price::deriveNormalPrice($lowest, $discount, $highest),
        );
    }

    /**
     * @return array<string, array{0: float, 1: int, 2: ?float, 3: ?float}>
     */
    public static function normalPriceCases(): array
    {
        return [
            '50% off sur 10€ → 20€' => [10.0, 50, null, 20.0],
            '25% off sur 15€ → 20€' => [15.0, 25, null, 20.0],
            'sans remise, fallback lowest' => [19.99, 0, null, 19.99],
            'sans remise, préfère highest' => [12.0, 0, 29.99, 29.99],
            'discount 100% invalide → fallback' => [0.0, 100, 49.99, 49.99],
            'prix à zéro sans highest → null' => [0.0, 0, null, null],
            'discount négatif → fallback lowest' => [10.0, -5, null, 10.0],
        ];
    }

    public function test_sql_unit_savings_expression_matches_php_formula(): void
    {
        $expr = Price::sqlUnitSavingsExpression('price', 'discount');

        $this->assertSame('(price / (1 - discount / 100.0) - price)', $expr);

        // 20€ à -60% → économie 30€ (même logique que HomeStats).
        $lowest = 20.0;
        $discount = 60;
        $normal = Price::deriveNormalPrice($lowest, $discount);
        $this->assertSame(50.0, $normal);
        $this->assertSame(30.0, round($normal - $lowest, 2));
    }
}
