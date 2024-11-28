/**
 * Any utility functions for counting node usages
 */

import type {
    CourseWithCurriculumNodeUsage,
    CurriculumCoverageNode,
    UnitWithCurriculumNodeUsage,
} from "../../js/types/curriculumCoverageTypes";
import { ReportOptionValues } from "../../js/types/curriculumCoverageTypes";
import { useFiltersStore } from "../store/ccFiltersStore";

// #region usage counts

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total course usage count
 */
export function getCourseColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getCourseColumnCount(child, course, isExpanded);
        }, 0);
        return course.usage.course.count + onChildrenCount;
    }
    // only need to check for course usage on the course column
    return course.usage.course.count;
}

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param unit - the unit to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total unit usage count
 */
export function getUnitColumnCount(
    node: CurriculumCoverageNode,
    unit: UnitWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    const onNodeCount = useFiltersStore().allReportOptions.reduce((acc, option) => {
        // always skip course as it can't be assigned within a unit
        if (option.value === ReportOptionValues.COURSE) return acc;
        return acc + unit.usage[option.value].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getUnitColumnCount(child, unit, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count for all selected course(s)
 */
export function getCourseCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    return (
        getCourseColumnCount(node, course, isExpanded) +
        course.units.reduce((acc, unit) => {
            return acc + getUnitColumnCount(node, unit, isExpanded);
        }, 0)
    );
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

// #endregion

// #region filtered usage counts

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total course usage count
 */
export function getFilteredCourseColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    // return early if course is not selected
    if (!useFiltersStore().selectedReportOptions.includes(ReportOptionValues.COURSE)) {
        return 0;
    }
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredCourseColumnCount(child, course, isExpanded);
        }, 0);
        return course.usage.course.count + onChildrenCount;
    }
    // only need to check for course usage on the course column
    return course.usage.course.count;
}

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param unit - the unit to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total unit usage count
 */
export function getFilteredUnitColumnCount(
    node: CurriculumCoverageNode,
    unit: UnitWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    const onNodeCount = useFiltersStore().selectedReportOptions.reduce((acc, option) => {
        // always skip course as it can't be assigned within a unit
        if (option === ReportOptionValues.COURSE) return acc;
        return acc + unit.usage[option].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredUnitColumnCount(child, unit, isExpanded);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param isExpanded - whether the node is expanded
 * @returns the total node usage count for all selected course(s)
 */
export function getFilteredCourseCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    isExpanded: boolean,
): number {
    const unitUsageCount = course.units.reduce((acc, unit) => {
        return acc + getFilteredUnitColumnCount(node, unit, isExpanded);
    }, 0);
    return getFilteredCourseColumnCount(node, course, isExpanded) + unitUsageCount;
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
