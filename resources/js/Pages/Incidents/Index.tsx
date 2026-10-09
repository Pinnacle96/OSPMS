import RecordList from '@/Components/Incidents/RecordList';
import type {RecordListProps} from '@/types/incidents';
export default function Index(props:RecordListProps){return <RecordList {...props} kind='incidents'/>;}
