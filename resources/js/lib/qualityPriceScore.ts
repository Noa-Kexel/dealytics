const NEUTRAL_QUALITY = 60;

export function hasQualityData(
    metacritic?: number | null,
    rating?: number | null,
): boolean {
    return !!(metacritic || rating);
}

export function getQualityValue(
    metacritic?: number | null,
    rating?: number | null,
): number {
    if (metacritic) {
        return metacritic;
    }

    if (rating) {
        return Math.round(rating * 20);
    }

    return NEUTRAL_QUALITY;
}

export function getSavingsScore(discount: number): number {
    return Math.min(Math.round(discount * 1.5), 100);
}

export function getQualityPriceScore(
    discount: number,
    metacritic?: number | null,
    rating?: number | null,
): number {
    const quality = getQualityValue(metacritic, rating);
    const savings = getSavingsScore(discount);

    return Math.round(quality * 0.6 + savings * 0.4);
}

export function getScoreColor(score: number): string {
    if (score >= 75) {
        return 'text-dealytics-cyan';
    }

    if (score >= 55) {
        return 'text-dealytics-purple';
    }

    if (score >= 35) {
        return 'text-yellow-400';
    }

    return 'text-muted-foreground';
}

export function getScoreBorderColor(score: number): string {
    if (score >= 75) {
        return 'border-dealytics-cyan/50 bg-dealytics-cyan/10';
    }

    if (score >= 55) {
        return 'border-dealytics-purple/50 bg-dealytics-purple/10';
    }

    if (score >= 35) {
        return 'border-yellow-400/50 bg-yellow-400/10';
    }

    return 'border-border bg-secondary/50';
}

export function getScoreLabel(score: number): string {
    if (score >= 75) {
        return 'Excellent';
    }

    if (score >= 55) {
        return 'Bon deal';
    }

    if (score >= 35) {
        return 'Moyen';
    }

    return 'Faible';
}
