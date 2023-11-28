/**
 * A link to perform further actions on a result
 */
export type ResultLink = {
    id: number;
    url: string;
    title: string;
    icon?: string;
    action?: string;
    data?: {
        achievementExpiry?: number | null;
        goalExpiry?: number | null;
    };
};
