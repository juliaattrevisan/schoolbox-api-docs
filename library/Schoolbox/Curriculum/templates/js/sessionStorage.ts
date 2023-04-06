/**
 * Save the root nodes of the curriculum in session storage
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

const SB_ROOT_CURRICULUM_NODES = "sb_rootCurriculumNodes";

/**
 * check if the nodes are in the session storage
 *
 * @returns { boolean } - true if the nodes are in the session storage
 */
export function hasRootNodes() {
    return window.sessionStorage.getItem(SB_ROOT_CURRICULUM_NODES);
}

/**
 * Return the root curriculum nodes
 *
 * @returns { JSON } - the root nodes of the curriculum
 */
export function getRootNodes() {
    const nodes = window.sessionStorage.getItem(SB_ROOT_CURRICULUM_NODES);
    return JSON.parse(nodes as string) as Node[]; // convert the saved string var back to JSON
}

/**
 * Set the root curriculum nodes
 *
 * @param { Node[] } nodes - the root nodes of the curriculum
 */
export function setRootNodes(nodes: Node[]) {
    window.sessionStorage.setItem(SB_ROOT_CURRICULUM_NODES, JSON.stringify(nodes));
}
