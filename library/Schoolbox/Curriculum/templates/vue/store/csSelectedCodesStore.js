/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedCodesStore = defineStore('csSelectedCodesStore', {

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
            return this.codes.map((node) => node.id);
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
                codeToAdd.forEach((code) => this.updateIsSelected(code, true));
                this.codes  = [...this.codes, ...codeToAdd];
            } else {
                this.updateIsSelected(codeToAdd, true);
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
                codeToRemove.forEach((code) => this.updateIsSelected(code, false));
                codeToRemove.forEach((code) => {
                    this.codes = this.codes.filter((selectedCode) => selectedCode.id !== code.id);
                });
            } else {
                this.updateIsSelected(codeToRemove, false);
                this.codes = this.codes.filter((code) => code.id !== codeToRemove.id);
            }
        },

        /**
         * to track whether a node is selected
         * we add an additional property to each node
         *
         * @param { object }
         */
        addIsSelected (code) {
            if (code.isSelected === undefined) {
                code.isSelected = false;
            }
        },

        /**
         * Update the code to the new selected state
         *
         * @param { object }
         * @param { boolean }
         */
        updateIsSelected (code, isSelected) {
            code.isSelected = isSelected;
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
            this.codes  = [];
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

    },

})