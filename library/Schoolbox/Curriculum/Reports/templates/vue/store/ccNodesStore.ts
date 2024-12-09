/**
 * Hold the list of selected nodes (curriculum)
 */

import { defineStore } from "pinia";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

export const useNodesStore = defineStore("ccNodes", () => {
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report

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
     * Set the nodes
     * @param newNodes - the new nodes to set
     */
    function setNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = newNodes;
    }

    /**
     * Add nodes to the existing nodes
     * @param newNodes - the new nodes to add
     */
    function addNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...newNodes[index].usage.courses];
            addCourseToChildren(node, newNodes[index]);
        });
    }

    /**
     *
     * @param newNodes - the new nodes to set
     */
    function setMoreNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = [...nodes.value, ...newNodes];
    }

    /**
     *
     * @param newNodes - the new nodes to add
     */
    function addMoreNodes(newNodes: CurriculumCoverageNode[]): void {
        const newNodeIds = newNodes.map((node) => node.id);
        const nodesToUpdate = nodes.value.filter((node) => newNodeIds.includes(node.id));
        nodesToUpdate.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...newNodes[index].usage.courses];
            addCourseToChildren(node, newNodes[index]);
        });
    }

    return {
        nodes,
        setNodes,
        addNodes,
        setMoreNodes,
        addMoreNodes,
    };
});
