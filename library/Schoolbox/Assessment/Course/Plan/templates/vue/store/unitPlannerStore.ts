import { defineStore } from "pinia";
import { Unit } from "../unitPlannerTypes";

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const expandedUnits = window.Vue.ref<Unit["id"][]>([]);
    const units = window.Vue.ref<Unit[]>([]);
    const routeFolderSequence = window.Vue.ref<string | null>(null);

    /**
     * store any passed through properties
     * @param { Unit[] } $units - the units to store
     * @param { string|null } $routeFolderSequence - the route to use when resequencing, if the user has access to do so
     */
    function init($units: Unit[], $routeFolderSequence: string | null) {
        units.value = $units;
        routeFolderSequence.value = $routeFolderSequence;
    }

    /**
     * toggle the expanded state of a unit
     * @param { number } unitId - the id of the unit to toggle
     */
    function toggleExpanded(unitId: Unit["id"]) {
        if (expandedUnits.value.includes(unitId)) {
            expandedUnits.value = expandedUnits.value.filter((id) => id !== unitId);
        } else {
            expandedUnits.value.push(unitId);
        }
    }

    /**
     * Resequences the units
     * @param { number[] } unitIDs - the ids of the units to resequence
     */
    function resequenceUnits(unitIDs: Unit["id"][]) {
        const newUnits = units.value.slice();
        unitIDs.forEach((id, index) => {
            const unit = newUnits.find((u) => u.id === id);
            if (unit) {
                unit.sequence = index;
            }
        });
        units.value = newUnits;
    }

    return {
        init,
        expandedUnits,
        routeFolderSequence,
        toggleExpanded,
        units,
        resequenceUnits,
    };
});
