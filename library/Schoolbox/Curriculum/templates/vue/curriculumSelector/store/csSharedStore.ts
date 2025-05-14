/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";

interface Props {
    apiEndpoint: string;
}

export const useSharedStore = defineStore(`csSharedStore`, () => {
    const apiEndpoint = window.Vue.ref(""); // the endpoint to load the curriculum data from

    /**
     * set up the passed properties and start loading the node data
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
