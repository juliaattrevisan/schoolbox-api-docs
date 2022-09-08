/**
 * Manages the currently selected codes relevant to the curent modal session
 */

import { defineStore } from 'pinia';
import { useSelectedCodesStore } from './csSelectedCodesStore';


export const useModalSelectedCodesStore = defineStore(`csModalSelectedCodes`, {

    state: () => ({
        codes: [], //the currently selected codes in the current modal session, with any previously selected also considered
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
         * populate with the currently selected codes
         */
        refresh () {
            this.codes = useSelectedCodesStore().codes; //ensure current selections are reflected in the new modal session
        },

        /**
         * add a code
         *
         * @param { Object }
         */
        add (codeToAdd) {
            this.codes  = [...this.codes, codeToAdd];
        },

        /**
         * remove a code
         *
         * @param { Object }
         */
        remove (codeToRemove) {
            this.codes = this.codes.filter((code) => code.id !== codeToRemove.id);
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
         * to switch a nodes selection status
         *
         * @param { object }
         * @param { boolean }
         */
        switch (node, isChecked) {
            isChecked ? this.add(node) : this.remove(node);
        },

        /**
         * clear all the currently selected codes
         */
        empty () {
            this.codes.forEach((node) => this.remove(node));
        },

        /**
         * update the currently selected with the latest modal session
         */
        update () {
            useSelectedCodesStore().update(this.codes);
        },

    },

})