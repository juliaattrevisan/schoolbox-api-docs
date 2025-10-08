import type { UserAuthor } from "@schoolboxLibrary/types/User";

/** @see /library/Schoolbox/Learning/Assessment/Translator/AssessmentForFeed.php */
export type AssessmentForFeed = {
    id: number;
    title: string;
    publishedAt: string | null;
    openAt: string | null;
    dueAt: string | null;
    closeAt: string | null;
    weight: number;
    sequence: number;
    workType: string | null;
    folderId: number | string;
    folderName: string;
};

/** @see /library/Schoolbox/Learning/Assessment/DueWork/Feedback/Translator/ModelToArray.php */
export type DueWorkFeedback = {
    id: number;
    mark: string;
    gradientMark: string | null;
    comment: string;
    publishedAt: string;
    instructor: UserAuthor | null;
    assessment: AssessmentForFeed | null;
    _links: {
        summary: string | null;
    };
};

/** @see /library/Schoolbox/Learning/Assessment/Quiz/Feedback/Translator/ModelToArray.php */
export type QuizFeedback = {
    id: number;
    mark: string;
    gradientMark: string | null;
    normalisedMark: string | null;
    comment: string;
    publishedAt: string;
    instructor: UserAuthor | null;
    assessment: AssessmentForFeed | null;
    _links: {
        summary: string | null;
    };
};
