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
            isProcessing: false,
            hasUpdated: false,
            hasDataError: false,
            logData: [], //to display the output of the returned action
        }
    },

    computed: {

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
        this.setDefaultSelection();
    },

    methods: {
        setDefaultSelection () {
            this.courses.forEach(
                (course) => {
                    this.updateFields.courseIds[course.id] = null;
                }
            )
        },

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
            for (const [key, value] of Object.entries(this.updateFields.courseIds)) {
                if (value) {
                    formData.append('courses[' + key + ']', value);
                }
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
                this.logData = responseData.data.join('\n');
            } else if (!response.ok) {
                this.hasDataError = true;
                this.logData = responseData.data.join('\n'); //which comes in with an error message
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
    <reveal-modal ref="modal" :close-on-background-click="false" :is-close-disabled="isProcessing">
        <Transition name="fade" mode="out-in">
            <div v-if="hasUpdated">
                <div>
                    <div v-if="hasDataError" class="alert-box alert">Error occurred. Further
                        details are logged below.
                    </div>
                    <textarea v-html="logData"></textarea>
                </div>
                <div>
                    <button @click.prevent="onCloseSelected" class="button submit">Close</button>
                </div>
            </div>
            <div v-else>
                <div>
                    <h2>Provision {{ selectedRowsCountWithRowName }} selected</h2>
                    <label>
                        Course Involvement
                    </label>
                    <div v-for="course in courses">
                        <label :for="'apply_course-' + String(course.id)">{{ course.name }}</label>
                        <select :id="'apply_course-' + String(course.id)" :name="'apply_course-' + String(course.id)"
                                v-model="updateFields.courseIds[course.id]">
                            <option :value=null>Unchanged</option>
                            <option value="1">Enroll</option>
                            <option value="0">Remove</option>
                        </select>
                    </div>
                </div>
                <div v-if="selectedRows.length > 1000">
                    <div class="alert-box warning">Please note, as a large number of users has been selected, processing
                        may take some time.
                    </div>
                </div>
                <div>
                    <button @click.prevent="onUpdateUsersSelected" class="button submit" :disabled="isProcessing"
                            v-html="updateButtonText"></button>
                </div>
            </div>
        </Transition>
    </reveal-modal>
</template>

<style lang="scss" scoped>

textarea { //to prevent the potential for a massively long modal
    min-height: 200px;
    max-height: 600px;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */
{
    opacity: 0;
}

</style>
