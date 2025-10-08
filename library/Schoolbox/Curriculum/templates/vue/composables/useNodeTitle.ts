/**
 * Get the title of a node, adding in the code if it's available
 */

import { hasCode } from "@schoolboxCurriculum/js/nodeUtils";
import type { ComputedRef } from "vue";
import type { Node } from "@schoolboxCurriculum/types/Node";

/**
 * Get the title of a node, adding in the code if it's available
 * @param node - the node to get the title for
 * @returns - the title of the node
 */
export function useNodeTitle(node: Pick<Node, "name" | "code">): {
    title: ComputedRef<string>;
} {
    /**
     * Add in the code if it's available otherwise just show the title
     * @returns - the title of the node
     */
    const title = window.Vue.computed(() => (hasCode(node) ? `[${node.code as string}] ${node.name}` : `${node.name}`));

    return { title };
}
