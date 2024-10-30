/**
 * To manage the state of the course selector modal
 */

import { defineStore } from "pinia";

export const useModalStore = defineStore(`coseModal`, () => {
    const isOpen = window.Vue.ref(false);

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
        open,
        select,
        close,
    };
});
