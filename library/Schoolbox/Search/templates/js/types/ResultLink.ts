/**
 * A link to perform further actions on a result
 */
export type ResultLink = {
    id: number;
    url: string;
    title: string;
    icon?: string;
    action?: "awardAchievementModal" | "setGoalModal"; // actions that aren't just a link (for example opening a modal)
    disabled?: boolean;
};
