/**
 * The different types of results available
 */
export enum ResultTypes {
    ASSESSMENT = "assessment",
    ASSESSMENT_DUE_WORK = "assessment.dueWork",
    ASSESSMENT_LESSON_PLAN = "assessment.lessonPlan",
    ASSESSMENT_PROJECT = "assessment.project",
    ASSESSMENT_LTI = "assessment.ltiAssessment",
    ASSESSMENT_TASK = "assessment.task",
    ASSESSMENT_QUIZ = "assessment.quiz",
    BADGE = "badge",
    EVENT = "event",
    FILE = "file",
    FILE_AUDIO = "file.audio",
    FILE_DOCUMENT = "file.document",
    FILE_IMAGE = "file.image",
    FILE_VIDEO = "file.video",
    FILE_OTHER = "file.other",
    FORUM = "forum",
    LINK = "link",
    NEWS = "news",
    PAGE = "page",
    PAGE_CLASS = "page.class",
    PAGE_COURSE = "page.course",
    PAGE_HOMEPAGE = "page.homepage",
    PAGE_UNIT = "page.unit",
    PAGE_OTHER = "page.other",
    TEXTBOX = "textbox",
    TILE = "tile",
    USER = "user",
    USER_GUEST = "user.guest",
    USER_PARENT = "user.parent",
    USER_STAFF = "user.staff",
    USER_STUDENT = "user.student",
}

/**
 * To provide context where the result is from
 */
export type ResultContext = {
    id: number;
    name: string;
    url: string;
};

/**
 * A search result
 */
export type Result = {
    id: number;
    type: ResultTypes;
    date: string;
    url: string;
    title: string;
    image: string | null;
    description: string | null;
    creator: {
        id: number;
        title?: string;
        firstName?: string;
        lastName?: string;
        fullName?: string;
    };
    context: ResultContext[] | null;
    icon?: string;
    _links: {
        subResults?: string; // url to load further results froms
        stream?: string; // when the result is a stream, this is the url to load the stream from
        data?: [];
        metadata?: {
            cursor: {
                current: string;
                next: string;
            };
        };
    };
};
