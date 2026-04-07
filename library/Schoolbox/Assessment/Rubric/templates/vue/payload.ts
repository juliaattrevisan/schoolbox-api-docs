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
    level: { id: number; name: string }[];
    group: { id: number | ""; name: string; maxValue: number | null }[];
    criterion: Record<
        number, // indicatorId
        {
            id: number;
            groupId: number;
            name: string;
            on?: 1;
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
    const gridData: RubricGridFormPayload = {
        level: grid.levels.map((level) => ({
            id: level.id,
            name: level.name,
        })),
        group: grid.capabilities.map((capability) => ({
            id: capability.id >= 0 ? capability.id : "", // the backend expects empty IDs for new capabilities (I think)
            name: capability.name,
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
        for (const indicator of capability.indicators) {
            gridData.criterion[indicator.id] = {
                id: indicator.id,
                groupId: capability.id,
                name: indicator.name,
                on: indicator.hasUsage ? 1 : undefined,
            };

            gridData.descriptor[indicator.id] = indicator.descriptors.map((descriptor) => ({
                id: descriptor.id >= 0 ? descriptor.id : "", // the backend expects empty IDs for new descriptors (I think)
                text: descriptor.description.trim(),
                points: (descriptor.maxValue ?? "").toString(),
            }));
        }
    }

    return gridData;
}
