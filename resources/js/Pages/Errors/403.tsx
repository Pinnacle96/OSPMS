import ErrorPage from '@/Components/Feedback/ErrorPage';
export default function Page() { return <ErrorPage status={403} title="Access denied" description="Your account does not have permission to access this page. Contact your administrator if you need access." />; }
