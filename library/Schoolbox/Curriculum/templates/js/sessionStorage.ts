/**
 * Save the root nodes of the curriculum in session storage
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

const SB_NODES = "sb_rootNodes";
const SB_IS_STORING_NODES = "sb_isStoringRootNodes";
export const NODES_STORED = "sb_rootNodesStored";

/**
 * check if the nodes are in the session storage
 *
 * @returns { boolean } - true if the nodes are in the session storage
 */
export function hasStoredNodes() {
    return window.sessionStorage.getItem(SB_NODES);
}

/**
 * check if the nodes are being stored in the session storage
 *
 * @returns { boolean } - true if the nodes are being stored in the session storage
 */
export function isStoringNodes() {
    return window.sessionStorage.getItem(SB_IS_STORING_NODES);
}

/**
 * Set the nodes are being stored in the session storage
 */
export function setIsStoringNodes() {
    window.sessionStorage.setItem(SB_IS_STORING_NODES, "true");
}

/**
 * Return the root curriculum nodes
 *
 * @returns { JSON } - the root nodes of the curriculum
 */
export function getStoredNodes() {
    const nodes = window.sessionStorage.getItem(SB_NODES);
    return JSON.parse(nodes as string) as Node[]; // convert the saved string var back to JSON
}

/**
 * Set the root curriculum nodes
 *
 * @param { Node[] } nodes - the root nodes of the curriculum
 */
export function setStoredNodes(nodes: Node[]) {
    window.sessionStorage.setItem(SB_NODES, JSON.stringify(nodes));
    document.dispatchEvent(new Event(NODES_STORED));
}
