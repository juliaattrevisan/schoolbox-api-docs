/**
 * Hold the list of selected nodes (curriculum)
 */

import { defineStore } from "pinia";
import { useFiltersStore } from "./ccFiltersStore";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";
import {
    getTotalCount,
    getCourseColumnCount,
    getUnitColumnCount,
    getCourseCount,
    getFilteredTotalCount,
} from "../util/countUtils";

export const useNodesStore = defineStore("ccNodes", () => {
    const filtersStore = useFiltersStore();
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report

    /**
     * Flatten the nodes into a single array for faster filtering
     * @param node - the node to flatten
     * @param flattenedNodes - the array to add the flattened nodes to
     * @returns - the flattened nodes
     */
    function flattenNodes(
        node: CurriculumCoverageNode,
        flattenedNodes: CurriculumCoverageNode[] = [],
    ): CurriculumCoverageNode[] {
        flattenedNodes.push(node);
        if (node.children && node.children.length > 0) {
            node.children.forEach((child) => {
                flattenNodes(child, flattenedNodes);
            });
        }
        return flattenedNodes;
    }

    const flattenedNodes = window.Vue.computed(() => {
        return nodes.value.reduce((acc, node) => {
            return [...acc, ...flattenNodes(node)];
        }, [] as CurriculumCoverageNode[]);
    });

    /**
     * Set the default isVisible property for the nodes, setting the first two levels as visible
     */
    function setDefaultIsVisible(): void {
        flattenedNodes.value.forEach((node) => {
            node.isVisible = false;
        });
        nodes.value.forEach((node) => {
            if (node.children) {
                node.isVisible = true;
                node.children.forEach((child) => {
                    child.isVisible = true;
                });
            }
        });
    }

    /**
     * Set the default isExpanded property for the nodes, setting the first two levels as expanded
     */
    function setDefaultIsExpanded(): void {
        flattenedNodes.value.forEach((node) => {
            node.isExpanded = false;
        });
        nodes.value.forEach((node) => {
            if (node.children) {
                node.isExpanded = true;
            }
        });
    }

    /**
     * Set the hasNoUsages property for the nodes
     */
    function setHasNoUsages(): void {
        flattenedNodes.value.forEach((node) => {
            node.hasNoUsages = getTotalCount(node, true) === 0;
            node.usage.courses.forEach((course) => {
                course.hasNoUsages = getCourseColumnCount(node, course, true) === 0;
                course.units.forEach((unit) => {
                    unit.hasNoUsages = getUnitColumnCount(node, course, unit, true) === 0;
                });
            });
        });
    }

    /**
     * Set new nodes
     * @param newNodes - the new nodes to set
     */
    function setNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = newNodes;
        setDefaultIsVisible();
        setDefaultIsExpanded();
        setHasNoUsages();
    }

    /**
     * Loop through and add the new course to the existing node.usage.courses[]
     * @param existingNode - the existing node to update
     * @param newNode - the new node that contains the new course to add
     */
    function addCourseToChildren(existingNode: CurriculumCoverageNode, newNode: CurriculumCoverageNode): void {
        if (existingNode.children) {
            existingNode.children.forEach((child, index) => {
                child.usage.courses = [...child.usage.courses, ...newNode.children![index].usage.courses];
                addCourseToChildren(child, newNode.children![index]);
            });
        }
    }

    /**
     * Add more course usages to the existing nodes
     * @param newNodes - the new nodes to add
     */
    function addCourseUsage(newNodes: CurriculumCoverageNode[]): void {
        nodes.value.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...newNodes[index].usage.courses];
            addCourseToChildren(node, newNodes[index]);
        });
    }

    /**
     * Add in more nodes to the existing nodes
     * @param newNodes - the new nodes to set
     */
    function setMoreNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = [...nodes.value, ...newNodes];
        setDefaultIsVisible();
        setDefaultIsExpanded();
        setHasNoUsages();
    }

    /**
     * Add more course usages to the existing nodes
     * @param newNodes - the new nodes to add
     */
    function addMoreCourseUsage(newNodes: CurriculumCoverageNode[]): void {
        const newNodeIds = newNodes.map((node) => node.id);
        const nodesToUpdate = nodes.value.filter((node) => newNodeIds.includes(node.id)); // to target only the nodes to that need updating (which are those last set in setMoreNodes)
        nodesToUpdate.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...newNodes[index].usage.courses];
            addCourseToChildren(node, newNodes[index]);
        });
    }

    /**
     * Toggle the nodes isExpanded property
     * @param node - the node to toggle
     */
    function toggleNodeExpand(node: CurriculumCoverageNode): void {
        if (node.isExpanded) {
            node.isExpanded = false;
            // hide the next level of children
            node.children?.forEach((child) => {
                child.isVisible = false;
            });
        } else {
            node.isExpanded = true;
            // show the next level of children
            node.children?.forEach((child) => {
                child.isVisible = true;
            });
        }
    }

    /**
     * Watch the onlyShowGaps filter and update the nodes visibility accordingly
     */
    window.Vue.watch(
        () => filtersStore.onlyShowGaps,
        () => {
            if (filtersStore.onlyShowGaps) {
                flattenedNodes.value.forEach((node) => {
                    node.previousIsVisible = node.isVisible;
                    node.isVisible = false;
                });
                const onlyNodesWithCodes = flattenedNodes.value.filter((node) => node.code);
                const onlyNodesWithNoUsage = onlyNodesWithCodes.filter(
                    (node) => getFilteredTotalCount(node, false) === 0,
                );
                onlyNodesWithNoUsage.forEach((node, index) => {
                    node.isVisible = index < 50;
                });
            } else {
                flattenedNodes.value.forEach((node) => {
                    node.isVisible = node.previousIsVisible;
                });
            }
        },
    );

    return {
        nodes,
        setNodes,
        addCourseUsage,
        setMoreNodes,
        addMoreCourseUsage,
        flattenedNodes,
        toggleNodeExpand,
    };
});
