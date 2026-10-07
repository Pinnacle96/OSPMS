import { Search } from 'lucide-react';
export default function SearchInput({ value, onChange, placeholder = 'Search records', id = 'search' }: { value: string; onChange: (value: string) => void; placeholder?: string; id?: string }) {
    return <div className="search-input"><label htmlFor={id} className="sr-only">{placeholder}</label><Search size={17} aria-hidden="true" /><input id={id} type="search" value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder} /></div>;
}
