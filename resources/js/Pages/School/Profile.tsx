import { Head } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import ProfileForms, { type ProfileData } from '@/Components/ProfileForms';

export default function Profile({ profile, updateUrl, passwordUrl }: {
    profile: ProfileData; updateUrl: string; passwordUrl: string;
}) {
    return (
        <SchoolLayout>
            <Head title="My Profile" />
            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900">My Profile</h1>
                <p className="text-sm text-slate-500 mt-0.5">Manage your account details and password.</p>
            </div>
            <ProfileForms profile={profile} updateUrl={updateUrl} passwordUrl={passwordUrl} dark={false} />
        </SchoolLayout>
    );
}
