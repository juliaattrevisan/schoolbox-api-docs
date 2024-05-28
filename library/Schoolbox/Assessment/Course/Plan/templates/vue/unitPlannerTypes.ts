import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";

export type Assessment = {
    id: number;
};

export type RubricCapabilitiesLocale = {
    singular: string;
    plural: string;
};

export type RubricCapability = {
    id: number;
    name: string;
    indicators: {
        id: number;
        name: string;
    }[];
};

export type TextboxContent = {
    id: number;
    body: string;
    inTemplate: boolean; // whether the component is part of the Unit template, or just a component added to the Unit home page
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

export type Unit = {
    id: number;
    title: string;
    homepage: string;
    folderId: number;
    activities: {
        _routes: ActivitiesListRoutes;
        items: Activity[];
    };
    curriculum: {
        selectedNodes: Node[]; // the individual nodes assigned to the folder
        nodes: Node[]; // the selectedNodes, displayed within their entire tree structure, containing all parent nodes
        components: CurriculumOverviewComponent[];
    };
    rubricCapabilities: RubricCapability[];
    textboxes: Record<string, TextboxContent>;
    extraTextboxes: number;
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
    curriculum: UnitTemplateComponent[]; // Curriculum Overview components
    textboxes: UnitTemplateTextboxComponent[]; // Textbox components
};
