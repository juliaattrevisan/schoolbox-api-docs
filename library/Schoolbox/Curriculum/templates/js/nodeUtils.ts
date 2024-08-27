/**
 * Various utils related to managing curriculum nodes
 */

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
 * @returns {Node} the empty Node
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
 * @param {Node[]} array - an array of Node to loop through
 * @param {number} id - the id to find
 * @returns {Node | undefined} - the found Node
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
 * @param {Node} aNode - the first node to compare
 * @param {Node} bNode - the second node to compare
 * @returns {number} - the number to determine sorting
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
 * @param {Node[]} nodes - the nodes to sort
 * @returns {Node[]} - the sorted nodes
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
 * @param {Node[]} children - all the Node children
 * @param {number} id - id of the Node to remove
 * @returns {Node[]} - list of children with the Node linked to id removed
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
 * Recursively loop through the parents adding in a breadcrumb for each
 * @param {Node} node - the Node to traverse for breadcrumbs
 * @param {Breadcrumb[]} currentBreadcrumbs - the current breadcrumbs
 * @returns {Breadcrumb[]} - the new breadcrumbs
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
 * @param {Node} node - the node to check
 * @returns {boolean} - whether there's any usage assigned on the node
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
 * @param {Node} node - the node to check
 * @returns {number} - the total count
 */
export function getTotalUsage(node: Node): number {
    return node._usageCounts.course + node._usageCounts.unit + node._usageCounts.assessment + node._usageCounts.badge;
}

/**
 * To distinguish the activity type
 * @param {ActivityTypes} type - the activity type
 * @returns {string} - the icon to return
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
 * @param {Usage} usage - the usage
 * @returns {string} - the icon to return
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
 * @param {Usage[]} usages - the usages
 * @param {ActivityTypes} type - the activity type
 * @returns {Usage[]} - the usages of the specified type
 */
export function getSpecificUsages(usages: Usage[], type: ActivityTypes): Usage[] {
    return usages.filter((usage) => usage.discriminator === type);
}

/**
 * To return the hash of the current url as a node id
 * @returns {number} - the node id
 */
export function getIdHash(): number {
    const documentURL = document.URL;
    return Number(documentURL.substring(documentURL.indexOf("#") + 1, documentURL.length));
}

/**
 * a node id could be passed into the page via the hash,
 * if so we need to traverse to this node on load if the hash
 * is a valid node id
 * @returns {boolean} - whether the hash is valid
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
