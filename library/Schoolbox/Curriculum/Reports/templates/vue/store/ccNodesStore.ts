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
    const newNodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the newly added nodes to display, used to set up the default properties
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
        if (node.children) {
            node.children.forEach((child) => {
                flattenNodes(child, flattenedNodes);
            });
        }
        return flattenedNodes;
    }

    /**
     * The flattened nodes, to be used for looping through all nodes without having to loop through the hierarchy
     */
    const flattenedNewNodes = window.Vue.computed(() => {
        return newNodes.value.reduce((acc, node) => {
            return [...acc, ...flattenNodes(node)];
        }, [] as CurriculumCoverageNode[]);
    });

    /**
     * The flattened nodes, to be used for looping through all nodes without having to loop through the hierarchy
     */
    const flattenedNodes = window.Vue.computed(() => {
        return nodes.value.reduce((acc, node) => {
            return [...acc, ...flattenNodes(node)];
        }, [] as CurriculumCoverageNode[]);
    });

    // #region set nodes

    /**
     * Set new nodes
     * @param $newNodes - the new nodes to set
     */
    function setNodes($newNodes: CurriculumCoverageNode[]): void {
        newNodes.value = $newNodes;
        nodes.value = $newNodes;
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
     * @param $newNodes - the new nodes to add
     */
    function addCourseUsage($newNodes: CurriculumCoverageNode[]): void {
        nodes.value.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...$newNodes[index].usage.courses];
            addCourseToChildren(node, $newNodes[index]);
        });
    }

    /**
     * Add in more nodes to the existing nodes
     * @param $newNodes - the new nodes to set
     */
    function setMoreNodes($newNodes: CurriculumCoverageNode[]): void {
        newNodes.value = $newNodes;
        nodes.value = [...nodes.value, ...$newNodes];
    }

    /**
     * Add more course usages to the existing nodes
     * @param $newNodes - the new nodes to add
     */
    function addMoreCourseUsage($newNodes: CurriculumCoverageNode[]): void {
        newNodes.value.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...$newNodes[index].usage.courses];
            addCourseToChildren(node, $newNodes[index]);
        });
    }

    // #endregion

    // #region expand / collapse

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

    // #endregion

    // #region filter changes

    const filtersStore = useFiltersStore();
    let currentIsVisibleCount = 0; // the current number of visible nodes
    const isVisibleBatchCount = 50; // the number of nodes to show at a time, when onlyShowGaps is true

    /**
     * The total number of valid empty nodes (nodes with a code and no usages)
     */
    const nodesWithNoFilteredUsages = window.Vue.computed(() => {
        return flattenedNodes.value.filter((node) => node.code && getFilteredTotalCount(node, false) === 0);
    });

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
                currentIsVisibleCount = isVisibleBatchCount;
            } else {
                flattenedNodes.value.forEach((node) => {
                    node.isVisible = node.previousIsVisible;
                });
                currentIsVisibleCount = 0; // reset the current visible count
            }
        },
    );

    // #endregion

    // #region show more

    /**
     * Has more nodes to show
     */
    const hasMore = window.Vue.computed(() => {
        // this check will only ever apply if the onlyShowGaps filter is on
        if (filtersStore.onlyShowGaps) {
            return currentIsVisibleCount < flattenedNodes.value.length;
        }
        return false;
    });

    /**
     * Show the next set of isVisibleBatchCount when called
     */
    function showMore(): void {
        const isVisibleBatchCountNext = currentIsVisibleCount + isVisibleBatchCount;
        const nodesToShow = nodesWithNoFilteredUsages.value.filter(
            (node, index) => index <= isVisibleBatchCountNext && index >= currentIsVisibleCount, // get the next batch of nodes to show
        );
        nodesToShow.forEach((node) => {
            node.isVisible = true;
        });
        currentIsVisibleCount = isVisibleBatchCountNext;
        // don't let the currentIsVisibleCount go over the total number of nodes
        if (currentIsVisibleCount >= flattenedNodes.value.length) {
            currentIsVisibleCount = flattenedNodes.value.length;
        }
    }

    // #endregion

    // #region set defaults

    /**
     * Set the default isVisible property for the nodes, setting the first two levels as visible
     */
    function setIsVisible(): void {
        flattenedNewNodes.value.forEach((node) => {
            node.isVisible = false;
        });
        newNodes.value.forEach((node) => {
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
        flattenedNewNodes.value.forEach((node) => {
            node.isExpanded = false;
        });
        newNodes.value.forEach((node) => {
            if (node.children) {
                node.isExpanded = true;
            }
        });
    }

    /**
     * When a node has no usages, we can set the hasNoUsages property to true
     * so we never have to loop through and calculate it again
     */
    function setHasNoUsages(): void {
        flattenedNewNodes.value.forEach((node) => {
            node.hasNoUsages = getTotalCount(node, true) === 0;
            node.usage.courses.forEach((course) => {
                course.hasNoCourseUsages = getCourseColumnCount(node, course, true) === 0;
                course.units.forEach((unit) => {
                    unit.hasNoUsages = getUnitColumnCount(node, course, unit, true) === 0;
                });
                course.hasNoTotalUsages = getCourseCount(node, course, true) === 0;
            });
            node.storedUsages = {};
        });
    }

    /**
     * Add the additional front end properties to the nodes,
     * to track visibility, expansion of the nodes and if they have no usages
     */
    function setDefaults(): void {
        setIsVisible();
        setIsExpanded();
        setHasNoUsages();
        // if only show gaps is active when new nodes are added, we need to update their visibility straight away
        if (filtersStore.onlyShowGaps) {
            // hide the newly added nodes, may be visible again if they have no usages
            flattenedNewNodes.value.forEach((node) => {
                node.previousIsVisible = node.isVisible;
                node.isVisible = false;
            });
        }
        // and if there's now more empty nodes to show, add the next batch in straight away
        // even if this new batch has no empty usages, triggering here will help show that a load has taken place,
        // even if the next batch of empty nodes is only from the previously loaded nodes
        if (filtersStore.onlyShowGaps && hasMore.value) {
            showMore();
        }
    }

    // #endregion

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
