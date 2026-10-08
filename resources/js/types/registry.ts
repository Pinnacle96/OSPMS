import type { Paginated } from '@/types';
export type Kind = 'lgas' | 'parks' | 'routes';
export type RegistryRecord = {
    id: number; public_id: string; name?: string; code?: string; park_code?: string; route_code?: string;
    origin?: string; destination?: string; description?: string | null; status: string; created_at?: string;
    administrative_contact_name?: string | null; administrative_contact_phone?: string | null; administrative_contact_email?: string | null;
    lga_id?: number; lga?: { id: number; public_id?: string; name: string }; address?: string; latitude?: string | null; longitude?: string | null;
    category?: string | null; contact_phone?: string | null; activated_at?: string | null;
    parks_count?: number; routes_count?: number; can_update?: boolean; pivot?: { status: string };
};
export type Option = { id: number; name: string; status?: string };
export type RouteOption = Pick<RegistryRecord, 'id' | 'route_code' | 'origin' | 'destination'>;
export type RegistryFilters = { search?: string; status?: string; lga_id?: string; sort?: string; direction?: string };
export type IndexProps = { records: Paginated<RegistryRecord>; filters: RegistryFilters; can_create: boolean; lgas?: Option[] };
export type DetailProps = { transport?: Partial<Record<'operators'|'drivers'|'vehicles',Paginated<import('@/types/catalog').Item>>> & {tickets?:import('@/types/ticketing').TicketPage}; record: RegistryRecord; can_update: boolean; can_archive: boolean; parks?: Paginated<RegistryRecord> | null;
    assigned_routes?: Paginated<RegistryRecord>; route_options?: RouteOption[]; selected_route_ids?: number[]; can_assign_routes?: boolean;
    activities: { description: string; created_at: string }[] };
export const registry = {
    lgas: { title: 'LGAs', singular: 'LGA', description: 'Administer local government records and their park network.' },
    parks: { title: 'Parks', singular: 'Park', description: 'Manage recognized motor parks, operational status and approved routes.' },
    routes: { title: 'Routes', singular: 'Route', description: 'Maintain approved transport corridors and their associated parks.' },
};
export function recordName(record: RegistryRecord) { return record.name ?? `${record.origin} → ${record.destination}`; }
