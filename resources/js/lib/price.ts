/**
 * Formules prix partagées (alignées avec App\Support\Price).
 */

/** Déduit le prix d'origine à partir du prix remisé et du % de réduction. */
export function deriveNormalPrice(
    lowest: number,
    discount: number,
    highest?: number | null,
): number | null {
    if (discount > 0 && discount < 100 && lowest > 0) {
        const normal = lowest / (1 - discount / 100);

        return normal > 0 ? normal : null;
    }

    const fallback = highest ?? (lowest > 0 ? lowest : null);

    return fallback !== null && fallback > 0 ? fallback : null;
}
