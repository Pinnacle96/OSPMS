import CorrectionForm from '@/Components/Finance/CorrectionForm';
import type {Props} from '@/Components/Finance/CorrectionForm';
export default function Create(props:Props){return <CorrectionForm key={props.idempotency_key} {...props} refund={false}/>;}
