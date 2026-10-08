import CorrectionDetail from '@/Components/Finance/CorrectionDetail';
import type {CorrectionDetail as Props} from '@/types/corrections';
export default function Show(props:Props){return <CorrectionDetail {...props} refund={true}/>;}
