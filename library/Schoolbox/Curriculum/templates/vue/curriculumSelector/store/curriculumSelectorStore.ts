/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";
import { useDomContentLoaded } from "@schoolboxVue/composables/events/useDomContentLoaded";
import type { Node } from "@schoolboxCurriculum/types/Node";
/* eslint-disable-next-line import/no-cycle -- Same as [SA-18428] */
import { useNodesStore } from "./csAllNodesStore";
import { useCurrentNodesStore } from "./csCurrentNodesStore";
/* eslint-disable-next-line import/no-cycle -- Same as [SA-18428] */
import { useSelectedNodesStore } from "./csSelectedNodesStore";
import { useSharedStore } from "./csSharedStore";

interface InitProps {
    isReadOnly: boolean;
    formValueName: string;
    dataEndpoint: string;
    selectedNodes: Node[];
    includeChildren?: boolean;
    hasTabs: boolean;
    parentFolderId: number | undefined;
    isSelectAll?: boolean;
    canSelectChildren?: boolean;
    includeWithoutCode?: boolean;
    includeDisabled?: boolean;
    filter?: Node | null;
}

export const useStore = defineStore(`curriculumSelector`, () => {
    const isLoading = window.Vue.ref(true); // to display loading note
    const { hasDomContentLoaded } = useDomContentLoaded(); // to detect when the DOM content is loaded
    const hasLoadError = window.Vue.ref(false); // to display loading error
    const isProcessing = window.Vue.ref(false); // to notify that an action is taking place, like updating a member access
    const formValueName = window.Vue.ref(""); // the name of the form value to update
    const containerWidth = window.Vue.ref(0); // currently so can tweak the styling when the selector is small
    const selectedOriginal = window.Vue.ref([]); // an 'original' selection, saved by the user
    const selectedDefault = window.Vue.ref([]); // the default selection relevant to the current user role
    const isSelectAll = window.Vue.ref(false); // to select any nodes, not just those that have a code
    const canSelectChildren = window.Vue.ref(true); // is able to select children of an already selected node
    const includeWithoutCode = window.Vue.ref(false); // set to true if curriculum nodes with blank codes need to be selectable
    const includeChildren = window.Vue.ref(false); // When a node is selected, also select all its children
    const includeDisabled = window.Vue.ref(false); // whether to display disabled nodes
    const sharedStore = useSharedStore(); // the store to load the shared data from
    const currentNodesStore = useCurrentNodesStore(); // the store to load the current nodes from

    /**
     * When all loading is complete and was a success
     */
    const hasLoadedOk = window.Vue.computed(() => {
        if (!sharedStore.hasTabs || currentNodesStore.parentFolderId === undefined) {
            return !isLoading.value && !hasLoadError.value;
        }
        return (
            hasDomContentLoaded.value &&
            !isLoading.value &&
            !hasLoadError.value &&
            currentNodesStore.hasLoaded &&
            !currentNodesStore.hasLoadError
        );
    });

    /**
     * To tweak the design of the selector when small
     */
    const isSmallContainer = window.Vue.computed(() => {
        return containerWidth.value < 500;
    });

    /**
     * set up the passed properties and start loading the node data
     * @param props - the properties to set
     */
    function init(props: InitProps): void {
        useSharedStore().init({
            apiEndpoint: props.dataEndpoint,
            isReadOnly: props.isReadOnly,
            hasTabs: props.hasTabs,
        });
        formValueName.value = props.formValueName;
        isSelectAll.value = props.isSelectAll ? props.isSelectAll : false;
        canSelectChildren.value = props.canSelectChildren ? props.canSelectChildren : true;
        includeWithoutCode.value = props.includeWithoutCode ? props.includeWithoutCode : false;
        includeChildren.value = props.includeChildren ? props.includeChildren : false;
        includeDisabled.value = props.includeDisabled ? props.includeDisabled : false;
        if (!props.isReadOnly && props.dataEndpoint !== "") {
            // only load when we need to
            void useNodesStore().load();
        }
        // load in the current nodes, only displaying the tabs if there are any to display
        if (props.hasTabs && props.parentFolderId !== undefined) {
            currentNodesStore.init({
                filter: props.filter ? props.filter : null,
                parentFolderId: props.parentFolderId,
            });
            void currentNodesStore.load();
        }
        if (props.selectedNodes?.length > 0) {
            // add in any nodes that have been selected previously
            const selectedNodesStore = useSelectedNodesStore();
            props.selectedNodes.forEach((node) => selectedNodesStore.add(node));
        }
    }

    /**
     * to track the width of the container the curriculum selector is in,
     * so we can update the design when appropriate
     * @param width - the width of the container
     */
    function updateContainerWidth(width: number): void {
        containerWidth.value = width;
    }

    return {
        isLoading,
        hasLoadError,
        isProcessing,
        formValueName,
        containerWidth,
        selectedOriginal,
        selectedDefault,
        isSelectAll,
        canSelectChildren,
        includeWithoutCode,
        includeChildren,
        includeDisabled,
        hasLoadedOk,
        isSmallContainer,
        currentNodesStore,
        init,
        updateContainerWidth,
    };
});
