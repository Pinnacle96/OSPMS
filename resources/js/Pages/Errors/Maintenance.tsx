import ErrorPage from '@/Components/Feedback/ErrorPage';
export default function Page() { return <ErrorPage status={503} title="Scheduled maintenance" description="The system is temporarily unavailable. Please check back shortly." />; }
