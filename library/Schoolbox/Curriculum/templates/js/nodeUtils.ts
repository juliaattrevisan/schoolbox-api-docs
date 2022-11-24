/**
 * Various utils related to managing curriculum nodes
 */

import { Node } from '@schoolboxCurriculum/types/Node';
import { Breadcrumb } from '@schoolboxCurriculum/types/Breadcrumb';

/**
 * To indicate that a node should be invalid and unset
 * used as the base 'node' to show all Curricula in the curriculum display list
 * and also to know when to toggle when a node is active
 */
export const UNSET_ID = 0;

/**
 * To sort the nodes, we store collator to for faster performance
 */
const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

/**
 * return an empty node, usually used to indicate there's currently
 * nothing to display
 *
 * @returns { Node } the empty Node
 */
export function getEmptyNode(): Node {
    return {
        children: null,
        code: null,
        description: null,
        enabled: true,
        id: UNSET_ID,
        parent: null,
        name: '',
    };
}

/**
 * To just return the node containing the specified id
 * We recursively loop through the object until we find the
 * matched object or undefined if not found
 *
 * @param { Node[] } array - an array of Node to loop through
 * @param { number } id - the id to find
 * @returns { Node | undefined } - the found Node
 */
export function findById(array: Node[], id: number): Node | undefined {
    let node: Node;
    array.forEach((item) => {
        if (item.id === id) {
            node = item;
        }
        if (item.children?.length) {
            const innerResult = findById(item.children, id);
            if (innerResult) {
                node = innerResult;
            }
        }
    });
    return node;
}

/**
 * Node with a code take priority, then followed by those without, the name is used
 *
 * @param { object }
 * @param { object }
 */
function sortNodesByCodeThenName(aNode, bNode) {
    const aHasCode = aNode.code !== '';
    const bHasCode = bNode.code !== '';
    if (aHasCode && bHasCode) {
        // if both have codes, sort by the code
        return collator.compare(aNode.code, bNode.code);
    }
    if (!aHasCode && !bHasCode) {
        // if both have no code, just sort by name
        return collator.compare(aNode.name, bNode.name);
    }
    return aHasCode ? -1 : bHasCode ? 1 : 0; // those with a code take precendence over those without
}

/**
 * Return the nodes sorted first by order, then code, then name
 *
 * @param { array }
 * @returns { array }
 */
export function sortNodes(nodes) {
    const nodesWithOrder = nodes.filter((node) => node.order !== 0); // to prioritise nodes with order
    if (nodesWithOrder.length > 0) {
        nodesWithOrder.sort((aNode, bNode) => aNode.order - bNode.order); // sort ascending
    }
    const nodesWithoutOrder = nodes.filter((node) => node.order === 0); // then those without order follow
    if (nodesWithoutOrder.length > 0) {
        nodesWithoutOrder.sort(sortNodesByCodeThenName); // sorted by code then name
    }
    const sortedChildNodes = nodesWithOrder.concat(nodesWithoutOrder); // nodes with order, then the others
    return sortedChildNodes;
}

/**
 * To return an array with any objects containing the specified id removed
 * we move through the object, looping recursively through .children and remove
 * any with the id that's being removed
 *
 * @param { array }
 * @param { number }
 *
 * @returns { object }
 */
export function removeId(data, id) {
    if (data !== null) {
        // .children wont always be available if the node hasn't been loaded yet
        return data.reduce((previousValue, currentValue) => {
            if (currentValue.id !== id) {
                previousValue.push({ ...currentValue, children: removeId(currentValue.children, id) });
            }
            return previousValue;
        }, []);
    }
    return data;
}

/**
 * Recursively loop through the parents adding in a breadcrumb for each
 *
 * @param { Node } node - the Node to traverse for breadcrumbs
 * @param { Breadcrumb[] } currentBreadcrumbs - the current breadcrumbs
 * @returns { Breadcrumb[] } - the new breadcrumbs
 */
export function getBreadcrumbs(node: Node, currentBreadcrumbs: Breadcrumb[]): Breadcrumb[] {
    if (node.parent !== null) {
        // if there's a parent to use as a breadcrumb
        let breadcrumbs = [{ id: node.parent.id, name: node.parent.name }, ...currentBreadcrumbs]; // add it in to the front of the list
        return (breadcrumbs = getBreadcrumbs(node.parent, breadcrumbs)); // and then check the next node up the list
    }
    // otherwise we must be at the top of the breadcrumb list
    return currentBreadcrumbs; // so we can return now
}
