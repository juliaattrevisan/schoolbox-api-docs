/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedCodesStore = defineStore(`csSelectedCodes`, {

    state: () => ({
        codes: [], //the currently selected codes
        activeCode: null, //to show extra info on a selected code in a modal
    }),

    getters: {

        /**
         * Do we have any selected codes?
         *
         * @return { Boolean }
         */
        hasNoCodes () {
            return this.codes.length === 0;
        },

        /**
         * Do we have any selected codes?
         *
         * @return { Boolean }
         */
        hasCodes () {
            return this.codes.length > 0;
        },

        /**
         * To show further info on a selected code
         *
         * @return { Boolean }
         */
        hasActiveCode () {
            return this.activeCode !== null ? true : false;
        },

        /**
         * just return an array of ids, used to compare if a code is selected or not
         *
         * @return { array }
         */
        codesIds () {
            return this.codes.map((code) => code.id);
        },

    },

    actions: {

        /**
         * add a code
         *
         * @param { Object or Array } either a single code, or mulitple can be added
         */
        add (codeToAdd) {
            if (Array.isArray(codeToAdd)) {
                this.codes  = [...this.codes, ...codeToAdd];
            } else {
                this.codes  = [...this.codes, codeToAdd];
            }
        },

        /**
         * remove a code
         *
         * @param { Object }
         */
        remove (codeToRemove) {
            if (Array.isArray(codeToRemove)) {
                codeToRemove.forEach((code) => {
                    this.codes = this.codes.filter((selectedCode) => selectedCode.id !== code.id);
                });
            } else {
                this.codes = this.codes.filter((code) => code.id !== codeToRemove.id);
            }
        },

        /**
         * to check if a code is selected
         *
         * @param { Object }
         *
         * @returns { boolean }
         */
        isSelected (code) {
            return this.codesIds.includes(code.id);
        },

        /**
         * to switch a codes selection status
         *
         * @param { object }
         * @param { boolean }
         */
        switch (code, isChecked) {
            isChecked ? this.add(code) : this.remove(code);
        },

        /**
         * completely update the codes with a new array
         *
         * @param { Aray }
         */
        update (updatedCodes) {
            this.codes  = [...updatedCodes];
        },

        /**
         * clear all the currently selected codes
         */
        empty () {
            this.codes.forEach((node) => this.remove(node));
        },

        /**
         * set the active code, which would prompt the cs-active-selected-code-modal to open
         * and if it's set to null we can listen for it to be set again
         *
         * @param { Object }
         */
        setActiveCode (code) {
            this.activeCode = code;
        },

        /**
         * A code can have .children and .parent properties which aren't always needed
         * and will bloat the selected objects. As such we remove these properties
         * to slim down the object.
         *
         * @param { Object }
         */
         trimCode (code) {
            const copiedCode = { ...code };
            delete copiedCode.children;
            delete copiedCode.parent;
            return copiedCode;
        },

    },

})