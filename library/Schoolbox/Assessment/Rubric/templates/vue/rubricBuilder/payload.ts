import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { RubricGrid, RubricGridCapability, RubricGridIndicator } from "./types";

export type RubricGridCreateCapabilityResponse = Omit<RubricGridCapability, "id"> & {
    id: number | null; // since it's a new capability, it won't have an ID yet
};

export type RubricGridCreateIndicatorResponse = {
    /**
     * The ID of the capability this indicator belongs to.
     * This becomes relevant if the capability was new, since the frontend wouldn't know of
     * the real ID yet until it gets created when an indicator is created under it here.
     */
    capabilityId: number;
    indicator: RubricGridIndicator;
};

/**
 * The form data used when saving the editable rubric grid
 * This is generally inside a .rb.[rubricId] property when sent
 * @see /library/Schoolbox/Assessment/Rubric/Grid/Factory.php $rubricData
 */
type RubricGridFormPayload = {
    level: { id: number | ""; name: string }[];
    group: { id: number | ""; name: string; maxValue: number | null }[];
    criterion: Record<
        number, // indicatorId
        {
            id: number | "";
            groupId: number;
            name: string;
            on?: 1;
            sequence: number;
        }
    >;
    descriptor: Record<
        number, // indicatorId
        {
            id: number | "";
            text?: string;
            points?: string;
        }[]
    >;
    title: string;
    weight: number | null;
    useMarkRange: boolean;
    capabilityMarkType: MarkTypeSlug;
    capabilityGradeGroup: number | null;
};

/**
 * Convert a RubricGrid into a RubricGridFormPayload
 * @param grid - the RubricGrid to convert
 * @returns the converted payload
 */
export function translateGridToFormPayload(grid: RubricGrid): RubricGridFormPayload {
    // TODO: allow backend to accept negative IDs?

    const gridData: RubricGridFormPayload = {
        level: grid.levels.map((level) => ({
            id: level.id >= 0 ? level.id : "", // the backend expects empty IDs for new levels
            name: level.name,
        })),
        group: grid.capabilities.map((capability) => ({
            id: capability.id >= 0 ? capability.id : "", // the backend expects empty IDs for new capabilities
            /* a whitespace-only capability is considered deliberate
             * in order to get a blank cell, so it must not be trimmed.
             * For normal text, still trim to avoid accidental whitespace.
             */
            name: capability.name.trim() === "" ? capability.name : capability.name.trim(),
            maxValue: capability.maxValue,
        })),
        criterion: {},
        descriptor: {},
        title: grid.title,
        weight: grid.weight,
        useMarkRange: grid.useMarkRange,
        capabilityMarkType: grid.capabilityMarkType ?? MarkTypeSlug.NONE,
        capabilityGradeGroup: grid.capabilityGradeGroup,
    };

    for (const capability of grid.capabilities) {
        // Indicator sequencing
        let i = 1;

        for (const indicator of capability.indicators) {
            gridData.criterion[indicator.id] = {
                id: indicator.id >= 0 ? indicator.id : "", // the backend expects empty IDs for new indicators
                groupId: capability.id,
                /* a whitespace-only indicator is considered deliberate
                 * in order to get a blank cell, so it must not be trimmed.
                 * For normal text, still trim to avoid accidental whitespace.
                 */
                name: indicator.name.trim() === "" ? indicator.name : indicator.name.trim(),
                on: indicator.hasUsage ? 1 : undefined,
                sequence: i,
            };

            gridData.descriptor[indicator.id] = indicator.descriptors.map((descriptor) => ({
                id: descriptor.id >= 0 ? descriptor.id : "", // the backend expects empty IDs for new descriptors
                /* a whitespace-only descriptor is considered deliberate
                 * in order to get a blank cell, so it must not be trimmed.
                 * For normal text, still trim to avoid accidental whitespace.
                 */
                text: descriptor.description.trim() === "" ? descriptor.description : descriptor.description.trim(),
                points: (descriptor.maxValue ?? "").toString(),
            }));

            // ...because we've never seen javascript concat numbers and strings before, but ok
            i += 1;
        }
    }

    return gridData;
}
