/**
 * To manage the list of currently selected courses
 */

import { defineStore } from "pinia";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";

export const useSelectedStore = defineStore("courseSelector", () => {
    const { selected, add, remove, switchItem, update, empty } = useSelected();

    return {
        selected,
        add,
        remove,
        switchItem,
        update,
        empty,
    };
});
