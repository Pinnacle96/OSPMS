import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import type { SharedProps } from '@/types';
export default function useBrandTokens(): CSSProperties {
    const { branding } = usePage<SharedProps>().props.system;
    return { '--gov-primary': branding.primary, '--gov-primary-dark': branding.primary_dark, '--gov-secondary': branding.secondary } as CSSProperties;
}
