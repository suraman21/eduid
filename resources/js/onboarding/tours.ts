/* ── Onboarding: a guided welcome tour only ──────────────────────
   A short, skippable coach-mark walkthrough that shows each user where
   the things they care about live. No checklist, no gamification — it
   never pushes anyone toward tasks that aren't their responsibility. */

export interface OnboardingState {
    tour_seen: boolean;
    dismissed: boolean;
}

/* A coach-mark step: a spotlight + tooltip on the current screen.
   `anchor` = a [data-tour] attribute value to highlight, or null for a
   centered card (welcome / finale). */
export interface CoachStep {
    anchor: string | null;
    title: string;
    body: string;
    emoji?: string;
}

/* ── School Admin tour — focused on what an admin actually sets up ── */
const adminCoach: CoachStep[] = [
    {
        anchor: null, emoji: '👋',
        title: 'Welcome to EduID',
        body: "Let's take 60 seconds to show you around. You can skip anytime and reopen this tour whenever you like from the help button.",
    },
    {
        anchor: 'nav-classes',
        title: 'Start with Classes',
        body: 'Create your grades and sections here first. Everything else — students, timetable, attendance, grades — hangs off your classes.',
    },
    {
        anchor: 'nav-students',
        title: 'Add Students',
        body: 'Register students one at a time or import a whole list, then assign them to their class.',
    },
    {
        anchor: 'nav-staff',
        title: 'Staff & assignments',
        body: 'Add your teachers and assign them to classes and subjects. Assignments control what each teacher can see and do.',
    },
    {
        anchor: 'nav-cardorders',
        title: 'ID cards',
        body: 'Order professional PVC cards here. Arkeon prints and delivers them — you never handle print files. Just pick students and confirm.',
    },
    {
        anchor: null, emoji: '🎉',
        title: "You're all set to begin",
        body: 'Set up your classes, students, and staff at your own pace. Teachers handle day-to-day attendance and grades from their own accounts.',
    },
];

/* ── Teacher tour — focused on their daily work ── */
const teacherCoach: CoachStep[] = [
    {
        anchor: null, emoji: '👋',
        title: 'Welcome to EduID',
        body: "Here's a quick 30-second look at everything you'll need. Skip anytime — reopen this tour whenever you want from the help button.",
    },
    {
        anchor: 'nav-dashboard',
        title: 'Your dashboard',
        body: "Your classes, today's schedule, and whether attendance has been taken all live here. Start each day from this screen.",
    },
    {
        anchor: 'nav-attendance',
        title: 'Attendance',
        body: 'Record attendance for your assigned classes. On the mobile app you can scan student ID cards — even with no internet.',
    },
    {
        anchor: 'nav-grades',
        title: 'Grades',
        body: 'Enter marks for the subjects you teach. Save drafts as you go, then publish when a term is ready.',
    },
    {
        anchor: null, emoji: '✅',
        title: "You're ready",
        body: "That's everything. Reopen this tour anytime from the help button in the corner.",
    },
];

/** Pick the tour for a role. Staff follow the admin tour (read-mostly). */
export function coachForRole(role: string): CoachStep[] {
    return role === 'teacher' ? teacherCoach : adminCoach;
}
