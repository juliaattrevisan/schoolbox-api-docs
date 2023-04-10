/**
 * Save the root nodes of the curriculum in global storage
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

export const NODES_STORED = "sb_rootNodesStored";

/**
 * check if the nodes are in the global storage
 *
 * @returns { boolean } - true if the nodes are in the global storage
 */
export function hasStoredNodes() {
    return window.sbProps.storedNodes;
}

/**
 * check if the nodes are being stored in the global storage
 *
 * @returns { boolean } - true if the nodes are being stored in the global storage
 */
export function isStoringNodes() {
    return window.sbProps.isStoringNodes;
}

/**
 * Set the nodes are being stored in the global storage
 */
export function setIsStoringNodes() {
    window.sbProps.isStoringNodes = true;
}

/**
 * Return the root curriculum nodes
 *
 * @returns { JSON } - the root nodes of the curriculum
 */
export function getStoredNodes() {
    return window.sbProps.storedNodes;
}

/**
 * Set the root curriculum nodes
 *
 * @param { Node[] } nodes - the root nodes of the curriculum
 */
export function setStoredNodes(nodes: Node[]) {
    window.sbProps.storedNodes = nodes;
    document.dispatchEvent(new Event(NODES_STORED));
}
