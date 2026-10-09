import IncidentForm,{type IncidentFormProps} from '@/Components/Incidents/IncidentForm';
export default function Create(props:IncidentFormProps){return <IncidentForm key={props.idempotency_key} {...props} field={false}/>;}
