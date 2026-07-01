import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import ProfileForms, { type ProfileData } from '@/Components/ProfileForms';

export default function Profile({ profile, updateUrl, passwordUrl }: {
    profile: ProfileData; updateUrl: string; passwordUrl: string;
}) {
    return (
        <AdminLayout>
            <Head title="My Profile" />
            <PageHeader title="My Profile" subtitle="Manage your account details and password." />
            <ProfileForms profile={profile} updateUrl={updateUrl} passwordUrl={passwordUrl} dark />
        </AdminLayout>
    );
}
