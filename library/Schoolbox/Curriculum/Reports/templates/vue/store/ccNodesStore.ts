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

const isVisibleBatchCount = 50; // the number of nodes to show at a time, when onlyShowGaps is true

export const useNodesStore = defineStore("ccNodes", () => {
    const filtersStore = useFiltersStore();
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report
    const currentIsVisibleCount = window.Vue.ref(0); // the current number of visible nodes

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

    /**
     * The flattened nodes, to be used for looping through all nodes without having to loop through the hierarchy
     */
    const flattenedNodes = window.Vue.computed(() => {
        return nodes.value.reduce((acc, node) => {
            return [...acc, ...flattenNodes(node)];
        }, [] as CurriculumCoverageNode[]);
    });

    /**
     * The total number of nodes
     */
    const totalCount = window.Vue.computed(() => {
        return flattenedNodes.value.length;
    });

    /**
     * The total number of valid empty nodes (nodes with a code and no usages)
     */
    const nodesWithNoFilteredUsages = window.Vue.computed(() => {
        return flattenedNodes.value.filter((node) => node.code && getFilteredTotalCount(node, false) === 0);
    });

    /**
     * Has more nodes to show
     */
    const hasMore = window.Vue.computed(() => {
        // this check will only every apply if the onlyShowGaps filter is on
        if (filtersStore.onlyShowGaps) {
            return currentIsVisibleCount.value < totalCount.value;
        }
        return false;
    });

    /**
     * Set the default isVisible property for the nodes, setting the first two levels as visible
     */
    function setIsVisible(): void {
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
    function setIsExpanded(): void {
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
                course.hasNoCourseUsages = getCourseColumnCount(node, course, true) === 0;
                course.units.forEach((unit) => {
                    unit.hasNoUsages = getUnitColumnCount(node, course, unit, true) === 0;
                });
                course.hasNoTotalUsages = getCourseCount(node, course, true) === 0;
            });
        });
    }

    /**
     * Set new nodes
     * @param newNodes - the new nodes to set
     */
    function setNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = newNodes;
        setIsVisible();
        setIsExpanded();
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
        setIsVisible();
        setIsExpanded();
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
     * Loop through and hide the children of the node
     * @param children - the children to hide
     */
    function hideChildren(children: CurriculumCoverageNode[] | null): void {
        if (children) {
            children.forEach((child) => {
                child.isVisible = false;
                child.isExpanded = false;
                hideChildren(child.children);
            });
        }
    }

    /**
     * Loop through and show the children of the node
     * @param children - the children to show
     */
    function showChildren(children: CurriculumCoverageNode[] | null): void {
        if (children) {
            children.forEach((child) => {
                child.isVisible = true;
                hideChildren(child.children);
            });
        }
    }

    /**
     * Toggle the nodes isExpanded property
     * @param node - the node to toggle
     */
    function toggleNodeExpand(node: CurriculumCoverageNode): void {
        if (node.isExpanded) {
            node.isExpanded = false;
            hideChildren(node.children);
        } else {
            node.isExpanded = true;
            showChildren(node.children);
        }
    }

    /**
     * Set the default values for the nodes
     */
    function setDefaults(): void {
        setIsVisible();
        setIsExpanded();
        setHasNoUsages();
    }

    /**
     * Watch the selectedReportOptions filter and update the nodes visibility accordingly
     */
    window.Vue.watch(
        () => filtersStore.selectedReportOptions,
        () => {
            if (filtersStore.onlyShowGaps) {
                flattenedNodes.value.forEach((node) => {
                    node.isVisible = false;
                });
                const nodesToShow = nodesWithNoFilteredUsages.value.filter(
                    (node, index) => index < isVisibleBatchCount,
                ); // just get the first batch of nodes to show
                nodesToShow.forEach((node) => {
                    node.isVisible = true;
                });
            }
        },
    );

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
                const nodesToShow = nodesWithNoFilteredUsages.value.filter(
                    (node, index) => index < isVisibleBatchCount,
                ); // just get the first batch of nodes to show
                nodesToShow.forEach((node) => {
                    node.isVisible = true;
                });
                currentIsVisibleCount.value = isVisibleBatchCount;
            } else {
                flattenedNodes.value.forEach((node) => {
                    node.isVisible = node.previousIsVisible;
                });
            }
        },
    );

    /**
     * Show the next set of isVisibleBatchCount when called
     */
    function showMore(): void {
        const isVisibleBatchCountNext = currentIsVisibleCount.value + isVisibleBatchCount;
        const nodesToShow = nodesWithNoFilteredUsages.value.filter(
            (node, index) => index <= isVisibleBatchCountNext && index >= currentIsVisibleCount.value, // get the next batch of nodes to show
        );
        nodesToShow.forEach((node) => {
            node.isVisible = true;
        });
        currentIsVisibleCount.value = isVisibleBatchCountNext;
    }

    return {
        nodes,
        setNodes,
        addCourseUsage,
        setMoreNodes,
        addMoreCourseUsage,
        flattenedNodes,
        toggleNodeExpand,
        setDefaults,
        hasMore,
        showMore,
    };
});
