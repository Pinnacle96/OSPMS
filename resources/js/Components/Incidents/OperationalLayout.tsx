import type {ReactNode} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import FieldLayout from '@/Layouts/FieldLayout';
export default function OperationalLayout({title,field=false,children}:{title:string;field?:boolean;children:ReactNode}){return field?<FieldLayout title={title}>{children}</FieldLayout>:<AppLayout title={title} retainFormOnError>{children}</AppLayout>;}
