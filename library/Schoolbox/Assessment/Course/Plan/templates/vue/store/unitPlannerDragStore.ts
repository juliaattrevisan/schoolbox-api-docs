import type { Ref } from "vue";
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import { Unit } from "../unitPlannerTypes";
import { useUnitPlannerStore } from "./unitPlannerStore";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

export const useUnitPlannerDragStore = defineStore("unitPlannerDragStore", () => {
    const unitPlannerStore = useUnitPlannerStore();
    const dragUnitHeight = 80; // The height of a unit when it is being dragged, in px

    // Allow the refs to be passed in instead of creating and monitoring two
    let unitPlannerContainerEl: Ref<HTMLElement>;
    let headerRowHeight: Ref<number>;
    let unitPlannerContainerScrollTop: Ref<number>;
    const routeFolderSequence = window.Vue.ref<string | null>(null);

    /**
     * store any passed through properties
     * @param { string|null } $routeFolderSequence - the route to use when resequencing, if the user has access to do so
     * @param { Ref<HTMLElement> } $unitPlannerContainerEl - the unit planner scrollable container element
     * @param { Ref<number> } $headerRowHeight - the height of the header row
     * @param { Ref<number> } $unitPlannerContainerScrollTop - the scroll position of the unit planner container
     */
    function init(
        $routeFolderSequence: string | null,
        $unitPlannerContainerEl: Ref<HTMLElement>,
        $headerRowHeight: Ref<number>,
        $unitPlannerContainerScrollTop: Ref<number>,
    ) {
        routeFolderSequence.value = $routeFolderSequence;
        unitPlannerContainerEl = $unitPlannerContainerEl;
        headerRowHeight = $headerRowHeight;
        unitPlannerContainerScrollTop = $unitPlannerContainerScrollTop;
    }

    const isDragging = window.Vue.ref(false);
    const dragCurrentUnit = window.Vue.ref<Unit | null>(null);
    const dragCurrentPointerY = window.Vue.ref<number | null>(null);
    const dragInitial = window.Vue.ref<{
        y: number;
        offsetY: number;
    } | null>(null);

    // Allow components to react to changes to the drag unit the tick after it actually changes
    // This is useful for example with the height transition of the drag placeholder where we need to initialise it with
    // the unexpanded height, and then set it to the drag height, otherwise it'll skip the transition.
    const dragCurrentUnitNextTick = window.Vue.ref<Unit | null>(null);
    window.Vue.watch(dragCurrentUnit, (value) => {
        setTimeout(() => {
            dragCurrentUnitNextTick.value = value;
        }, 0);
    });

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
            unitPlannerContainerEl.value.clientHeight - dragUnitHeight - headerRowHeight.value,
            unitPlannerStore.orderedUnits.reduce((acc, unit) => {
                if (unit.id === dragCurrentUnit.value?.id) {
                    return acc + dragUnitHeight;
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
        if (dragCurrentUnit.value === null || dragInitial.value === null) {
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
            if (unit.id === dragCurrentUnit.value?.id) {
                currentUnitHeight = dragUnitHeight;
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
        const dragUnitIndex = unitPlannerStore.orderedUnits.findIndex((u) => u.id === dragCurrentUnit.value?.id);
        const [draggedUnit] = newOrderedUnits.splice(dragUnitIndex, 1);
        newOrderedUnits.splice(adjustedDragIndex, 0, draggedUnit);

        return newOrderedUnits;
    });

    /**
     * Save the sequence of the units to the database
     */
    async function saveUnitSequence() {
        if (!routeFolderSequence.value) {
            return; // User does not have access to resequence
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        unitPlannerStore.toastEl!.activate("Saving...");
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

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            unitPlannerStore.toastEl!.timeout("Save successful", true, 2000);
        } catch (error) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            unitPlannerStore.toastEl!.timeout(error, false, 2000);
        }
    }

    /**
     * Resequences and saves the units
     * @param { number[] } unitIDs - the ids of the units to resequence
     */
    function resequenceUnits(unitIDs: Unit["id"][]) {
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
     * @param { Draggable } draggable - the draggable instance
     * @returns { void }
     */
    function onDragMove(draggable: Draggable) {
        dragCurrentPointerY.value = draggable.pointerY;
    }

    /**
     * When the user starts dragging a unit
     * @param { Draggable } draggable - the draggable instance
     * @param { Unit } unit - the unit to start dragging
     * @returns { void }
     */
    function onDragStart(draggable: Draggable, unit: Unit) {
        const pointerEvent = draggable.pointerEvent as PointerEvent;
        const pointerTarget = pointerEvent.target as HTMLElement;

        dragCurrentPointerY.value = draggable.pointerY;
        dragCurrentUnit.value = unit;
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
     * @param { Draggable } draggable - the draggable instance
     * @returns { void }
     */
    function onDragEnd(draggable: Draggable) {
        if (dragInitial.value !== null) {
            // Move the drag handle back to its original position
            gsap.to(draggable.target, {
                y: dragInitial.value.y,
                duration: 0,
            });
        }

        resequenceUnits(dragOrderedUnits.value.map((u) => u.id));

        isDragging.value = false;
        dragCurrentUnit.value = null;
        dragCurrentPointerY.value = null;
        dragInitial.value = null;
    }

    return {
        init,
        dragUnitHeight,
        routeFolderSequence,
        isDragging,
        dragCurrentUnit,
        dragCurrentUnitNextTick,
        dragPlaceholderY,
        dragOrderedUnits,
        onDragStart,
        onDragMove,
        onDragEnd,
    };
});
