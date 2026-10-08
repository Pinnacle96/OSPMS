import FinancialList from '@/Components/Finance/FinancialList';
import type {ComponentProps} from 'react';
export default function Index(props:ComponentProps<typeof FinancialList>){return <FinancialList {...props} ledger/>;}
