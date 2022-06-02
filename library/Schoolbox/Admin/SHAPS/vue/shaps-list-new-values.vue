<script>

/**
 * Provides the available options that can be bulk modified
 * for the currently selected users
 */

import RevealModal from '@schoolboxVue/components/reveal-modal';


export default {

    components: {
        RevealModal,
    },

    props: {
        srcUserModify: {
            type: String,
            required: true
        },
        courses: {
            type: Array,
            required: true
        },
        selectedRows: {
            type: Array,
            required: true,
        },
        selectedRowsCountWithRowName: {
            type: String,
            required: true,
        },
    },

    emits: ['updated'],

    data () {
        return {
            updateFields: {
                courseIds: [],
            },
            updateFieldsOriginal: {}, //to reset to original values once an update is complete
            isProcessing: false,
            hasUpdated: false,
            hasDataError: false,
            logData: [], //to display the output of the returned action
        }
    },

    computed: {

        /**
         * To strip the <br> from the log data
         *
         * @return { String }
         */
        updatedLogData () {
            return this.logData[0].replace(/<br\s*\/?>/gi, '');
        },

        /**
         * To change when processing
         *
         * @return { String }
         */
        updateButtonText () {
            if (this.isProcessing) {
                return `<em>Processing, please wait...</em>`;
            } else {
                return `Update Selected`;
            }
        },

    },

    mounted () {
        this.updateFieldsOriginal = Object.assign({}, this.updateFields); //to reset to original values
    },

    methods: {

        /**
         * Update the selected users
         */
        modify () {
            this.$refs.modal.open();
            this.hasUpdated = false; //return to its form state
        },

        /**
         * Update the selected users
         */
        async onUpdateUsersSelected () {
            try {
                this.isProcessing = true;
                const response = await fetch(this.srcUserModify, {
                    method: 'POST',
                    body: this.getUpdateUsersFormData(),
                });
                const responseData = await response.json();
                this.checkUpdateResponse(response, responseData);
            } catch (error) {
                console.warn(error);
                this.isProcessing = false;
            } finally {
                this.hasUpdated = true;
                this.isProcessing = false;
                this.updateFields = Object.assign({}, this.updateFieldsOriginal); //reset to original values
                this.$emit('updated');
            }
        },

        /**
         * Set up updating fields to POST
         *
         * @returns { FormData }
         */
        getUpdateUsersFormData () {
            const usersToModifyIDList = this.selectedRows.map(row => row.id);
            const formData = new FormData();
            usersToModifyIDList.forEach((item) => {
                formData.append('users[]', item);
            });
            for (const [key, value] of Object.entries(this.updateFields)) {
                formData.append(key, value);
            }
            return formData;
        },

        /**
         * Check for any issues in the returned update
         *
         * @param { Object }
         * @param { Object }
         */
        checkUpdateResponse (response, responseData) {
            if (response.ok) { //successful response
                this.hasDataError = false;
                this.logData = responseData;
            } else if (!response.ok && response.status === 501) { //otherwise check for 501 error
                this.hasDataError = true;
                this.logData = [responseData.title]; //which comes in with an error message
            } else {
                this.hasDataError = true;
                this.logData = ['Sorry, an unknown error has occurred. Please contact your IT support if the problem persists.'];
            }
        },

        /**
         * Close the modal
         */
        onCloseSelected () {
            this.$refs.modal.close();
        }

    }

}
</script>

<template>
    <reveal-modal ref="modal" size="xlarge" :close-on-background-click="false" :is-close-disabled="isProcessing">
        <Transition name="fade" mode="out-in">
            <div v-if="hasUpdated" class="new-values">
                <div>
                    <div v-if="hasDataError" class="alert-box alert">The Bulk Modify has failed to update. Further details are logged below.</div>
                    <textarea v-html="updatedLogData"></textarea>
                </div>
                <div>
                    <button @click.prevent="onCloseSelected" class="button submit">Close</button>
                </div>
            </div>
            <div v-else class="new-values">
                <div>
                    <h2>Provision {{ selectedRowsCountWithRowName }} selected</h2>
                </div>
                <div>
                    <label>
                        Course Involvement
                        <span data-tooltip class="hint has-tip tip-top"
                              title="One or more campuses that the users will be involved in.
                                Leave blank if you don't want to update their campuses.">?</span>
                    </label>
                    <ul class="option-list">
                        <input id='apply_course-0' name='apply_course[]'
                               value='0' type="checkbox"/>
                        <label class="checkbox-label"
                               for='apply_course-0'>provision only</label>
                        <li v-for="course in courses">
                            <input :id="'apply_course-' + String(course.id)" name='apply_course[]'
                                   :value=course.id v-model="updateFields.courseIds" type="checkbox"/>
                            <label class="checkbox-label"
                                   :for="'apply_course-' + String(course.id)">{{ course.name }}</label>
                        </li>
                    </ul>
                </div>
                <div v-if="selectedRows.length > 1000">
                    <div class="alert-box warning">Please note, as a large number of users has been selected, processing may take some time.</div>
                </div>
                <div class="new-values__full-width">
                    <button @click.prevent="onUpdateUsersSelected" class="button submit" :disabled="isProcessing" v-html="updateButtonText"></button>
                </div>
            </div>
        </Transition>
    </reveal-modal>
</template>

<style lang="scss" scoped>

$toTwoColumns: 1200px;
$toOneColumn: 600px;

.new-values {
    display: grid;
    grid-gap: 0 1.5rem;
    grid-template-columns: repeat(4, 1fr); //four equal columns
    padding: 0.5rem;
    @media (max-width: $toTwoColumns) {
        grid-template-columns: repeat(2, 1fr);
    }
    @media (max-width: $toOneColumn) {
        display: block;
    }
}

.new-values__full-width {
    grid-column: 1 / span 4; //extend full grid width
    @media (max-width: $toTwoColumns) {
        grid-column: 1 / span 2;
    }
}

textarea { //to prevent the potential for a massively long modal
    min-height: 200px;
    max-height: 600px;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

</style>
