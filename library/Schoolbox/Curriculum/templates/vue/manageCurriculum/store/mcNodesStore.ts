/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import {
    UNSET_ID,
    findById,
    sortByName,
    removeId,
    getBreadcrumbs,
    getEmptyNode,
} from '@schoolboxCurriculum/js/nodeUtils';
import { useStore } from '@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore';
import { useViewNodesStore } from '@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore';
import { useSelectedNodesStore } from '@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore';
import { Node } from '@schoolboxCurriculum/types/Node';
import { Breadcrumb } from '@schoolboxCurriculum/types/Breadcrumb';

export const useNodesStore = defineStore('mcNodes', () => {
    const nodes = ref<Node>(getEmptyNode()); // all the nodes currently loaded
    const isLoading = ref(false); // to display loading note
    const hasLoadError = ref(false); // to display loading error

    /**
     * If there's only no Curriculum, display an empty state
     *
     * @returns { boolean }
     */
    const hasNoCurriculum = computed(() => nodes.value?.children && nodes.value.children.length === 0);

    /**
     * If there's only one curriculum, we can select it
     * straight away, otherwise we allow the user to make the selection
     *
     * @returns { boolean }
     */
    const hasSingleCurriculum = computed(() => nodes.value?.children && nodes.value?.children.length === 1);

    /**
     * Update the nodes when a change has occured, either from the initial load or adding or deleting them
     *
     * @param { Node[] } $nodes - the updated nodes
     */
    function updateNodes($nodes: Node[]) {
        // we add them to .children so the top level structure is consistent with the levels below
        nodes.value = {
            children: $nodes,
            code: null,
            description: null,
            enabled: true,
            id: UNSET_ID,
            parent: null,
            name: 'Curricula',
        };
        $nodes.forEach((node) => useSelectedNodesStore().checkIfSelected(node)); // ensure isSelected is kept up to date
    }

    /**
     * load the single curriculum for display straight away
     */
    async function loadSingleCurriculum() {
        if (nodes.value?.children !== null) {
            const singleCurriculumId: number = nodes.value?.children[0]?.id;
            await useViewNodesStore().setNextSelected(singleCurriculumId);
        }
    }

    /**
     * to only load the nodes that aren't already active
     *
     * @param { Breadcrumb[] } activeBreadcrumbs - the active breadcrumbs
     * @param { Breadcrumb } newBreadcrumb - breadcrumb to add
     * @returns { boolean } whether the breadcrumb is active
     */
    function isActiveBreadcrumb(activeBreadcrumbs: Breadcrumb[], newBreadcrumb: Breadcrumb) {
        return !activeBreadcrumbs.some((activeBreadcrumb) => newBreadcrumb.id === activeBreadcrumb.id);
    }

    /**
     * load in all the children required to reach the depth of the node,
     * avoiding loading those nodes that are already loaded
     *
     * @todo - this setup currently assumes we are already in the node tree of the passed through node
     * it currently doesnt handle an entirely new node tree
     * @param { Node } node - the Node to load all children of
     */
    async function loadAllChildren(node: Node) {
        const breadcrumbs = getBreadcrumbs(node, []);
        breadcrumbs.push({ id: node.id, name: node.name }); // add in the actual node as the last
        const viewNodesStore = useViewNodesStore();
        const activeBreadcrumbs: Breadcrumb[] = [...viewNodesStore.breadcrumbs, { id: viewNodesStore.current.id }];
        const onlyInactiveBreadcrumbs = breadcrumbs.filter((newBreadcrumb: Breadcrumb) =>
            isActiveBreadcrumb(activeBreadcrumbs, newBreadcrumb)
        );
        for (let i = 0; i < onlyInactiveBreadcrumbs.length; i++) {
            // recursively load in each node and traverse down
            await useViewNodesStore().setNextSelected(onlyInactiveBreadcrumbs[i].id);
        }
    }

    /**
     * load the node, retrieving all its parents so we can traverse directly to it
     *
     * @param { number } id - the id of the Node to load
     */
    async function loadId(id: number) {
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
                    throw new Error(`No node id matching ${id} has been found`);
                }
            }
        } catch (error) {
            hasLoadError.value = true;
            throw new Error(error.message, error.options);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * set the top level nodes, passed through from loading
     *
     * @param { Node[] } $nodes - the node to load
     */
    async function setTopLevel($nodes: Node[]) {
        updateNodes($nodes);
        const store = useStore();
        if (store.hasId) {
            await loadId(store.id);
        } else if (hasSingleCurriculum.value) {
            // we jump straight to the only node available if there's only one
            await loadSingleCurriculum();
        }
    }

    /**
     * load in the top level nodes from the api
     */
    async function load() {
        const store = useStore();
        try {
            const response = await fetch(`${store.getApiEndpoint()}/?topLevelOnly=1&descendants=1&limit=50`, {
                headers: {
                    'Content-Type': 'application/json',
                },
            });
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
     *
     * @param { number } id - the id of the Node to children of
     */
    async function loadNextChildren(id: number) {
        try {
            isLoading.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/${id}?descendants=2&limit=50`, {
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            const responseJson = await response.json();
            if (response.ok) {
                const newNode = responseJson;
                newNode.children.forEach((node: Node) => useSelectedNodesStore().checkIfSelected(node));
                const oldNode = findById(nodes.value?.children, id); // grab the old node to update
                useSelectedNodesStore().checkIfSelected(oldNode);
                oldNode.children = newNode.children; // we just grab the loaded children and overwrite the old property
            } else {
                throw new Error(responseJson.title, { ...responseJson });
            }
        } catch (error) {
            hasLoadError.value = true;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * A new curriculum has been added so add it to the nodes
     *
     * @param { Node } curriculum - the new root curriculum node
     */
    function addNewCurriculum(curriculum: Node) {
        nodes.value?.children?.push(curriculum);
        sortByName(nodes.value?.children as Node[]);
    }

    /**
     * A node has been updated, so we should check if the name change would prompt a sort change
     *
     * @param { Node } node - the node that has been updated
     */
    function nodeUpdated(node: Node) {
        if (node.parent) {
            // sort via the parent node
            const parentNode = findById(nodes.value?.children as Node[], node.parent.id) as Node;
            sortByName(parentNode.children as Node[]);
        } else {
            // otherwise sort on the children
            sortByName(nodes.value?.children as Node[]);
        }
    }

    /**
     * Pull the node out of the list
     *
     * @param { Node } node - the node to delete
     */
    function deleteNode(node: Node) {
        useViewNodesStore().checkDeletedNode(node); // check we aren't on the deleted node currently, if so we should traverse backwards one depth
        const nodeRemoved: Node[] = removeId(nodes.value.children as Node[], node.id);
        updateNodes(nodeRemoved);
    }

    return {
        nodes,
        isLoading,
        hasLoadError,
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
