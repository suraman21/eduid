import {
    LayoutDashboard, Users, GraduationCap, UserCog, ClipboardCheck,
    FileBarChart, CreditCard, PlayCircle, type LucideIcon,
} from 'lucide-react';

/* ── Live progress signals shared from the backend ───────────────
   Booleans derived from real data, so a checklist task lights up the
   moment the user actually does the thing. Shape differs per role. */
export interface OnboardingProgress {
    // admin / staff
    has_classes?: boolean;
    has_students?: boolean;
    has_teacher?: boolean;
    has_attendance?: boolean;
    has_grades?: boolean;
    has_order?: boolean;
}

export interface OnboardingState {
    tour_seen: boolean;
    dismissed: boolean;
    finished_at: string | null;
    progress: OnboardingProgress | null;
}

/* ── Coach-mark step: a spotlight + tooltip on the current screen ─
   `anchor` = a [data-tour] attribute value to highlight, or null for a
   centered card (welcome / finale). */
export interface CoachStep {
    anchor: string | null;
    title: string;
    body: string;
    emoji?: string;
}

/* ── Getting-Started checklist task ──────────────────────────────
   `done` reads the live progress; `href` navigates the user there. */
export interface ChecklistTask {
    id: string;
    label: string;
    desc: string;
    icon: LucideIcon;
    href?: string;
    /** Special task that replays the coach-mark tour instead of navigating. */
    replayTour?: boolean;
    done: (s: OnboardingState) => boolean;
}

interface RolePlan {
    coach: CoachStep[];
    checklist: ChecklistTask[];
}

/* ── School Admin plan ───────────────────────────────────────── */
const adminPlan: RolePlan = {
    coach: [
        {
            anchor: null, emoji: '👋',
            title: 'Welcome to EduID',
            body: "Let's take 60 seconds to show you around. You can skip anytime and pick this tour back up whenever you like.",
        },
        {
            anchor: 'nav-students',
            title: 'Students',
            body: 'Your student registry lives here — add students one at a time or import a whole list, search, and manage profiles.',
        },
        {
            anchor: 'nav-classes',
            title: 'Classes',
            body: 'Create your grades and sections here first. Everything else — attendance, grades, timetable — hangs off your classes.',
        },
        {
            anchor: 'nav-staff',
            title: 'Staff & assignments',
            body: 'Add teachers and assign them to classes and subjects (e.g. "1A → English", or homeroom of 2B). Assignments control what each teacher can do.',
        },
        {
            anchor: 'nav-attendance',
            title: 'Attendance',
            body: 'Record daily attendance from the web, or let teachers scan student ID cards with the EduID mobile app — it works fully offline.',
        },
        {
            anchor: 'nav-cardorders',
            title: 'ID cards',
            body: 'Order professional PVC cards here. Arkeon prints and delivers them — you never handle print files. Just pick students and confirm.',
        },
        {
            anchor: 'onboarding-launcher',
            title: 'Your setup checklist',
            body: 'Track your progress here. It ticks off automatically as you go, and you can reopen this tour anytime from inside.',
        },
        {
            anchor: null, emoji: '🎉',
            title: "You're ready to roll!",
            body: 'Work through the checklist at your own pace. Reach 100% and there might be a little surprise. 😉',
        },
    ],
    checklist: [
        { id: 'take_tour', label: 'Take the quick tour', desc: 'A 60-second walkthrough of the basics', icon: PlayCircle, replayTour: true, done: (s) => s.tour_seen },
        { id: 'create_class', label: 'Create your first class', desc: 'A grade and section, e.g. Grade 1 - A', icon: GraduationCap, href: '/school/classes', done: (s) => !!s.progress?.has_classes },
        { id: 'add_student', label: 'Add your first student', desc: 'Register a student into a class', icon: Users, href: '/school/students/create', done: (s) => !!s.progress?.has_students },
        { id: 'add_teacher', label: 'Add a teacher', desc: 'Create a teacher and assign their classes', icon: UserCog, href: '/school/staff/create', done: (s) => !!s.progress?.has_teacher },
        { id: 'record_attendance', label: 'Record attendance', desc: 'Take attendance for one class', icon: ClipboardCheck, href: '/school/attendance', done: (s) => !!s.progress?.has_attendance },
        { id: 'enter_grades', label: 'Enter grades', desc: 'Add and publish a grade', icon: FileBarChart, href: '/school/grades', done: (s) => !!s.progress?.has_grades },
        { id: 'order_cards', label: 'Order ID cards', desc: 'Send your first card order to Arkeon', icon: CreditCard, href: '/school/card-orders/create', done: (s) => !!s.progress?.has_order },
    ],
};

/* ── Teacher plan ────────────────────────────────────────────── */
const teacherPlan: RolePlan = {
    coach: [
        {
            anchor: null, emoji: '👋',
            title: 'Welcome to EduID',
            body: "Here's a quick 30-second look at everything you'll need. Skip anytime — you can replay this whenever you want.",
        },
        {
            anchor: 'nav-dashboard',
            title: 'Your dashboard',
            body: 'Your classes, today\'s schedule, and whether attendance has been taken all live here. Start each day from this screen.',
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
            anchor: 'onboarding-launcher',
            title: 'Your checklist',
            body: 'Track your first steps here and reopen this tour anytime.',
        },
        {
            anchor: null, emoji: '🎉',
            title: "You're all set!",
            body: 'Take attendance and enter a grade to complete your checklist.',
        },
    ],
    checklist: [
        { id: 'take_tour', label: 'Take the quick tour', desc: 'A 30-second walkthrough', icon: PlayCircle, replayTour: true, done: (s) => s.tour_seen },
        { id: 'record_attendance', label: 'Take attendance', desc: 'Record attendance for one of your classes', icon: ClipboardCheck, href: '/school/attendance', done: (s) => !!s.progress?.has_attendance },
        { id: 'enter_grades', label: 'Enter a grade', desc: 'Add a mark for a subject you teach', icon: FileBarChart, href: '/school/grades', done: (s) => !!s.progress?.has_grades },
    ],
};

/** Pick the tour plan for a role. Staff follow the admin plan (read-mostly). */
export function planForRole(role: string): RolePlan {
    return role === 'teacher' ? teacherPlan : adminPlan;
}

export const DASHBOARD_ICON: LucideIcon = LayoutDashboard;
