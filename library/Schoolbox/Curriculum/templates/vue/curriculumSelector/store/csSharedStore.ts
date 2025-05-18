/**
 * Handles any shared state between the curriculumSelector stores
 */

import { defineStore } from "pinia";

interface Props {
    apiEndpoint: string;
}

export const useSharedStore = defineStore(`csSharedStore`, () => {
    const apiEndpoint = window.Vue.ref(""); // the endpoint to load the curriculum data from

    /**
     * Initialises the shared store with the given properties
     * @param props - the properties to set
     */
    function init(props: Props): void {
        apiEndpoint.value = props.apiEndpoint;
    }

    return {
        apiEndpoint,
        init,
    };
});
