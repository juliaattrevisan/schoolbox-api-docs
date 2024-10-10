import type { Ref } from "vue";
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Unit } from "../unitPlannerTypes";
import { useUnitPlannerStore } from "./unitPlannerStore";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

export const useUnitPlannerDragStore = defineStore("unitPlannerDragStore", () => {
    const unitPlannerStore = useUnitPlannerStore();

    // Allow the refs to be passed in instead of creating and monitoring two
    let unitPlannerContainerEl: Ref<HTMLElement>;
    let headerRowHeight: Ref<number>;
    let unitPlannerContainerScrollTop: Ref<number>;
    const routeFolderSequence = window.Vue.ref<string | null>(null);

    /**
     * store any passed through properties
     * @param $routeFolderSequence - the route to use when resequencing, if the user has access to do so
     * @param $unitPlannerContainerEl - the unit planner scrollable container element
     * @param $headerRowHeight - the height of the header row
     * @param $unitPlannerContainerScrollTop - the scroll position of the unit planner container
     */
    function init(
        $routeFolderSequence: string | null,
        $unitPlannerContainerEl: Ref<HTMLElement>,
        $headerRowHeight: Ref<number>,
        $unitPlannerContainerScrollTop: Ref<number>,
    ): void {
        routeFolderSequence.value = $routeFolderSequence;
        unitPlannerContainerEl = $unitPlannerContainerEl;
        headerRowHeight = $headerRowHeight;
        unitPlannerContainerScrollTop = $unitPlannerContainerScrollTop;
    }

    const isDragging = window.Vue.ref(false);
    const dragCurrentPointerY = window.Vue.ref<number | null>(null);
    const dragInitial = window.Vue.ref<{
        y: number;
        offsetY: number;
    } | null>(null);

    const dragPlaceholderY = window.Vue.computed(() => {
        if (
            dragCurrentPointerY.value === null ||
            dragInitial.value === null ||
            unitPlannerContainerEl.value === undefined ||
            unitPlannerContainerScrollTop.value === undefined
        ) {
            // Not ready to calculate the placeholder position yet, either not dragging or not initialised yet
            return -1;
        }

        // The optimal placeholder position is:
        // - the current mouse pointer Y position
        // - minus the top of the unit planner container
        // - plus the current scroll position of the unit planner container
        // - minus the height of the header row (which is fixed)
        // - minus the initial offset of the drag (which is the difference between where the user grabbed the drag handle, and the top of the unit)
        const targetHeight =
            dragCurrentPointerY.value -
            unitPlannerContainerEl.value.getBoundingClientRect().top +
            unitPlannerContainerScrollTop.value -
            headerRowHeight.value -
            dragInitial.value.offsetY;

        // Clamp the target height to between:
        // - Min of 0 (right under the header row)
        // - Max of the largest of:
        //   - The height of the unit planner container (excluding header), minus 1 drag unit height (to allow for the drag placeholder to be below the last unit)
        //   - The cumulative height of all the non-dragged units (in case it's larger than the container and needs to scroll)
        //     Does not include the height of the dragged unit height, as we are based on the top of the placeholder
        const minDragHeight = 0;
        const maxDragHeight = Math.max(
            unitPlannerContainerEl.value.clientHeight - unitPlannerStore.dragUnitHeight - headerRowHeight.value,
            unitPlannerStore.orderedUnits.reduce((acc, unit) => {
                if (unit.id === unitPlannerStore.dragCurrentUnit?.id) {
                    return acc + unitPlannerStore.dragUnitHeight;
                }

                if (unitPlannerStore.expandedUnits.includes(unit.id)) {
                    const unitHeights = unitPlannerStore.unitContentHeights[unit.id] || {};
                    return acc + Math.max(...Object.values(unitHeights), unitPlannerStore.unexpandedUnitHeight);
                }

                return acc + unitPlannerStore.unexpandedUnitHeight;
            }, 0),
        );
        return Math.max(minDragHeight, Math.min(targetHeight, maxDragHeight));
    });

    // Reorder the selected unit in the list based on the drag position
    const dragOrderedUnits = window.Vue.computed(() => {
        if (unitPlannerStore.dragCurrentUnit === null || dragInitial.value === null) {
            return unitPlannerStore.orderedUnits;
        }

        // Use reduce to find the index where the unit should be inserted
        const initialReduceVals = { cumulativeHeight: 0, foundDragIndex: 0, found: false };
        const reduceResult = unitPlannerStore.orderedUnits.reduce((acc, unit, index) => {
            if (acc.found) return acc; // Skip further processing if index is found

            // The unit height is:
            // - unexpandedUnitHeight if it's not the unit being dragged and it's not expanded
            // - dragUnitHeight if it's the unit being dragged
            // - the maximum of the content heights if it's expanded and not being dragged

            let currentUnitHeight = unitPlannerStore.unexpandedUnitHeight;
            if (unit.id === unitPlannerStore.dragCurrentUnit?.id) {
                currentUnitHeight = unitPlannerStore.dragUnitHeight;
            } else if (unitPlannerStore.expandedUnits.includes(unit.id)) {
                const unitHeights = unitPlannerStore.unitContentHeights[unit.id] || {};
                currentUnitHeight = Math.max(...Object.values(unitHeights), unitPlannerStore.unexpandedUnitHeight);
            }

            const newCumulativeHeight = acc.cumulativeHeight + currentUnitHeight;

            // If the new cumulative height is greater than the top of the drag placeholder (just above the mouse position), then we've found the index
            if (newCumulativeHeight >= dragPlaceholderY.value && !acc.found) {
                return { ...acc, foundDragIndex: index, found: true };
            }

            // Otherwise, continue to the next unit
            return { ...acc, cumulativeHeight: newCumulativeHeight, foundDragIndex: index + 1 };
        }, initialReduceVals);

        const adjustedDragIndex = Math.min(reduceResult.foundDragIndex, unitPlannerStore.orderedUnits.length - 1);

        // Reorder the units based on the new index
        const newOrderedUnits = [...unitPlannerStore.orderedUnits];
        const dragUnitIndex = unitPlannerStore.orderedUnits.findIndex(
            (u) => u.id === unitPlannerStore.dragCurrentUnit?.id,
        );
        const [draggedUnit] = newOrderedUnits.splice(dragUnitIndex, 1);
        newOrderedUnits.splice(adjustedDragIndex, 0, draggedUnit);

        return newOrderedUnits;
    });

    /**
     * Save the sequence of the units to the database
     */
    async function saveUnitSequence(): Promise<void> {
        if (!routeFolderSequence.value) {
            return; // User does not have access to resequence
        }

        activate("Saving...");
        try {
            const currentSequence = unitPlannerStore.orderedUnits.map((u) => u.folderId);
            const response = await fetch(routeFolderSequence.value, {
                method: "POST",
                body: JSON.stringify({
                    sequence: currentSequence,
                }),
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
            });

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }

            timeout({ text: "Unit order saved successfully" });
        } catch (error) {
            timeout({ text: error as string, isSuccess: false });
        }
    }

    /**
     * Resequences and saves the units
     * @param unitIDs - the ids of the units to resequence
     */
    function resequenceUnits(unitIDs: Unit["id"][]): void {
        let hasChanged = false;

        // As the sequence for units is largest to smallest, we need to reverse the array
        unitIDs.reverse().forEach((id, index) => {
            const unit = unitPlannerStore.units.find((u) => u.id === id);
            if (unit && unit.sequence !== index + 1) {
                unit.sequence = index + 1;
                hasChanged = true;
            }
        });

        if (hasChanged) {
            saveUnitSequence().catch(() => {
                // noop
            });
        }
    }

    /**
     * While the user is dragging a unit
     * @param draggable - the draggable instance
     */
    function onDragMove(draggable: Draggable): void {
        dragCurrentPointerY.value = draggable.pointerY;
    }

    /**
     * When the user starts dragging a unit
     * @param draggable - the draggable instance
     * @param unit - the unit to start dragging
     */
    function onDragStart(draggable: Draggable, unit: Unit): void {
        const pointerEvent = draggable.pointerEvent as PointerEvent;
        const pointerTarget = pointerEvent.target as HTMLElement;

        dragCurrentPointerY.value = draggable.pointerY;
        unitPlannerStore.dragCurrentUnit = unit;
        dragInitial.value = {
            // The initial position of the drag handle
            y: draggable.y,
            // The difference between the pointer position and the top of the unit row
            offsetY:
                pointerEvent.offsetY +
                pointerTarget.getBoundingClientRect().top -
                pointerTarget.parentElement!.getBoundingClientRect().top,
        };

        isDragging.value = true;
    }

    /**
     * When the user stops dragging a unit
     * @param draggable - the draggable instance
     */
    function onDragEnd(draggable: Draggable): void {
        if (dragInitial.value !== null) {
            // Move the drag handle back to its original position
            gsap.to(draggable.target, {
                y: dragInitial.value.y,
                duration: 0,
            });
        }

        resequenceUnits(dragOrderedUnits.value.map((u) => u.id));

        isDragging.value = false;
        unitPlannerStore.dragCurrentUnit = null;
        dragCurrentPointerY.value = null;
        dragInitial.value = null;
    }

    return {
        init,
        routeFolderSequence,
        isDragging,
        dragPlaceholderY,
        dragOrderedUnits,
        onDragStart,
        onDragMove,
        onDragEnd,
    };
});
