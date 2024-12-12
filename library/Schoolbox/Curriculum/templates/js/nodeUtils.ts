/**
 * Various utils related to managing curriculum nodes
 */

import type { Ref } from "vue";
import { unref } from "vue";
import type {
    Node,
    Usage,
    CourseUsageObject,
    UnitUsageObject,
    LearningActivityUsageObject,
} from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

/**
 * To indicate that a node should be invalid and unset
 * used as the base 'node' to show all Curricula in the curriculum display list
 * and also to know when to toggle when a node is active
 */
export const UNSET_ID = 0;

/**
 * To sort the nodes, we store collator to for faster performance
 */
const collator = new Intl.Collator("en", { numeric: true, sensitivity: "base" });

/**
 * return an empty node, usually used to indicate there's currently
 * nothing to display
 * @returns the empty Node
 */
export function getEmptyNode(): Node {
    return {
        children: null,
        code: null,
        description: null,
        enabled: true,
        id: UNSET_ID,
        order: 0,
        parent: null,
        name: "",
        _links: {
            delete: "",
            getUsage: "",
            read: "",
            update: "",
            getExport: "",
        },
        _usageCounts: {
            course: 0,
            unit: 0,
            assessment: 0,
            badge: 0,
            folder: 0,
        },
    };
}

/**
 * To just return the node containing the specified id
 * We recursively loop through the object until we find the
 * matched object or undefined if not found
 * @param array - an array of Node to loop through
 * @param id - the id to find
 * @returns - the found Node
 */
export function findById(array: Node[], id: number): Node | undefined {
    let node: Node | undefined;
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
 * @param aNode - the first node to compare
 * @param bNode - the second node to compare
 * @returns - the number to determine sorting
 */
function sortNodesByCodeThenName(aNode: Node, bNode: Node): number {
    const aHasCode = aNode.code !== "";
    const bHasCode = bNode.code !== "";
    if (aHasCode && bHasCode) {
        // if both have codes, sort by the code
        return collator.compare(aNode.code as string, bNode.code as string);
    }
    if (!aHasCode && !bHasCode) {
        // if both have no code, just sort by name
        return collator.compare(aNode.name, bNode.name);
    }
    // those with a code take precedence over those without
    if (aHasCode) {
        return -1;
    }
    if (bHasCode) {
        return 1;
    }
    return 0;
}

/**
 * Return the nodes sorted first by order, then code, then name
 * @param nodes - the nodes to sort
 * @returns - the sorted nodes
 */
export function sortNodes(nodes: Node[]): Node[] {
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
 * To return an array with any Nodes containing the specified id removed
 * we move through the object, looping recursively through .children and remove
 * any with the id that's being removed
 * @param children - all the Node children
 * @param id - id of the Node to remove
 * @returns - list of children with the Node linked to id removed
 */
export function removeId(children: Node[], id: number): Node[] {
    if (children !== null) {
        // .children wont always be available if the node hasn't been loaded yet
        return children.reduce((previousValue: Node[], currentValue) => {
            if (currentValue.id !== id) {
                previousValue.push({ ...currentValue, children: removeId(currentValue.children as Node[], id) });
            }
            return previousValue;
        }, []);
    }
    return children;
}

/**
 * Get all enabled children
 * @param childNodes - the node.children to get all children of
 * @returns - all included, enabled children
 */
function getAllEnabledChildren(childNodes: Node[] | null): Node[] {
    if (childNodes === undefined || childNodes === null) {
        // ensure we have children
        return [];
    }
    const enabledChildren = childNodes.filter((child) => child.enabled); // only include enabled children
    let children = [...enabledChildren];
    children.forEach((child) => {
        children = [...children, ...getAllEnabledChildren(child.children)];
    });
    return children;
}

/**
 * Include all children
 * @param childNodes - the node.children to get all children of
 * @returns - all included children
 */
export function includeChildren(childNodes: Node[] | null): Node[] {
    if (childNodes === undefined || childNodes === null) {
        // ensure we have children
        return [];
    }
    const enabledChildren = [...getAllEnabledChildren(childNodes)]; // get all enabled children
    return enabledChildren.filter((child) => child.code && child.code !== ""); // then only include children with codes
}

/**
 * we need to check if a new parent has been selected of any existing children,
 * if it has the children should be removed as the parent now takes precedence
 * @param nodes - the nodes to check
 * @param nodeToAdd - the Node to add
 * @returns - no children of selected nodes
 */
export function noChildrenOfNode(nodes: Ref<Node[]>, nodeToAdd: Node): Node[] {
    const unrefNodes = unref(nodes); // to allow both composition (nodes) and options (this.nodes) APIs, as they return different types
    const updatedNodes = unrefNodes.filter((selectedNode) => {
        if (nodeToAdd.children) {
            // only if the children have loaded
            const node = findById(nodeToAdd.children, selectedNode.id);
            return node === undefined; // only keep those nodes that aren't a child of the new node
        }
        // otherwise no existing nodes will be children, as they haven't been loaded and so couldn't have been selected
        return true;
    });
    return updatedNodes;
}

/**
 * Recursively loop through the parents adding in a breadcrumb for each
 * @param node - the Node to traverse for breadcrumbs
 * @param currentBreadcrumbs - the current breadcrumbs
 * @returns - the new breadcrumbs
 */
export function getBreadcrumbs(node: Node, currentBreadcrumbs: Breadcrumb[]): Breadcrumb[] {
    if (node.parent !== null) {
        // if there's a parent to use as a breadcrumb
        let breadcrumbs = [{ id: node.parent.id, name: node.parent.name }, ...currentBreadcrumbs]; // add it in to the front of the list
        breadcrumbs = getBreadcrumbs(node.parent, breadcrumbs);
        return breadcrumbs; // and then check the next node up the list
    }
    // otherwise we must be at the top of the breadcrumb list
    return currentBreadcrumbs; // so we can return now
}

/**
 * To determine if there's any usage assigned on the node
 * @param node - the node to check
 * @returns - whether there's any usage assigned on the node
 */
export function hasUsage(node: Node): boolean {
    if (
        node._usageCounts.course > 0 ||
        node._usageCounts.unit > 0 ||
        node._usageCounts.assessment > 0 ||
        node._usageCounts.badge > 0
    ) {
        return true;
    }
    return false;
}

/**
 * Get the total usage linked to all activities
 * @param node - the node to check
 * @returns - the total count
 */
export function getTotalUsage(node: Node): number {
    return node._usageCounts.course + node._usageCounts.unit + node._usageCounts.assessment + node._usageCounts.badge;
}

/**
 * To distinguish the activity type
 * @param type - the activity type
 * @returns - the icon to return
 */
export function getUsageIcon(type: ActivityTypes): string {
    const icons = {
        [ActivityTypes.COURSE]: "icon-course-builder",
        [ActivityTypes.UNIT]: "icon-unit",
        [ActivityTypes.LEARNING_ACTIVITY]: "icon-due-work",
        [ActivityTypes.LEARNING_ACTIVITY_PROJECT]: "icon-project",
        [ActivityTypes.LEARNING_ACTIVITY_DUE_WORK]: "icon-due-work",
        [ActivityTypes.LEARNING_ACTIVITY_TASK]: "icon-task",
        [ActivityTypes.LEARNING_ACTIVITY_QUIZ]: "icon-quiz",
        [ActivityTypes.LEARNING_ACTIVITY_LESSON_PLAN]: "icon-lesson-plan",
        [ActivityTypes.LEARNING_ACTIVITY_LTI]: "icon-lti",
        [ActivityTypes.BADGE]: "icon-badge",
        [ActivityTypes.FOLDER]: "icon-folder",
    };
    return icons[type];
}

/**
 * To return the href based on the activity type
 * @param usage - the usage
 * @returns - the icon to return
 */
export function getUsageHref(usage: Usage): string {
    const icons = {
        [ActivityTypes.COURSE]: () => (usage.object as CourseUsageObject).folder._links.view,
        [ActivityTypes.UNIT]: () => (usage.object as UnitUsageObject).folder._links.view,
        [ActivityTypes.LEARNING_ACTIVITY]: () => (usage.object as LearningActivityUsageObject)._links.view,
        [ActivityTypes.BADGE]: () => "#view-badge-details", // opens the badge modal
        [ActivityTypes.FOLDER]: () => "#", // UNIMPLEMENTED
    };
    return icons[usage.discriminator] && icons[usage.discriminator]();
}

/**
 * To only return the usages of the specified type
 * @param usages - the usages
 * @param type - the activity type
 * @returns - the usages of the specified type
 */
export function getSpecificUsages(usages: Usage[], type: ActivityTypes): Usage[] {
    return usages.filter((usage) => usage.discriminator === type);
}

/**
 * To return the hash of the current url as a node id
 * @returns - the node id
 */
export function getIdHash(): number {
    const documentURL = document.URL;
    return Number(documentURL.substring(documentURL.indexOf("#") + 1, documentURL.length));
}

/**
 * a node id could be passed into the page via the hash,
 * if so we need to traverse to this node on load if the hash
 * is a valid node id
 * @returns - whether the hash is valid
 */
export function isValidHash(): boolean {
    const documentURL = document.URL;
    const hashCount = (documentURL.match(/#/g) || []).length; // the number of # found in the url
    const idHash = getIdHash();
    const isIdNumber = Number.isInteger(idHash);
    // we check if there's an #, and the string proceeding it is a number
    if (hashCount === 1 && isIdNumber && idHash !== UNSET_ID) {
        return true;
    }
    return false;
}
