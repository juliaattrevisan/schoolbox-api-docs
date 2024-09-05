import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import { Column } from "../unitPlannerConfig";
import { CurriculumOverviewComponent, Unit, UnitTemplate } from "../unitPlannerTypes";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

export const useUnitPlannerStore = defineStore("unitPlannerStore", () => {
    const unexpandedUnitHeight: number = 300; // The default unexpanded height of a unit, in px
    const dragUnitHeight: number = 80; // The height of a unit when it is being dragged, in px

    const courseId = window.Vue.ref(0);
    const units = window.Vue.ref<Unit[]>([]);
    const unitCount = window.Vue.computed(() => units.value.length); // The number of units in the course
    const template = window.Vue.ref<UnitTemplate | null>(null); // the course framework template that units are linked to

    /**
     * store any passed through properties
     * @param $courseId - the course ID
     * @param $units - the units to store
     * @param $template - the unit template
     */
    function init($courseId: number, $units: Unit[], $template: UnitTemplate): void {
        courseId.value = $courseId;
        units.value = $units;
        template.value = $template;
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

    // The unit that is currently being dragged
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

    // Unit Cells that are focused, e.g. when the user is editing a textbox (focused on the editor), dragging an activity, etc
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
     * @param unitId - the unit to update the focus state for
     * @param column - the column to update the focus state for
     * @param isFocused - whether the column is focused
     */
    function updateFocusedUnitCell(unitId: number, column: Column, isFocused: boolean): void {
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
     * @param unitId - the id of the unit to toggle
     */
    function toggleViewMore(unitId: Unit["id"]): void {
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
     * @param unit - the unit to set the content height for
     * @param column - the column to set the content height for
     * @param height - the height to set
     */
    function setUnitContentHeight(unit: Unit, column: Column, height: number): void {
        if (unitContentHeights.value[unit.id] === undefined) {
            unitContentHeights.value[unit.id] = {};
        }

        unitContentHeights.value[unit.id][column.id] = Math.max(height, unexpandedUnitHeight);
    }

    /**
     * The minimum height at which each cell should be rendered, based on:
     * - The height of the content in the cell
     * - The unexpanded height of the unit
     * - Whether the cell is focused
     * - Whether the unit row is in a "View More" state
     * @returns { Record<number, Record<string, number>> } - a record of unit ID to a record of column ID to minimum display height
     */
    const unitCellMinDisplayHeights = window.Vue.computed<Record<number, Record<string, number>>>(() => {
        const cellMinHeights: Record<number, Record<string, number>> = {};

        units.value.forEach((unit) => {
            const isDragging = dragCurrentUnitNextTick.value?.id === unit.id;
            const isViewMore = viewMoreUnitIDs.value.includes(unit.id);

            cellMinHeights[unit.id] = Object.fromEntries(
                Object.entries(unitContentHeights.value[unit.id] || {}).map(([columnId, contentHeight]) => {
                    if (isDragging) return [columnId, dragUnitHeight];
                    if (isViewMore) return [columnId, contentHeight];
                    if (focusedUnitCells.value[unit.id]?.includes(columnId)) return [columnId, contentHeight];
                    return [columnId, unexpandedUnitHeight];
                }),
            );
        });

        return cellMinHeights;
    });

    /**
     * The display height of each unit row, based on the maximum of the minimum display heights of each cell inside
     * @returns { Record<number, number> } - a record of unit ID to display height
     */
    const unitRowDisplayHeights = window.Vue.computed<Record<number, number>>(() => {
        return Object.fromEntries(
            Object.entries(unitCellMinDisplayHeights.value).map(([unitId, cellHeights]) => {
                const maxCellHeight = Math.max(...Object.values(cellHeights));

                if (maxCellHeight < 0) {
                    return [unitId, unexpandedUnitHeight];
                }
                return [unitId, maxCellHeight];
            }),
        );
    });

    /**
     * Add a newly created curriculum component to a unit
     * @param unitId - to find the unit to add the component to
     * @param component - the component to add
     */
    function addCurriculumComponent(unitId: number, component: CurriculumOverviewComponent): void {
        const matchedUnit = units.value.find((unit) => unit.id === unitId);
        if (!matchedUnit) {
            return;
        }
        matchedUnit.curriculum.components.push(component);
    }

    return {
        init,
        unexpandedUnitHeight,
        dragUnitHeight,
        courseId,
        units,
        unitCount,
        template,
        dragCurrentUnit,
        dragCurrentUnitNextTick,
        orderedUnits,
        focusedUnitCells,
        expandedUnits,
        updateFocusedUnitCell,
        toggleViewMore,
        unitContentHeights,
        setUnitContentHeight,
        unitRowDisplayHeights,
        addCurriculumComponent,
    };
});
