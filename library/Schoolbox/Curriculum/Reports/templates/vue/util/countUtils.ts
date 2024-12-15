/**
 * Any utility functions for counting node usages
 *
 * The below is a visual example of the report row and how the functions are used to calculate the usage counts
 * The non filtered functions are used to check whether there's no usages linked to the relevant course columns / unit columns,
 * in which case any reactivity can be skipped.
 * Where as the filtered functions are used to calculate the usage counts based on the selected report options
 *
 * | Total count | Course column count | Unit column count | Unit column count| Course count |
 * | getTotalCount() | getCourseColumnCount() | getUnitColumnCount() | getUnitColumnCount() | getCourseCount() |
 * | getFilteredTotalCount() | getFilteredCourseColumnCount() | getFilteredUnitColumnCount() | getFilteredUnitColumnCount() | getFilteredCourseCount() |
 */

import type {
    CurriculumCoverageNode,
    CourseWithCurriculumNodeUsage,
    UnitWithCurriculumNodeUsage,
} from "../../js/types/curriculumCoverageTypes";
import { ReportOptionValues } from "../../js/types/curriculumCoverageTypes";
import { useFiltersStore } from "../store/ccFiltersStore";

// #region get child usage

/**
 * Get the child usage count for a course
 * @param childNode - the node to get the usage count for
 * @param parentCourse - the node to get the usage count for
 * @returns the course usage
 */
function getChildCourseUsage(
    childNode: CurriculumCoverageNode,
    parentCourse: CourseWithCurriculumNodeUsage,
): CourseWithCurriculumNodeUsage {
    // loop through the node children and find the usage.courses[] that matches the same id
    return childNode.usage.courses.find((course) => course.id === parentCourse.id) as CourseWithCurriculumNodeUsage;
}

/**
 * Get the child usage count for a unit
 * @param childNode - the node to get the usage count for
 * @param parentCourse - the node to get the usage count for
 * @param parentUnit - the child node to get the usage count for
 * @returns the unit usage
 */
function getChildUnitUsage(
    childNode: CurriculumCoverageNode,
    parentCourse: CourseWithCurriculumNodeUsage,
    parentUnit: UnitWithCurriculumNodeUsage,
): UnitWithCurriculumNodeUsage {
    // loop through the node children and find the usage.courses[].units[] that matches the same id
    const childCourse = childNode.usage.courses.find(
        (course) => course.id === parentCourse.id,
    ) as CourseWithCurriculumNodeUsage;
    return childCourse.units.find((unit) => unit.id === parentUnit.id) as UnitWithCurriculumNodeUsage;
}

// #endregion

// #region usage counts

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total course usage count
 */
export function getCourseColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    includeChildren: boolean,
): number {
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (includeChildren && node.children) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getCourseColumnCount(child, getChildCourseUsage(child, course), true);
        }, 0);
        return course.usage.course.count + onChildrenCount;
    }
    // only need to check for course usage on the course column
    return course.usage.course.count;
}

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param unit - the unit to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total unit usage count
 */
export function getUnitColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    unit: UnitWithCurriculumNodeUsage,
    includeChildren: boolean,
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
    if (includeChildren && node.children) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getUnitColumnCount(child, course, getChildUnitUsage(child, course, unit), true);
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
 * @param includeChildren - whether to include direct children
 * @returns the total node usage count for all selected course(s)
 */
export function getCourseCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    includeChildren: boolean,
): number {
    return (
        getCourseColumnCount(node, course, includeChildren) +
        course.units.reduce((acc, unit) => {
            return acc + getUnitColumnCount(node, course, unit, includeChildren);
        }, 0)
    );
}

/**
 * Get the total node usage count for a node,
 * which is the total usage count for all courses
 * @param node - the node to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total node usage count
 */
export function getTotalCount(node: CurriculumCoverageNode, includeChildren: boolean): number {
    return node.usage.courses.reduce((acc, course) => {
        return acc + getCourseCount(node, course, includeChildren);
    }, 0);
}

// #endregion

// #region filtered usage counts

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total course usage count
 */
export function getFilteredCourseColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    includeChildren: boolean,
): number {
    const key = `column-${node.id}-${course.id}-${includeChildren}-${useFiltersStore().selectedReportOptionsKey}`;
    if (node.storedUsages[key]) {
        return node.storedUsages[key];
    }
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (includeChildren && node.children) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredCourseColumnCount(child, getChildCourseUsage(child, course), true);
        }, 0);
        node.storedUsages[key] = course.usage.course.count + onChildrenCount;
        return node.storedUsages[key];
    }
    // only need to check for course usage on the course column
    node.storedUsages[key] = course.usage.course.count;
    return node.storedUsages[key];
}

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param unit - the unit to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total unit usage count
 */
export function getFilteredUnitColumnCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    unit: UnitWithCurriculumNodeUsage,
    includeChildren: boolean,
): number {
    const key = `unit-${node.id}-${course.id}-${unit.id}-${includeChildren}-${useFiltersStore().selectedReportOptionsKey}`;
    if (node.storedUsages[key]) {
        return node.storedUsages[key];
    }
    const onNodeCount = useFiltersStore().selectedReportOptions.reduce((acc, option) => {
        // always skip course as it can't be assigned within a unit
        if (option === ReportOptionValues.COURSE) return acc;
        return acc + unit.usage[option].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (includeChildren && node.children) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getFilteredUnitColumnCount(child, course, getChildUnitUsage(child, course, unit), true);
        }, 0);
        node.storedUsages[key] = onNodeCount + onChildrenCount;
        return node.storedUsages[key];
    }
    node.storedUsages[key] = onNodeCount;
    return node.storedUsages[key];
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param course - the course to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total node usage count for all selected course(s)
 */
export function getFilteredCourseCount(
    node: CurriculumCoverageNode,
    course: CourseWithCurriculumNodeUsage,
    includeChildren: boolean,
): number {
    const key = `course-total-${node.id}-${course.id}-${includeChildren}-${useFiltersStore().selectedReportOptionsKey}`;
    if (node.storedUsages[key]) {
        return node.storedUsages[key];
    }
    const unitUsageCount = course.units.reduce((acc, unit) => {
        return acc + getFilteredUnitColumnCount(node, course, unit, includeChildren);
    }, 0);
    const courseCount = getFilteredCourseColumnCount(node, course, includeChildren) + unitUsageCount;
    node.storedUsages[key] = courseCount;
    return node.storedUsages[key];
}

/**
 * Get the total node usage count for a node,
 * which is the total usage count for all courses
 * @param node - the node to get the usage count for
 * @param includeChildren - whether to include direct children
 * @returns the total node usage count
 */
export function getFilteredTotalCount(node: CurriculumCoverageNode, includeChildren: boolean): number {
    const key = `total-${node.id}-${includeChildren}-${useFiltersStore().selectedReportOptionsKey}`;
    if (node.storedUsages[key]) {
        return node.storedUsages[key];
    }
    console.warn("getFilteredTotalCount");
    const usage = node.usage.courses.reduce((acc, course) => {
        return acc + getFilteredCourseCount(node, course, includeChildren);
    }, 0);
    node.storedUsages[key] = usage;
    return node.storedUsages[key];
}

// #endregion filtered usage counts
