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

    /**
     * report has been triggered so set the active course and nodes
     * @param $course - the course to set as active
     * @param $unit - the unit to set as active
     * @param $node - the node to set as active
     */
    function setActive($course: Course, $unit: Unit, $node: CurriculumCoverageNode): void {
        course.value = $course;
        unit.value = $unit;
        node.value = $node;
        console.warn("setActive", course.value, unit.value, node.value);
    }

    return {
        node,
        setActive,
    };
});
