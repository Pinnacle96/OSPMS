export type ReportDefinition={type:string;title:string;basis:string;can_export?:boolean};
export type ReportColumn={key:string;label:string;type:'text'|'integer'|'money'};
export type ReportSummary={key:string;label:string;value:string|number;type:'money'|'integer'};
export type ReportRow=Record<string,string|number|null>;
export type SavedExport={public_id:string;type:string;title:string;format:string;status:'queued'|'completed'|'failed';requested_at:string;generated_at?:string;error?:string;can_download:boolean;can_retry:boolean};
