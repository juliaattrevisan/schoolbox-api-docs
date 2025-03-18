import type { User } from "@schoolboxLibrary/types/User";

/**
 * The expected payload for a plan associated with a participant
 */
export type Plan = {
    closeDate: string | null;
    createdAt: string;
    deletedAt: string | null;
    description: string | null;
    dueDate: string | null;
    id: number;
    isDynamic: boolean;
    name: string | null;
    openDate: string | null;
    publishDate: string | null;
    updatedAt: string;
};

/**
 * The expected payload for a participant in the system
 */
export type Participant = {
    id: number;
    createdAt: string;
    excludedAt: string | null;
    plan: Plan;
    updatedAt: string;
    user: User;
};
