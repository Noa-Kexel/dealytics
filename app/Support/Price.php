<?php

namespace App\Support;

/**
 * Formules prix partagées (alignées avec resources/js/lib/price.ts).
 */
class Price
{
    /**
     * Déduit le prix d'origine à partir du prix remisé et du % de réduction.
     * Nexarda ne renvoie souvent que le prix actuel + discount.
     */
    public static function deriveNormalPrice(float $lowest, int $discount, ?float $highest = null): ?float
    {
        if ($discount > 0 && $discount < 100 && $lowest > 0) {
            $normal = round($lowest / (1 - $discount / 100), 2);

            return $normal > 0 ? $normal : null;
        }

        $fallback = $highest ?? ($lowest > 0 ? $lowest : null);

        return $fallback !== null && $fallback > 0 ? $fallback : null;
    }

    /**
     * Expression SQL : économie unitaire (prix d'origine − prix actuel).
     * Même formule que deriveNormalPrice pour discount entre 1 et 99.
     */
    public static function sqlUnitSavingsExpression(
        string $priceColumn = 'price_snapshots.price',
        string $discountColumn = 'price_snapshots.discount',
    ): string {
        return "({$priceColumn} / (1 - {$discountColumn} / 100.0) - {$priceColumn})";
    }
}
