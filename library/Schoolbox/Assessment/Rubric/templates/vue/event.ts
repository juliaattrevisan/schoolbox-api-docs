import type { RubricGrid, RubricGridLevel } from "./types";

export const RUBRIC_GRID_IMPORTED = "RUBRIC_GRID_IMPORTED";
export const RUBRIC_GRID_APPLY_RESULT_SET = "RUBRIC_GRID_APPLY_RESULT_SET";

interface RubricGridImportedEvent extends CustomEvent {
    detail: {
        rubricGrid: RubricGrid;
    };
}

interface RubricGridApplyResultSetEvent extends CustomEvent {
    detail: {
        options: RubricGridLevel["name"][];
    };
}

export interface RubricGridDocumentEventHandlerMap {
    [RUBRIC_GRID_IMPORTED]: RubricGridImportedEvent;
    [RUBRIC_GRID_APPLY_RESULT_SET]: RubricGridApplyResultSetEvent;
}
