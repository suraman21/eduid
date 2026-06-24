import type { Variants } from 'framer-motion';

export const pageTransition = {
    initial: { opacity: 0, y: 8 },
    animate: { opacity: 1, y: 0 },
    exit: { opacity: 0, y: -8 },
    transition: { duration: 0.3, ease: 'easeOut' as const },
};

export const staggerContainer: Variants = {
    animate: {
        transition: { staggerChildren: 0.05 },
    },
};

export const fadeInUp = {
    initial: { opacity: 0, y: 12 },
    animate: { opacity: 1, y: 0 },
    transition: { duration: 0.4, ease: 'easeOut' as const },
};

export const cardHover = {
    whileHover: { y: -2, transition: { duration: 0.2 } },
};

export const scaleIn = {
    initial: { scale: 0.95, opacity: 0 },
    animate: { scale: 1, opacity: 1 },
    transition: { duration: 0.3 },
};
