import type { RubricMarkedGrid } from "./types";

/**
 * The form data used when saving the editable rubric grid
 * This is generally inside a .rb.[rubricId] property when sent
 * @see /library/Schoolbox/Assessment/Rubric/Grid/Factory.php $rubricData
 */
type RubricMarkedGridFormPayload = {
    humanReadableMark: string | null;
    normalisedMark: number | null;
    capabilities: Record<
        number, // capabilityId
        {
            humanReadableMark: string | null;
            value: string | null;
            scaledMark: number | null;
        }
    >;
    indicators: Record<
        number, // indicatorId
        {
            value: number | null;
            descriptorId: number | null;
        }
    >;
};

/**
 * Convert a RubricMarkedGrid into a RubricMarkedGridFormPayload
 * @param grid - the RubricMarkedGrid to convert
 * @returns the converted payload
 */
export function translateMarkedGridToFormPayload(grid: RubricMarkedGrid): RubricMarkedGridFormPayload {
    const markedGridData: RubricMarkedGridFormPayload = {
        humanReadableMark: grid.humanReadableMark,
        normalisedMark: grid.normalisedMark,
        capabilities: {},
        indicators: {},
    };

    // TODO: here's ALL the data, but it's likely that you don't actually need some of these fields. revise later
    for (const capability of grid.capabilities) {
        markedGridData.capabilities[capability.id] = {
            humanReadableMark: capability.humanReadableMark,
            value: capability.value,
            scaledMark: capability.scaledMark,
        };

        for (const indicator of capability.indicators) {
            markedGridData.indicators[indicator.id] = {
                value: indicator.value,
                descriptorId: indicator.descriptorId,
            };
        }
    }

    return markedGridData;
}
