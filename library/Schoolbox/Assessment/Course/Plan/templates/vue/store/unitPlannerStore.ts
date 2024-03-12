import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { Unit } from "../unitPlannerTypes";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

const defaultUnexpandedUnitHeight = 300; // The default unexpanded height of a unit, in px
const dragUnitHeight = 80; // The height of a unit when it is being dragged, in px

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const isDragging = window.Vue.ref(false);
    const isDraggingNextTick = window.Vue.ref(false);
    window.Vue.watch(isDragging, (newValue) => {
        // Wait until the next tick to set isDraggingNextTick to true
        // Ensures CSS transitions are not interrupted
        // Note: Using Vue.nextTick() here does not work as expected
        if (newValue) {
            setTimeout(() => {
                isDraggingNextTick.value = true;
            }, 0);
        } else {
            isDraggingNextTick.value = false;
        }
    });

    const unexpandedUnitHeight = window.Vue.computed(() => {
        return isDraggingNextTick.value ? dragUnitHeight : defaultUnexpandedUnitHeight;
    });

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

    const dragCurrentUnit = window.Vue.ref<Unit | null>(null);
    const dragCurrentPointerY = window.Vue.ref<number | null>(null);
    const dragInitial = window.Vue.ref<{
        y: number;
        deltaY: number;
        offsetY: number;
        pointerY: number;
        scrollTop: number;
    } | null>(null);

    const dragPlaceholderY = window.Vue.computed(() => {
        if (
            dragCurrentPointerY.value === null ||
            dragInitial.value === null ||
            unitPlannerContainerEl.value === undefined ||
            unitPlannerContainerScrollTop.value === undefined
        ) {
            return -1;
        }

        return Math.max(
            0,
            Math.min(
                dragCurrentPointerY.value -
                    unitPlannerContainerEl.value.getBoundingClientRect().top +
                    unitPlannerContainerScrollTop.value -
                    headerRowHeight.value -
                    dragInitial.value.offsetY,
                dragUnitHeight * units.value.length,
            ),
        );
    });

    // Reorder the selected unit in the list based on the drag position
    const dragOrderedUnits = window.Vue.computed(() => {
        if (dragCurrentUnit.value === null || dragInitial.value === null) {
            return units.value;
        }

        const dragIndex = Math.floor(Math.max(0, dragPlaceholderY.value) / dragUnitHeight);
        const dragUnitIndex = units.value.findIndex((u) => u.id === dragCurrentUnit.value!.id);

        if (dragIndex === dragUnitIndex) {
            return units.value;
        }

        const newOrderedUnits = [...units.value];
        newOrderedUnits.splice(dragUnitIndex, 1);
        newOrderedUnits.splice(dragIndex, 0, dragCurrentUnit.value);

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
            y: draggable.y,
            deltaY: draggable.deltaY,
            offsetY:
                pointerEvent.offsetY +
                pointerTarget.getBoundingClientRect().top -
                pointerTarget.parentElement!.getBoundingClientRect().top,
            pointerY: draggable.pointerY,
            scrollTop: unitPlannerContainerEl.value?.scrollTop || 0,
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
        onDragStart,
        onDragMove,
        onDragEnd,
        unexpandedUnitHeight,
        units,
        routeFolderSequence,
        toastEl,
        expandedUnits,
        orderedUnits,
        toggleExpanded,
        resequenceUnits,
    };
});
