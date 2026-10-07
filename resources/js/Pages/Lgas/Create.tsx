import RegistryForm from '@/Components/Registry/RegistryForm';
import type {RegistryRecord,Option} from '@/types/registry';
export default function Create(props:{record?:RegistryRecord;lgas?:Option[]}) { return <RegistryForm {...props} kind="lgas" />; }
