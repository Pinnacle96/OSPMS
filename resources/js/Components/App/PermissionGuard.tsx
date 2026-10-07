import type { ReactNode } from 'react';
import { usePermissions } from '@/hooks/usePermissions';
export default function PermissionGuard({ permission, children }: { permission: string; children: ReactNode }) { const { can } = usePermissions(); return can(permission) ? children : null; }
