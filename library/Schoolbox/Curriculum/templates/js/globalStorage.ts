/**
 * Save the root nodes of the curriculum in global storage
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

export const NODES_STORED = "sb_rootNodesStored";

/**
 * check if the nodes are in the global storage
 * @returns - true if the nodes are in the global storage
 */
export function hasStoredNodes(): boolean {
    return window.sbProps.storedNodes !== undefined;
}

/**
 * check if the nodes are being stored in the global storage
 * @returns - true if the nodes are being stored in the global storage
 */
export function isStoringNodes(): boolean {
    return window.sbProps.isStoringNodes ?? false;
}

/**
 * Set the nodes are being stored in the global storage
 */
export function setIsStoringNodes(): void {
    window.sbProps.isStoringNodes = true;
}

/**
 * Return the root curriculum nodes
 * @returns - the root nodes of the curriculum
 */
export function getStoredNodes(): Node[] {
    return JSON.parse(window.sbProps.storedNodes as string) as Node[]; // convert the saved string var back to JSON
}

/**
 * Set the root curriculum nodes
 * @param nodes - the root nodes of the curriculum
 */
export function setStoredNodes(nodes: Node[]): void {
    window.sbProps.storedNodes = JSON.stringify(nodes);
    document.dispatchEvent(new Event(NODES_STORED));
}
