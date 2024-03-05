import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { Unit } from "../unitPlannerTypes";

const defaultUnexpandedUnitHeight = 300;
/* The height of a unit when it is being dragged, in px */
const dragUnitHeight = 80;

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const isDragging = window.Vue.ref(false);
    const isDraggingNextTick = window.Vue.ref(false);
    window.Vue.watch(isDragging, (newValue) => {
        if (newValue) {
            isDraggingNextTick.value = true;
        } else {
            // Wait until the next tick to set isDraggingNextTick to false
            // Ensures CSS transitions are not interrupted
            //
            // Note: Using Vue.nextTick() here does not work as expected
            setTimeout(() => {
                isDraggingNextTick.value = false;
            }, 0);
        }
    });

    const unexpandedUnitHeight = window.Vue.computed(() => {
        return isDraggingNextTick.value ? dragUnitHeight : defaultUnexpandedUnitHeight;
    });
    const unexpandedUnitHeightStyle = window.Vue.computed(() => `${unexpandedUnitHeight.value}px`);

    const units = window.Vue.ref<Unit[]>([]);
    const routeFolderSequence = window.Vue.ref<string | null>(null);
    const toastEl = window.Vue.ref<typeof Toast>(); // so we don't need to pass it all around as a prop

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

    /**
     * store any passed through properties
     * @param { Unit[] } $units - the units to store
     * @param { string|null } $routeFolderSequence - the route to use when resequencing, if the user has access to do so
     * @param { typeof Toast } $toastEl - the toast element
     */
    function init($units: Unit[], $routeFolderSequence: string | null, $toastEl: typeof Toast) {
        units.value = $units;
        routeFolderSequence.value = $routeFolderSequence;
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
     * Resequences the units
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

    return {
        init,
        isDragging,
        unexpandedUnitHeight,
        unexpandedUnitHeightStyle,
        units,
        routeFolderSequence,
        toastEl,
        expandedUnits,
        expandedUnitIDs,
        orderedUnits,
        toggleExpanded,
        resequenceUnits,
    };
});
