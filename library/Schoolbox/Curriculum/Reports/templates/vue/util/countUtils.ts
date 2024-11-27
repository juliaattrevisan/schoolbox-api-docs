/**
 * Any utility functions for counting node usages
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import type {
    CourseWithCurriculumNodeUsage,
    CurriculumCoverageNode,
    UnitWithCurriculumNodeUsage,
} from "../../js/types/curriculumCoverageTypes";
import { ReportOptionValues } from "../../js/types/curriculumCoverageTypes";
import { useFiltersStore } from "../store/ccFiltersStore";

// #region usage counts

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param $unit - the unit to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total unit usage count
 */
export function getUnitColumnCount(
    node: CurriculumCoverageNode,
    $course: Course,
    $unit: Unit,
    isExpanded: boolean,
): number {
    const filtersStore = useFiltersStore();
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined) return 0;
    const unit = course.units.find((u) => u.id === $unit.id);
    if (unit === undefined) return 0;
    const { usage } = unit;
    const onNodeCount = filtersStore.allReportOptions.reduce((acc, option) => {
        // always skip course as it can't be assigned within a unit
        if (option.value === ReportOptionValues.COURSE) return acc;
        return acc + usage[option.value].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getUnitColumnCount(child, $course, $unit, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total course usage count
 */
export function getCourseColumnCount(node: CurriculumCoverageNode, $course: Course, isExpanded: boolean): number {
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined) return 0;
    const { usage } = course;
    // only need to check for course usage on the course column
    const onNodeCount = usage.course.count;
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getCourseColumnCount(child, $course, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count for all selected course(s)
 */
export function getCourseCount(node: CurriculumCoverageNode, $course: Course, isExpanded: boolean): number {
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined || course.units === undefined) return 0;
    const courseUsageCount = getCourseColumnCount(node, $course, isExpanded);
    const unitUsageCount = course.units.reduce((acc, unit) => {
        return acc + getUnitColumnCount(node, course, unit, isExpanded);
    }, 0);
    return courseUsageCount + unitUsageCount;
}

/**
 * Get the total node usage count for a node,
 * which is the total usage count for all courses
 * @param node - the node to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count
 */
export function getTotalCount(node: CurriculumCoverageNode, isExpanded: boolean): number {
    return node.usage.courses.reduce((acc, course) => {
        return acc + getCourseCount(node, course, isExpanded);
    }, 0);
}

// #endregion filtered usage counts

// #region filtered usage counts

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param $unit - the unit to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total unit usage count
 */
export function getFilteredUnitColumnCount(
    node: CurriculumCoverageNode,
    $course: Course,
    $unit: Unit,
    isExpanded: boolean,
): number {
    const filtersStore = useFiltersStore();
    const course = node.usage.courses.find((c) => c.id === $course.id) as CourseWithCurriculumNodeUsage; // assume course exists
    const unit = course.units.find((u) => u.id === $unit.id) as UnitWithCurriculumNodeUsage; // assume unit exists
    const { usage } = unit;
    const onNodeCount = filtersStore.selectedReportOptions.reduce((acc, option) => {
        // always skip course as it can't be assigned within a unit
        if (option === ReportOptionValues.COURSE) return acc;
        return acc + usage[option].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredUnitColumnCount(child, $course, $unit, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total course usage count
 */
export function getFilteredCourseColumnCount(
    node: CurriculumCoverageNode,
    $course: Course,
    isExpanded: boolean,
): number {
    const filtersStore = useFiltersStore();
    const course = node.usage.courses.find((c) => c.id === $course.id) as CourseWithCurriculumNodeUsage; // assume course exists
    const { usage } = course;
    // only need to check for course usage on the course column
    const onNodeCount = filtersStore.selectedReportOptions.includes(ReportOptionValues.COURSE) ? usage.course.count : 0;
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredCourseColumnCount(child, $course, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count for all selected course(s)
 */
export function getFilteredCourseCount(node: CurriculumCoverageNode, $course: Course, isExpanded: boolean): number {
    const course = node.usage.courses.find((c) => c.id === $course.id) as CourseWithCurriculumNodeUsage; // assume course exists
    const courseUsageCount = getFilteredCourseColumnCount(node, $course, isExpanded);
    const unitUsageCount = course.units.reduce((acc, unit) => {
        return acc + getFilteredUnitColumnCount(node, course, unit, isExpanded);
    }, 0);
    return courseUsageCount + unitUsageCount;
}

/**
 * Get the total node usage count for a node,
 * which is the total usage count for all courses
 * @param node - the node to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count
 */
export function getFilteredTotalCount(node: CurriculumCoverageNode, isExpanded: boolean): number {
    return node.usage.courses.reduce((acc, course) => {
        return acc + getFilteredCourseCount(node, course, isExpanded);
    }, 0);
}

// #endregion filtered usage counts
