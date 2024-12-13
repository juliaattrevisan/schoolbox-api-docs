/**
 * Hold the list of selected nodes (curriculum)
 */

import { defineStore } from "pinia";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

export const useNodesStore = defineStore("ccNodes", () => {
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report

    /**
     * Set new nodes
     * @param newNodes - the new nodes to set
     */
    function setNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = newNodes;
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

    return {
        nodes,
        setNodes,
        addCourseUsage,
        setMoreNodes,
        addMoreCourseUsage,
    };
});
