import CorrectionList from '@/Components/Finance/CorrectionList';
import type {Paginated} from '@/types';
import type {Correction,CorrectionFilters} from '@/types/corrections';
export default function Index(props:{records:Paginated<Correction>;filters:CorrectionFilters;can_create?:boolean}){return <CorrectionList {...props} refund={false}/>;}
