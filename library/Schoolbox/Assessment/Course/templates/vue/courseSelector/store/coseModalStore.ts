/**
 * To manage the state of the course selector modal
 */

import { defineStore } from "pinia";

export const useModalStore = defineStore(`coseModal`, () => {
    let endpoint = ""; // the endpoint to search for courses
    const isOpen = window.Vue.ref(false);

    /**
     * Open the modal
     * @param $endpoint - the endpoint to search for courses
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    /**
     * Open the modal
     */
    function open(): void {
        isOpen.value = true;
    }

    /**
     * select the modal options
     */
    function select(): void {}

    /**
     * close the modal
     */
    function close(): void {
        isOpen.value = false;
    }

    return {
        isOpen,
        init,
        open,
        select,
        close,
    };
});
