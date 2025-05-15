/**
 * Handles any shared state between the curriculumSelector stores
 */

import { defineStore } from "pinia";

interface InitProps {
    apiEndpoint: string;
    hasTabs: boolean;
}

export const useSharedStore = defineStore(`csSharedStore`, () => {
    const apiEndpoint = window.Vue.ref(""); // the endpoint to load the curriculum data from
    const hasTabs = window.Vue.ref(false); // whether to display tabs

    /**
     * Initialises the shared store with the given properties
     * @param props - the properties to set
     */
    function init(props: InitProps): void {
        apiEndpoint.value = props.apiEndpoint;
        hasTabs.value = props.hasTabs;
    }

    return {
        apiEndpoint,
        hasTabs,
        init,
    };
});
