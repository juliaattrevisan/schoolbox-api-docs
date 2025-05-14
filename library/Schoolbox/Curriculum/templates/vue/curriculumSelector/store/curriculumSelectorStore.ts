/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
/* eslint-disable-next-line import/no-cycle -- Same as [SA-18428] */
import { useNodesStore } from "./csAllNodesStore";
import { useUnitNodesStore } from "./csUnitNodesStore";
/* eslint-disable-next-line import/no-cycle -- Same as [SA-18428] */
import { useSelectedNodesStore } from "./csSelectedNodesStore";
import { useSharedStore } from "./csSharedStore";

interface Props {
    isReadOnly: boolean;
    formValueName: string;
    dataEndpoint: string;
    selectedNodes: Node[];
    includeChildren: boolean;
    hasTabs: boolean;
    folderId: number | undefined;
    isSelectAll: boolean;
    canSelectChildren: boolean;
    includeWithoutCode: boolean;
    includeDisabled: boolean;
}

export const useStore = defineStore(`curriculumSelector`, () => {
    const isLoading = window.Vue.ref(true); // to display loading note
    const hasLoadError = window.Vue.ref(false); // to display loading error
    const isProcessing = window.Vue.ref(false); // to notify that an action is taking place, like updating a member access
    const isReadOnly = window.Vue.ref(true); // can only view the currently selected codes, not edit them
    const formValueName = window.Vue.ref(""); // the name of the form value to update
    const containerWidth = window.Vue.ref(0); // currently so can tweak the styling when the selector is small
    const selectedOriginal = window.Vue.ref([]); // an 'original' selection, saved by the user
    const selectedDefault = window.Vue.ref([]); // the default selection relevant to the current user role
    const isSelectAll = window.Vue.ref(false); // to select any nodes, not just those that have a code
    const canSelectChildren = window.Vue.ref(true); // is able to select children of an already selected node
    const includeWithoutCode = window.Vue.ref(false); // set to true if curriculum nodes with blank codes need to be selectable
    const includeChildren = window.Vue.ref(false); // When a node is selected, also select all its children
    const includeDisabled = window.Vue.ref(false); // whether to display disabled nodes
    const hasTabs = window.Vue.ref(false); // whether to display tabs
    const folderId = window.Vue.ref<number | undefined>(undefined); // the folderId to load the unit nodes from

    /**
     * When all loading is complete and was a success
     */
    const hasLoadedOk = window.Vue.computed(() => {
        if (!hasTabs.value || folderId.value === undefined) {
            return !isLoading.value && !hasLoadError.value;
        }
        return (
            !isLoading.value &&
            !hasLoadError.value &&
            useUnitNodesStore().hasLoaded &&
            !useUnitNodesStore().hasLoadError
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
    function init(props: Props): void {
        useSharedStore().init({ apiEndpoint: props.dataEndpoint });
        isReadOnly.value = props.isReadOnly;
        formValueName.value = props.formValueName;
        isSelectAll.value = props.isSelectAll;
        canSelectChildren.value = props.canSelectChildren;
        includeWithoutCode.value = props.includeWithoutCode;
        includeChildren.value = props.includeChildren;
        includeDisabled.value = props.includeDisabled;
        hasTabs.value = props.hasTabs;
        folderId.value = props.folderId;
        if (!isReadOnly.value && props.dataEndpoint !== "") {
            // only load when we need to
            void useNodesStore().load();
        }
        // load in the unit nodes, only displaying the tabs if there are any unit nodes
        if (props.hasTabs && props.folderId !== undefined) {
            void useUnitNodesStore().load(props.folderId);
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
        isReadOnly,
        formValueName,
        containerWidth,
        selectedOriginal,
        selectedDefault,
        isSelectAll,
        canSelectChildren,
        includeWithoutCode,
        includeChildren,
        includeDisabled,
        hasTabs,
        folderId,
        hasLoadedOk,
        isSmallContainer,
        init,
        updateContainerWidth,
    };
});
