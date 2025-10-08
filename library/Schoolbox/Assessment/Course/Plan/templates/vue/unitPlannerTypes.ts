import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";

export type Assessment = {
    id: number;
};

export type RubricCapability = {
    id: number;
    name: string;
    course: boolean;
    indicators: {
        id: number;
        name: string;
    }[];
};

export type TextboxContent = {
    componentId: number; // the id of the component, -1 if it doesn't exist (a componentId must exist if a textboxId exists)
    textboxId: number; // the id of the textbox within the component, -1 if it doesn't exist (note: a componentId may exist without a textboxId)
    body: string;
    inTemplate: boolean; // whether the component is part of the Unit template, or just a component added to the Unit home page
    _links: {
        update: string | null;
    };
};

/**
 * A single Curriculum Overview component that's been added to a Unit's home page
 */
export type CurriculumOverviewComponent = {
    componentId: number;
    heading: string;
    filter?: Node; // the node selected as the Curriculum Tree Filter on the Curriculum Overview component, if not selected, can be set to null
    inTemplate: boolean; // whether the component is part of the Unit template, or just a component added to the Unit home page
};

/**
 * A single Course Builder component that's been added to a Unit's home page
 */
export type CourseBuilderComponent = {
    componentId: number;
    heading: string;
    inTemplate: boolean; // whether the component is part of the Unit template, or just a component added to the Unit home page
};

export type Unit = {
    id: number;
    title: string;
    homepage: string;
    folderId: number;
    activities: {
        _routes: ActivitiesListRoutes & {
            createComponent: string | null;
        };
        items: Activity[];
        components: CourseBuilderComponent[];
    };
    curriculum: {
        _routes: {
            createComponent: string | null;
            modifyCurriculum: string | null;
        };
        selectedNodes: Node[]; // the individual nodes assigned to the folder
        nodes: Node[]; // the selectedNodes, displayed within their entire tree structure, containing all parent nodes
        components: CurriculumOverviewComponent[];
    };
    rubricCapabilities: {
        _routes: {
            manageUnit: string | null;
            manageCourse: string | null;
        };
        usedIndicators: number[];
        usedCapabilities: number[];
        availableRubricCapabilities: RubricCapability[];
    };
    textboxes: {
        _routes: {
            createComponent: string | null;
            createTextbox: string | null;
        };
        items: Partial<Record<string, TextboxContent>>;
    };

    extraTextboxes: number;
    extraActivities: number;
    sequence: number;
};

/**
 * A component that has been set on a Unit template
 */
export type UnitTemplateComponent = {
    heading: string;
    filter?: Node; // the node selected as the Curriculum Tree Filter on the Curriculum Overview component, if not selected, can be set to null
};

export interface UnitTemplateTextboxComponent extends UnitTemplateComponent {
    emptyStateMessage: string;
}

/**
 * All components that have been set on a Unit template,
 * to help distinguish which component are linked to the Unit template,
 * and which are just components added to a home page
 */
export type UnitTemplate = {
    textboxDefId: null | number; // the textbox component definition ID
    curriculumDefId: null | number; // the curriculum component definition ID
    activityDefId: null | number; // the course builder definition ID
    curriculum: UnitTemplateComponent[]; // Curriculum Overview components
    textboxes: UnitTemplateTextboxComponent[]; // Textbox components
    activities: UnitTemplateComponent[]; // course builder components
};
