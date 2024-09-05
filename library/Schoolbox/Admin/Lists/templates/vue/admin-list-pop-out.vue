<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Update an individual rows pop out options
 * Currently only checkboxes are being considered, can be extended as required
 */

import { mapState, mapActions } from "pinia";
import { useAdminListStore } from "./store/adminListStore";
import { gsap } from "gsap/all";
import saveRows from "./mixins/mixinAdminListSaveRows";
import Toast from "@schoolboxVue/components/toast";

export default {
    components: {
        Toast,
    },

    props: {
        tableName: {
            //the name of the current list, used to submit updates to the DB
            type: String,
            required: true,
        },
    },

    mixins: [saveRows],

    data() {
        return {
            isActive: true,
            originalSelectedRow: {},
        };
    },

    computed: {
        ...mapState(useAdminListStore, {
            selectedRow: (state) => state.selectedRow,
            isOpen: (state) => state.isPopOutOpen,
            headerField: (state) => state.popOutHeaderField, //the header field object
            fields: (state) => state.popOutFields, //the fields to display within the pop out
        }),

        /**
         * When 'changePopOut' is set on the popOutList, we need to consider
         * displaying only those fields linked to the current selection of 'changePopOutOptions'
         * For example on Lists > Roles, changing 'Role Type' will change the list of Permissions available
         * If 'changePopOut' isn't set, we can just return all 'popOutList' fields
         *
         * @returns { Array of Objects }
         */
        changePopOutFields() {
            if (this.headerField.changePopOut && this.selectedRow.id) {
                const selectedRowChangePopOutValue = this.selectedRow[this.headerField.changePopOut]; //eg: for 'Role Type', could be 'Staff' or 'Student'
                const changePopOutOptions = this.headerField.changePopOutOptions[selectedRowChangePopOutValue]; //eg: only the options linked to the selected 'Role Type'
                return this.fields.filter((field) => changePopOutOptions.includes(field.id)); //now only those fields linked to the value
            } else {
                return this.fields; //otherwise when there's no 'changePopOut' just return as normal
            }
        },

        /**
         * Retrieve all checkboxes in the pop out
         *
         * @returns { Array }
         */
        checkboxFieldList() {
            return this.changePopOutFields.filter((field) => field.checkbox);
        },

        /**
         * If there's a few checkboxes in the list, we should show a 'Select All' checkbox
         * for easier selection
         *
         * @returns { Boolean }
         */
        canShowSelectAllCheckbox() {
            return this.checkboxFieldList.length > 2;
        },

        /**
         * Retrieve all selected check boxes
         *
         * @returns { Array }
         */
        selectedCheckboxFieldList() {
            return this.checkboxFieldList.filter((field) => this.selectedRow[field.id]); //will return those checkboxes with a true value
        },

        /**
         * To determine the status of the Select All checkbox
         *
         * @returns { Boolean }
         */
        isAllCheckboxesSelected() {
            return this.selectedCheckboxFieldList.length === this.checkboxFieldList.length; //whether all checkboxes are selected (set as true) or not
        },
    },

    watch: {
        /**
         * To open or close depending on boolean value
         */
        isOpen(newValue) {
            if (newValue === true) {
                this.open();
            } else {
                this.close();
            }
        },
    },

    mounted() {
        // Add event listener for click events on the document to trigger closing the pop out
        document.addEventListener("click", this.handleClickOutside);

        this.$emit("on-pop-out-mounted", this.$refs.innerContainer.clientHeight); //to ensure the parent container is tall enough to display the entire pop out
        this.isActive = false;
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        $(document).foundation("tooltip", "reflow");
    },

    beforeUnmount() {
        // Remove the event listener when the component is unmounted
        document.removeEventListener("click", this.handleClickOutside);
    },

    methods: {
        ...mapActions(useAdminListStore, ["closePopUp", "updateSelectedRow"]),

        /**
         * Animate the view on
         */
        open() {
            // Store the original selected row so we can revert back to it if the user cancels
            this.originalSelectedRow = JSON.parse(JSON.stringify(this.selectedRow));

            this.isActive = true;
            gsap.to(this.$refs.innerContainer, { right: 0, duration: 0.4 });
            this.adjustPosition();
        },

        /**
         * Adjust the position of the component based on the selected row's position
         */
        adjustPosition() {
            this.$nextTick(() => {
                const currentRow = this.$el.parentNode.querySelector(
                    '.list-item-container[data-row-id="' + this.selectedRow.id + '"]',
                );
                if (currentRow) {
                    const tableRect = this.$el.parentNode.getBoundingClientRect();
                    const rowRect = currentRow.getBoundingClientRect();
                    const componentRect = this.$refs.innerContainer.getBoundingClientRect();

                    // The top of the popout, in relation to the top of the table
                    let newTop = rowRect.top - componentRect.y - componentRect.height;

                    // If the top of the table is out of view, ensure that the pop out locks
                    // itself to the top of the screen so it doesn't go out of view
                    if (tableRect.top < 0 && tableRect.top < -newTop) {
                        // Add 5px to create a small gap between the top of the screen and the popout
                        newTop = tableRect.top * -1 + 5;
                    }

                    // Make sure the pop out can't go above the top of the table
                    if (newTop > 0) {
                        this.$refs.innerContainer.style.top = `${newTop}px`;
                    }
                }
            });
        },

        /**
         * Close the view
         */
        close() {
            gsap.to(this.$refs.innerContainer, {
                right: "-100%",
                duration: 0.3,
                onComplete: () => {
                    this.isActive = false;
                    this.$refs.innerContainer.style.top = "0px"; //set back to 0, adjustPosition() can always update on another open
                },
            });
        },

        /**
         * check / uncheck all checkboxes
         */
        onSelectAllSelected(event) {
            this.checkboxFieldList.forEach((field) => {
                this.selectedRow[field.id] = event.target.checked;
            });
        },

        /**
         * Close the pop out
         */
        onCloseSelected() {
            this.closePopUp();
        },

        /**
         * Discard changes and close the pop out
         */
        onDiscardChangesSelected() {
            for (const [key, value] of Object.entries(this.originalSelectedRow)) {
                this.selectedRow[key] = value;
            }
            this.closePopUp();
        },

        /**
         * Handle click events on the document
         * @param {MouseEvent} event The click event
         */
        handleClickOutside(event) {
            // Disable the event if the pop out is saving, inactive, or not open
            if (this.saveRows_isProcessing || !this.isActive || !this.isOpen) {
                return;
            }
            const container = this.$refs.innerContainer;
            if (container && !container.contains(event.target) && !event.target.classList.contains("update-button")) {
                // Clicked outside the "outer-container", discard changes and close the pop out
                this.onDiscardChangesSelected();
            }
        },
    },
};
</script>

<template>
    <div class="outer-container" :style="`display: ${isActive ? 'block' : 'none'};`">
        <div class="inner-container checklist" ref="innerContainer">
            <button
                href="#close-pop-out"
                class="close-button icon-close"
                @click.prevent="onDiscardChangesSelected"
                :disabled="saveRows_isProcessing"
                title="Discard Changes"
            ></button>
            <ul class="option-list" data-option-list>
                <li v-if="canShowSelectAllCheckbox" style="margin-bottom: 5px">
                    <input
                        id="select-all"
                        type="checkbox"
                        :checked="isAllCheckboxesSelected"
                        :disabled="saveRows_isProcessing"
                        @change="onSelectAllSelected"
                    />
                    <label for="select-all">Select All</label>
                </li>
                <li v-for="(field, index) in changePopOutFields" :key="index">
                    <input
                        :id="field.caption"
                        type="checkbox"
                        v-model="selectedRow[field.id]"
                        :disabled="saveRows_isProcessing"
                    />
                    <label :for="field.caption"
                        >{{ field.caption
                        }}<span
                            v-if="field.tooltip"
                            :class="`hint has-tip tip-${field.tooltipPosition ? field.tooltipPosition : 'top'}`"
                            data-tooltip
                            :title="field.tooltip"
                            >?</span
                        ></label
                    >
                </li>
            </ul>
            <button
                class="save-button"
                type="submit"
                @click.prevent="saveRows_onSaveSelected"
                :disabled="saveRows_isProcessing"
            >
                {{ saveRows_saveStatus }} {{ headerField.caption }}
            </button>
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss" scoped>
@import "@css/core/base/_variables.scss";

.outer-container {
    position: absolute;
    z-index: var(--sbx-depth-default);
    right: 0px;
    top: 0px;
    width: 320px;
    min-height: 100%;
}

.inner-container {
    position: absolute;
    right: -100%;
    top: 0px;
    width: 100%;
    border: 2px solid $form-border-color;
    padding: 1rem;
    background: white;
}

.close-button {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 2px 2px 2px 10px;
    font-size: 1.5rem;
}

.close-button[disabled] {
    background-color: transparent;
    color: inherit;
}

.save-button {
    width: 100%;
}
</style>
