import type { RubricGrid } from "./types";

export const RUBRIC_GRID_IMPORTED = "RUBRIC_GRID_IMPORTED";

export interface RubricGridImportedEvent extends CustomEvent {
    detail: {
        rubricGrid: RubricGrid;
    };
}

export interface RubricGridDocumentEventHandlerMap {
    [RUBRIC_GRID_IMPORTED]: RubricGridImportedEvent;
}
