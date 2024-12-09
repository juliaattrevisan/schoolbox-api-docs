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
     * @param newNodes - the new courses to set
     */
    function setNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value = newNodes;
    }

    /**
     * Add nodes to the existing nodes
     * @param newNodes - the new courses to add
     */
    function addNodes(newNodes: CurriculumCoverageNode[]): void {
        nodes.value.forEach((node, index) => {
            node.usage.courses = [...node.usage.courses, ...newNodes[index].usage.courses];
            addCourseToChildren(node, newNodes[index]);
        });
    }

    return {
        nodes,
        setNodes,
        addNodes,
    };
});
