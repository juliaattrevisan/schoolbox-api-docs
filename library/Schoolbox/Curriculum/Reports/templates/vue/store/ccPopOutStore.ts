/**
 * Manage the loading and state of the single cc-usage-pop-out
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import { defineStore } from "pinia";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

export const usePopOutStore = defineStore(`ccPopOut`, () => {
    const course = window.Vue.ref<Course | null>(null);
    const unit = window.Vue.ref<Unit | null>(null);
    const node = window.Vue.ref<CurriculumCoverageNode | null>(null);
    const button = window.Vue.ref<HTMLButtonElement | null>(null);

    /**
     * report has been triggered so set the active course and nodes
     * @param $course - the course to set as active
     * @param $unit - the unit to set as active
     * @param $node - the node to set as active
     * @param $button - the button that triggered the action
     */
    function setActive($course: Course, $unit: Unit, $node: CurriculumCoverageNode, $button: HTMLButtonElement): void {
        course.value = $course;
        unit.value = $unit;
        node.value = $node;
        button.value = $button;
    }

    return {
        button,
        setActive,
    };
});
