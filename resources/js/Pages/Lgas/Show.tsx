import RegistryDetail from '@/Components/Registry/RegistryDetail';
import type {DetailProps} from '@/types/registry';
export default function Show(props:DetailProps) { return <RegistryDetail {...props} kind="lgas" />; }
