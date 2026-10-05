const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

const divisions: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

export function timeAgo(iso: string): string {
    let duration = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const division of divisions) {
        if (Math.abs(duration) < division.amount) {
            return relative.format(Math.round(duration), division.unit);
        }

        duration /= division.amount;
    }

    return formatDate(iso);
}

export function formatDate(
    iso: string,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return new Intl.DateTimeFormat(undefined, options).format(new Date(iso));
}

export function daysUntil(iso: string): number {
    return Math.max(
        0,
        Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000),
    );
}

export function formatMoney(cents: number | string, currency = 'USD'): string {
    if (typeof cents === 'string') {
        return cents;
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(cents / 100);
}

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
