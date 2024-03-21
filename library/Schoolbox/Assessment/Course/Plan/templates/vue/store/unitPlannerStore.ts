import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { Column } from "../unitPlannerConfig";
import { Unit, UnitPlannerLocale } from "../unitPlannerTypes";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const unexpandedUnitHeight = 300; // The default unexpanded height of a unit, in px

    const units = window.Vue.ref<Unit[]>([]);
    const locale = window.Vue.ref<UnitPlannerLocale>();
    const toastEl = window.Vue.ref<typeof Toast>(); // so we don't need to pass it all around as a prop

    /**
     * store any passed through properties
     * @param { Unit[] } $units - the units to store
     * @param { UnitPlannerLocale } $locale - the locales relating to the unit planner
     * @param { typeof Toast } $toastEl - the toast element
     */
    function init($units: Unit[], $locale: UnitPlannerLocale, $toastEl: typeof Toast) {
        units.value = $units;
        locale.value = $locale;
        toastEl.value = $toastEl;
    }

    const orderedUnits = window.Vue.computed<Unit[]>(() => {
        return units.value.sort((a, b) => {
            // Sort by sequence, in descending order
            if (a.sequence > b.sequence) return -1;
            if (a.sequence < b.sequence) return 1;

            // Fall back to by ID, in ascending order
            return a.id - b.id;
        });
    });

    // Units that are focused, e.g. when the user is editing a textbox (focused on the editor)
    const focusedUnitCells = window.Vue.ref<Record<number, string[]>>({});
    // Units that have been expanded by clicking "view more"
    const viewMoreUnitIDs = window.Vue.ref<Unit["id"][]>([]);
    // The final list of units that should be expanded
    const expandedUnits = window.Vue.computed(() => {
        const focusedUnitIDs = Object.keys(focusedUnitCells.value);
        return [...new Set([...focusedUnitIDs.map(Number), ...viewMoreUnitIDs.value])];
    });

    /**
     * Updates the focus state of a unit+column
     * @param { number } unitId - the unit to update the focus state for
     * @param { Column } column - the column to update the focus state for
     * @param { boolean } isFocused - whether the column is focused
     */
    function updateFocusedUnitCell(unitId: number, column: Column, isFocused: boolean) {
        if (isFocused) {
            if (focusedUnitCells.value[unitId] === undefined) {
                focusedUnitCells.value[unitId] = [];
            }

            focusedUnitCells.value[unitId].push(column.id);
        } else {
            if (focusedUnitCells.value[unitId] === undefined) {
                return;
            }

            focusedUnitCells.value[unitId] = focusedUnitCells.value[unitId].filter((c) => c !== column.id);
            if (focusedUnitCells.value[unitId].length === 0) {
                delete focusedUnitCells.value[unitId];
            }
        }
    }

    /**
     * toggle the view more (expanded) state of a unit
     * @param { number } unitId - the id of the unit to toggle
     */
    function toggleViewMore(unitId: Unit["id"]) {
        if (viewMoreUnitIDs.value.includes(unitId)) {
            viewMoreUnitIDs.value = viewMoreUnitIDs.value.filter((id) => id !== unitId);
        } else {
            viewMoreUnitIDs.value.push(unitId);
        }
    }

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

        unitContentHeights.value[unit.id][column.id] = Math.max(height, unexpandedUnitHeight);
    }

    return {
        init,
        unexpandedUnitHeight,
        units,
        locale,
        toastEl,
        orderedUnits,
        focusedUnitCells,
        expandedUnits,
        updateFocusedUnitCell,
        toggleViewMore,
        unitContentHeights,
        setUnitContentHeight,
    };
});
