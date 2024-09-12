/**
 * Stores all the curriculum codes and determines display of nodes
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import {
    UNSET_ID,
    findById,
    sortNodes,
    removeId,
    getBreadcrumbs,
    getEmptyNode,
} from "@schoolboxCurriculum/js/nodeUtils";
import { useStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

export const useNodesStore = defineStore("mcNodes", () => {
    const nodes = window.Vue.ref<Node>(getEmptyNode()); // all the nodes currently loaded
    const isLoading = window.Vue.ref(false); // to display loading note
    const hasLoadError = window.Vue.ref(false); // to display loading error
    const isLoadingAllChildren = window.Vue.ref(false); // to distinguish when we are traversing through multiple trees

    /**
     * If there's only no Curriculum, display an empty state
     * @returns { boolean }
     */
    const hasNoCurriculum = window.Vue.computed(() => nodes.value?.children && nodes.value.children.length === 0);

    /**
     * If there's only one curriculum, we can select it
     * straight away, otherwise we allow the user to make the selection
     * @returns { boolean }
     */
    const hasSingleCurriculum = window.Vue.computed(() => nodes.value?.children && nodes.value?.children.length === 1);

    /**
     * Update the nodes when a change has occured, either from the initial load or adding or deleting them
     * @param $nodes - the updated nodes
     */
    function updateNodes($nodes: Node[]): void {
        // we add them to .children so the top level structure is consistent with the levels below
        nodes.value = {
            children: $nodes,
            code: null,
            description: null,
            enabled: true,
            id: UNSET_ID,
            order: 0,
            parent: null,
            name: "Curricula",
            _links: {
                delete: "",
                getUsage: "",
                read: "",
                update: "",
            }, // TODO: ... review this, it seems like it could lead to a bug
            _usageCounts: {
                course: 0,
                unit: 0,
                assessment: 0,
                badge: 0,
                folder: 0,
            },
        };
        $nodes.forEach((node) => useSelectedNodesStore().checkIfSelected(node)); // ensure isSelected is kept up to date
    }

    /**
     * load the single curriculum for display straight away
     */
    async function loadSingleCurriculum(): Promise<void> {
        if (nodes.value?.children !== null) {
            const singleCurriculumId: number = nodes.value?.children[0]?.id;
            await useViewNodesStore().setNextSelected(singleCurriculumId);
        }
    }

    /**
     * to only load the nodes that aren't already active
     * @param activeBreadcrumbs - the active breadcrumbs
     * @param newBreadcrumb - breadcrumb to add
     * @returns whether the breadcrumb is active
     */
    function isActiveBreadcrumb(activeBreadcrumbs: Breadcrumb[], newBreadcrumb: Breadcrumb): boolean {
        return !activeBreadcrumbs.some((activeBreadcrumb) => newBreadcrumb.id === activeBreadcrumb.id);
    }

    /**
     * load in all the children required to reach the depth of the node,
     * avoiding loading those nodes that are already loaded
     * @param node - the Node to load all children of
     */
    async function loadAllChildren(node: Node): Promise<void> {
        isLoadingAllChildren.value = true;
        const breadcrumbs = getBreadcrumbs(node, []);
        breadcrumbs.push({ id: node.id, name: node.name }); // add in the actual node as the last
        const viewNodesStore = useViewNodesStore();
        const activeBreadcrumbs: Breadcrumb[] = [
            ...viewNodesStore.breadcrumbs,
            { id: viewNodesStore.current.id, name: viewNodesStore.current.name },
        ];
        const onlyInactiveBreadcrumbs = breadcrumbs.filter((newBreadcrumb: Breadcrumb) =>
            isActiveBreadcrumb(activeBreadcrumbs, newBreadcrumb),
        );

        // recursively load in each node and traverse down
        await onlyInactiveBreadcrumbs.reduce(async (prevPromise, breadcrumb) => {
            await prevPromise;
            // if we are at the last breadcrumb, we are no longer loading all children
            if (breadcrumb.id === node.id) {
                isLoadingAllChildren.value = false;
            }
            return useViewNodesStore().setNextSelected(breadcrumb.id);
        }, Promise.resolve());
    }

    /**
     * load the node, retrieving all its parents, so we can traverse directly to it
     * @param id - the id of the Node to load
     */
    async function loadId(id: number): Promise<void> {
        try {
            isLoading.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/${id}?ancestors=99`); // just use 99 as hopefully there's no tree that deep...
            if (response.ok) {
                const nodeWithParents = (await response.json()) as Node;
                await loadAllChildren(nodeWithParents);
            } else {
                useStore().hasId = false; // as the id couldn't be found
                if (hasSingleCurriculum.value) {
                    // we should still jump straight to the only node available if there's only one
                    await loadSingleCurriculum();
                }
                if (response.status === 404) {
                    // and we assume the id can't be found, displaying an error
                    throw new Error(`No outcome id matching ${id} has been found`);
                }
            }
        } catch (error) {
            hasLoadError.value = true;
            throw new Error((error as Error).message);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * set the top level nodes, passed through from loading
     * @param $nodes - the node to load
     */
    async function setTopLevel($nodes: Node[]): Promise<void> {
        updateNodes($nodes);
        const store = useStore();
        if (store.hasId) {
            // if we have an id passed via the url hash, we load that
            await loadId(store.id);
        } else if (hasSingleCurriculum.value) {
            // we jump straight to the only node available if there's only one
            await loadSingleCurriculum();
        }
    }

    /**
     * load in the top level nodes from the api
     */
    async function load(): Promise<void> {
        const store = useStore();
        try {
            const response = await fetch(
                `${store.getApiEndpoint()}/?topLevelOnly=1&descendants=1&limit=${store.getUILimit()}`,
                {
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );
            if (response.ok) {
                const topLevelNodes = (await response.json()) as Node[];
                await setTopLevel(topLevelNodes);
            } else {
                store.hasLoadError = true; // show the load error display state
            }
        } catch (error) {
            store.hasLoadError = true; // show the load error display state
        } finally {
            store.isLoading = false;
        }
    }

    /**
     * Load in another level of children of the target node id
     * @param id - the id of the Node to children of
     */
    async function loadNextChildren(id: number): Promise<void> {
        try {
            isLoading.value = true;
            const response = await fetch(
                `${useStore().getApiEndpoint()}/${id}?descendants=2&limit=${useStore().getUILimit()}`,
                {
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );
            const responseJson = (await response.json()) as Node | { title: string };
            if (response.ok) {
                const newNode = responseJson as Node;
                newNode.children?.forEach((node: Node) => useSelectedNodesStore().checkIfSelected(node));
                const oldNode = findById(nodes.value?.children as Node[], id) as Node; // grab the old node to update
                useSelectedNodesStore().checkIfSelected(oldNode);
                oldNode.children = newNode.children; // we just grab the loaded children and overwrite the old property
            } else {
                throw new Error((responseJson as { title: string }).title);
            }
        } catch (error) {
            hasLoadError.value = true;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * A new curriculum has been added so add it to the nodes
     * @param curriculum - the new root curriculum node
     */
    function addNewCurriculum(curriculum: Node): void {
        nodes.value?.children?.push(curriculum);
        nodes.value.children = sortNodes(nodes.value.children as Node[]);
    }

    /**
     * A node has been updated, so we should check if the name change would prompt a sort change
     * @param node - the node that has been updated
     */
    function nodeUpdated(node: Node): void {
        if (node.parent) {
            // sort via the parent node
            const parentNode = findById(nodes.value?.children as Node[], node.parent.id) as Node;
            parentNode.children = sortNodes(parentNode.children as Node[]);
        } else {
            // otherwise sort on the children
            nodes.value.children = sortNodes(nodes.value.children as Node[]);
        }
    }

    /**
     * Pull the node out of the list
     * @param node - the node to delete
     */
    function deleteNode(node: Node): void {
        useViewNodesStore().checkDeletedNode(node); // check we aren't on the deleted node currently, if so we should traverse backwards one depth
        const nodeRemoved: Node[] = removeId(nodes.value.children as Node[], node.id);
        updateNodes(nodeRemoved);
    }

    return {
        nodes,
        isLoading,
        hasLoadError,
        isLoadingAllChildren,
        hasNoCurriculum,
        hasSingleCurriculum,
        load,
        loadId,
        loadNextChildren,
        addNewCurriculum,
        nodeUpdated,
        deleteNode,
    };
});
