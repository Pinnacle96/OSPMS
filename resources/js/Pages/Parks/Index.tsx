import RegistryIndex from '@/Components/Registry/RegistryIndex';
import type {IndexProps} from '@/types/registry';
export default function Index(props:IndexProps) { return <RegistryIndex {...props} kind="parks" />; }
