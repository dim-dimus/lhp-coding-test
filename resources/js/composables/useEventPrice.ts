import type { EventPrice } from '@/types/events';

/**
 * Formats an event price for display, e.g. "US $217.44" or "Free".
 *
 * Some locales render USD as "US$217.44"; we insert a space between the
 * currency-code prefix and the symbol ("US $217.44") for readability.
 */
export function formatPrice(price: EventPrice | null): string | null {
    if (!price) {
        return null;
    }

    if (price.amount === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat(undefined, { style: 'currency', currency: price.currency }).format(price.amount).replace(/(\p{L})(\$)/u, '$1 $2');
}
