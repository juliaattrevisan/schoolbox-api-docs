import { defineStore } from "pinia";
import { Unit } from "../unitPlannerTypes";

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const expandedUnits = window.Vue.ref<Unit["id"][]>([]);
    const units = window.Vue.ref<Unit[]>([]);

    /**
     * store any passed through properties
     * @param { Unit[] } $units - the units to store
     */
    function init($units: Unit[]) {
        units.value = $units;
    }

    /**
     * toggle the expanded state of a unit
     * @param { number } unitId - the id of the unit to toggle
     */
    function toggleExpanded(unitId: number) {
        if (expandedUnits.value.includes(unitId)) {
            expandedUnits.value = expandedUnits.value.filter((id) => id !== unitId);
        } else {
            expandedUnits.value.push(unitId);
        }
    }

    return {
        init,
        expandedUnits,
        toggleExpanded,
        units,
    };
});
