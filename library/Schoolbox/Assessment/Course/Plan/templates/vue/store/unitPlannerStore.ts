import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { Column } from "../unitPlannerConfig";
import { Unit } from "../unitPlannerTypes";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const unexpandedUnitHeight = 300; // The default unexpanded height of a unit, in px
    const dragUnitHeight = 80; // The height of a unit when it is being dragged, in px

    const isDragging = window.Vue.ref(false);

    const units = window.Vue.ref<Unit[]>([]);
    const routeFolderSequence = window.Vue.ref<string | null>(null);
    const toastEl = window.Vue.ref<typeof Toast>(); // so we don't need to pass it all around as a prop

    // Allow the refs to be passed in instead of creating and monitoring two
    let unitPlannerContainerEl: ReturnType<typeof window.Vue.ref<HTMLElement>>;
    let headerRowHeight: ReturnType<typeof window.Vue.computed<number>>;
    let unitPlannerContainerScrollTop: ReturnType<typeof window.Vue.ref<number>>;

    const expandedUnitIDs = window.Vue.ref<Unit["id"][]>([]);
    const expandedUnits = window.Vue.computed(() => {
        return isDragging.value ? [] : expandedUnitIDs.value;
    });

    const orderedUnits = window.Vue.computed<Unit[]>(() => {
        return units.value.sort((a, b) => {
            // Sort by sequence, in descending order
            if (a.sequence > b.sequence) return -1;
            if (a.sequence < b.sequence) return 1;

            // Fall back to by ID, in ascending order
            return a.id - b.id;
        });
    });

    const unitContentHeights = window.Vue.ref<Record<number, Record<string, number>>>({});
    /**
     * As content is loaded, we need to keep track of the height so we can correctly
     * size other cells from that unit when it is expanded.
     * @param { Unit } unit - the unit to set the content height for
     * @param { Column } column - the column to set the content height for
     * @param { number } height - the height to set
     */
    function setUnitContentHeight(unit: Unit, column: Column, height: number) {
        if (unitContentHeights.value[unit.id] === undefined) {
            unitContentHeights.value[unit.id] = {};
        }

        // Prevent overwriting the height if it's already been set
        if (unitContentHeights.value[unit.id][column.id] === undefined) {
            unitContentHeights.value[unit.id][column.id] = height;
        }
    }

    const dragCurrentUnit = window.Vue.ref<Unit | null>(null);

    // Allow components to react to changes to the drag unit the tick after it actually changes
    // This is useful for example with the height transition of the drag placeholder where we need to initialise it with
    // the unexpanded height, and then set it to the drag height, otherwise it'll skip the transition.
    const dragCurrentUnitNextTick = window.Vue.ref<Unit | null>(null);
    window.Vue.watch(dragCurrentUnit, (value) => {
        setTimeout(() => {
            dragCurrentUnitNextTick.value = value;
        }, 0);
    });

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
            unitPlannerContainerEl.value.clientHeight - dragUnitHeight - headerRowHeight.value,
            orderedUnits.value.reduce((acc, unit) => {
                if (unit.id === dragCurrentUnit.value?.id) {
                    return acc + dragUnitHeight;
                }

                if (expandedUnitIDs.value.includes(unit.id)) {
                    const unitHeights = unitContentHeights.value[unit.id] || {};
                    return acc + Math.max(...Object.values(unitHeights), unexpandedUnitHeight);
                }

                return acc + unexpandedUnitHeight;
            }, 0),
        );
        return Math.max(minDragHeight, Math.min(targetHeight, maxDragHeight));
    });

    // Reorder the selected unit in the list based on the drag position
    const dragOrderedUnits = window.Vue.computed(() => {
        if (dragCurrentUnit.value === null || dragInitial.value === null) {
            return orderedUnits.value;
        }

        // Use reduce to find the index where the unit should be inserted
        const initialReduceVals = { cumulativeHeight: 0, foundDragIndex: 0, found: false };
        const reduceResult = orderedUnits.value.reduce((acc, unit, index) => {
            if (acc.found) return acc; // Skip further processing if index is found

            // The unit height is:
            // - unexpandedUnitHeight if it's not the unit being dragged and it's not expanded
            // - dragUnitHeight if it's the unit being dragged
            // - the maximum of the content heights if it's expanded and not being dragged

            let currentUnitHeight = unexpandedUnitHeight;
            if (unit.id === dragCurrentUnit.value?.id) {
                currentUnitHeight = dragUnitHeight;
            } else if (expandedUnitIDs.value.includes(unit.id)) {
                const unitHeights = unitContentHeights.value[unit.id] || {};
                currentUnitHeight = Math.max(...Object.values(unitHeights), unexpandedUnitHeight);
            }

            const newCumulativeHeight = acc.cumulativeHeight + currentUnitHeight;

            // If the new cumulative height is greater than the top of the drag placeholder (just above the mouse position), then we've found the index
            if (newCumulativeHeight >= dragPlaceholderY.value && !acc.found) {
                return { ...acc, foundDragIndex: index, found: true };
            }

            // Otherwise, continue to the next unit
            return { ...acc, cumulativeHeight: newCumulativeHeight, foundDragIndex: index + 1 };
        }, initialReduceVals);

        const adjustedDragIndex = Math.min(reduceResult.foundDragIndex, orderedUnits.value.length - 1);

        // Reorder the units based on the new index
        const newOrderedUnits = [...orderedUnits.value];
        const dragUnitIndex = orderedUnits.value.findIndex((u) => u.id === dragCurrentUnit.value?.id);
        const [draggedUnit] = newOrderedUnits.splice(dragUnitIndex, 1);
        newOrderedUnits.splice(adjustedDragIndex, 0, draggedUnit);

        return newOrderedUnits;
    });

    /**
     * store any passed through properties
     * @param { Unit[] } $units - the units to store
     * @param { string|null } $routeFolderSequence - the route to use when resequencing, if the user has access to do so
     * @param { ReturnType<typeof window.Vue.ref<HTMLElement>> } $unitPlannerContainerEl - the unit planner scrollable container element
     * @param { ReturnType<typeof window.Vue.computed<number>> } $headerRowHeight - the height of the header row
     * @param { ReturnType<typeof window.Vue.ref<number>> } $unitPlannerContainerScrollTop - the scroll position of the unit planner container
     * @param { typeof Toast } $toastEl - the toast element
     */
    function init(
        $units: Unit[],
        $routeFolderSequence: string | null,
        $unitPlannerContainerEl: typeof unitPlannerContainerEl,
        $headerRowHeight: typeof headerRowHeight,
        $unitPlannerContainerScrollTop: typeof unitPlannerContainerScrollTop,
        $toastEl: typeof Toast,
    ) {
        units.value = $units;
        routeFolderSequence.value = $routeFolderSequence;
        unitPlannerContainerEl = $unitPlannerContainerEl;
        headerRowHeight = $headerRowHeight;
        unitPlannerContainerScrollTop = $unitPlannerContainerScrollTop;
        toastEl.value = $toastEl.value;
    }

    /**
     * toggle the expanded state of a unit
     * @param { number } unitId - the id of the unit to toggle
     */
    function toggleExpanded(unitId: Unit["id"]) {
        if (expandedUnitIDs.value.includes(unitId)) {
            expandedUnitIDs.value = expandedUnitIDs.value.filter((id) => id !== unitId);
        } else {
            expandedUnitIDs.value.push(unitId);
        }
    }

    /**
     * Save the sequence of the units to the database
     */
    async function saveUnitSequence() {
        if (!routeFolderSequence.value) {
            return; // User does not have access to resequence
        }

        toastEl.value.activate("Saving...");
        try {
            const currentSequence = orderedUnits.value.map((u) => u.folderId);
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
                let responseText = await response.text();

                // If the response is JSON, then parse it correctly for the response text
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    const responseJSON = JSON.parse(responseText) as { title: string; type: string; status: number };
                    responseText = responseJSON.title;
                }

                throw new Error(responseText);
            }

            toastEl.value.timeout("Save successful", true, 2000);
        } catch (error) {
            toastEl.value.timeout(error, false, 2000);
        }
    }

    /**
     * Resequences and saves the units
     * @param { number[] } unitIDs - the ids of the units to resequence
     */
    function resequenceUnits(unitIDs: Unit["id"][]) {
        // As the sequence for units is largest to smallest, we need to reverse the array
        unitIDs.reverse().forEach((id, index) => {
            const unit = units.value.find((u) => u.id === id);
            if (unit) {
                unit.sequence = index + 1;
            }
        });

        saveUnitSequence().catch(() => {
            // noop
        });
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
        isDragging,
        dragPlaceholderY,
        dragOrderedUnits,
        dragCurrentUnit,
        dragCurrentUnitNextTick,
        onDragStart,
        onDragMove,
        onDragEnd,
        dragUnitHeight,
        unexpandedUnitHeight,
        unitContentHeights,
        setUnitContentHeight,
        units,
        routeFolderSequence,
        toastEl,
        expandedUnits,
        orderedUnits,
        toggleExpanded,
        resequenceUnits,
    };
});
