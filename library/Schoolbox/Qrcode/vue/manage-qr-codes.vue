<script>
/**
 * Generate a list of QR login codes for the current class / group.
 * Using the QR code, the student can login and be redirected to the specified URL.
 * The codes are set to a specific expiry.
 * By default all users have the QR login code generated, however select students can also be chosen.
 */

import ManageQrCodesStudent from "./manage-qr-codes-student";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer";

export default {
    components: {
        ManageQrCodesStudent, //to generate and display QR codes
        BottomDrawer, //to provide submit actions
    },

    props: {
        endpointGenerate: {
            //the url to request that generates the QR codes
            type: String,
            required: true,
        },
        folderNameWithCode: {
            //the class name, to show in the print styles below the student name
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            isProcessing: true, //to disable various actions while active
            hasGeneratedCodes: false, //to enable printing when codes are available
            students: [], //the entire list of students
            selectedStudents: [], //those students currently selected
            destination: "homepage", //where to redirect the student on login
            expiry: "", //when the returned qr code will expire
            expiryMonth: "1", //how long to set the login expiry for
        };
    },

    computed: {
        /**
         * No qr codes to show if there's no students, so show a 'no students' display
         *
         * @returns { Boolean }
         */
        hasStudents() {
            return this.students.length > 0;
        },

        /**
         * To toggle the bottom-drawer, only show when there's students to submit
         *
         * @returns { Boolean }
         */
        hasSelectedStudents() {
            return this.selectedStudents.length > 0;
        },

        /**
         * To display how many students are selected
         *
         * @returns { Number }
         */
        selectedStudentsCount() {
            return this.selectedStudents.length;
        },

        /**
         * To display how many students are selected
         *
         * @returns { String }
         */
        selectedStudentsStatus() {
            if (this.students.length === this.selectedStudentsCount) {
                return "All students selected";
            } else if (this.selectedStudentsCount === 0) {
                return "No students selected";
            } else if (this.selectedStudentsCount === 1) {
                return "1 student selected";
            } else {
                return `${this.selectedStudentsCount} students selected`;
            }
        },

        /**
         * To update the toggle selection status based on the current selection
         *
         * @returns { String }
         */
        toggleStudentSelectionStatus() {
            if (this.selectedStudentsCount > 0) {
                return "Clear selection";
            } else {
                return "Select all";
            }
        },

        /**
         * To disable the submit button while already processing or
         * when there's no students currently selected
         *
         * @returns { Boolean }
         */
        isGenerateCodesDisabled() {
            if (this.isProcessing || !this.hasSelectedStudents) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * To notify that codes are currently being generated
         *
         * @returns { String }
         */
        generateCodesStatus() {
            if (this.isProcessing && this.isDataLoaded) {
                return "Generating codes...";
            } else {
                return "Generate codes";
            }
        },
    },

    mounted() {
        this.loadStudents();
    },

    methods: {
        /**
         * Load the current list of students
         */
        async loadStudents() {
            try {
                const response = await fetch(this.endpointGenerate, {
                    method: "POST",
                    body: [],
                });
                const data = await response.json();
                this.students = data.students;
                this.selectedStudents = this.students; //all students are selected by default
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list or error
                this.isProcessing = false; //to update to student display
            }
        },

        /**
         * To determine the checked/unchecked display of the checkbox on the student
         *
         * @param { Number }
         *
         * @returns { Boolean }
         */
        isInSelectedStudents(studentId) {
            return this.selectedStudents.some((student) => student.id === studentId);
        },

        /**
         * Update the selected student list with the selected or unselected student
         *
         * @params { Object }
         */
        onStudentSelected(student) {
            if (this.isInSelectedStudents(student.id)) {
                this.selectedStudents = this.selectedStudents.filter(
                    (selectedStudent) => selectedStudent.id !== student.id,
                ); //remove if in the list
            } else {
                this.selectedStudents = [student, ...this.selectedStudents]; //otherwise add them in
            }
        },

        /**
         * Generate the QR codes for the selected students
         */
        async onGenerateCodesSelected() {
            try {
                this.isProcessing = true;
                const response = await fetch(this.endpointGenerate, {
                    method: "POST",
                    body: this.getFormData(),
                });
                const data = await response.json();
                this.expiry = data.expiry;
                this.students = data.students;
                this.selectedStudents = []; //clear the selected students once generated once
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = false;
                this.hasGeneratedCodes = true; //enable the print button
            }
        },

        /**
         * Get the FormData to submit to the QR generation endpoint
         *
         * @returns { FormData }
         */
        getFormData() {
            const formData = new FormData();
            this.selectedStudents.forEach((student) => {
                formData.append("students[]", student.id);
            });
            formData.append("destination", this.destination);
            formData.append("expiry", this.expiryMonth);
            return formData;
        },

        /**
         * Clear the currently selected students or select all based on
         * whether there's any students currently selected
         */
        onToggleStudentSelection() {
            if (this.selectedStudentsCount > 0) {
                this.selectedStudents = [];
            } else {
                this.selectedStudents = [...this.students];
            }
        },

        /**
         * Print the qrcodes
         */
        onPrintSelected() {
            window.print();
        },
    },
};
</script>

<template>
    <div class="small-12 island manage-qrcodes">
        <div v-if="isDataLoaded && hasStudents">
            <section class="content">
                <div class="students">
                    <manage-qr-codes-student
                        v-for="student in students"
                        :key="student.id"
                        :student="student"
                        :selectedStudents="selectedStudents"
                        :expiry="expiry"
                        :folder-name-with-code="folderNameWithCode"
                        @student-selected="onStudentSelected"
                    />
                </div>
            </section>
        </div>

        <div v-else-if="isDataLoaded && !hasStudents && !hasDataLoadError" class="empty-state">
            <i class="icon-staff-students"></i>
            <p>There are currently no students within this group.</p>
        </div>

        <div v-else-if="hasDataLoadError" class="alert-box alert">
            Sorry, we are currently unable to load the student data. Please contact your support if the problem
            persists.
        </div>

        <div v-else class="loader"></div>

        <bottom-drawer :showDrawer="true" :is-always-anchored="false" :unanchor-width="760" class="bottom-drawer">
            <h3 class="selected-count">
                <em>{{ selectedStudentsStatus }}</em>
            </h3>
            <button @click.prevent="onToggleStudentSelection" class="button-toggle-selection">
                {{ toggleStudentSelectionStatus }}
            </button>
            <div class="options-list">
                <div class="options-list__option">
                    <label class="required-with-tip"
                        >Login destination
                        <span
                            data-tooltip
                            class="hint has-tip tip-top"
                            title="The page the student will be sent to after logging in with the QR code"
                            >?</span
                        ></label
                    >
                    <select v-model="destination">
                        <option value="homepage">Homepage</option>
                        <option value="dashboard">Dashboard</option>
                        <option value="profile">Profile</option>
                    </select>
                </div>
                <div class="options-list__option">
                    <label
                        >Login expiry
                        <span
                            data-tooltip
                            class="hint has-tip tip-top"
                            title="How long the login codes will last for. After this time they will need to be regenerated"
                            >?</span
                        ></label
                    >
                    <select v-model="expiryMonth">
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                    </select>
                </div>
                <button
                    @click.prevent="onGenerateCodesSelected()"
                    class="button submit icon-tiles options-list__option"
                    title="Generate codes"
                    :disabled="isGenerateCodesDisabled"
                >
                    {{ generateCodesStatus }}
                </button>
                <button
                    @click.prevent="onPrintSelected()"
                    class="button submit icon-print options-list__option"
                    title="Print codes"
                    :disabled="!hasGeneratedCodes"
                >
                    Print codes
                </button>
            </div>
        </bottom-drawer>
    </div>
</template>

<style lang="scss" scoped>
.manage-qrcodes {
    padding-bottom: 190px; // allow space for bottom-drawer at the bottom
    @media (max-width: 760px) {
        //as it will no longer be anchored below this point, we don't need the extra padding
        padding-bottom: 1rem;
    }
}

.selected-count {
    display: inline-block;
    margin-right: 0.5rem;
}

.button-toggle-selection {
    display: inline-block;
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
}

.options-list {
    align-items: flex-end; //so the selects and buttons line up at the bottom
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    select,
    button {
        margin-bottom: 0;
    }
    button {
        max-height: 1rem; //to allign the buttons to be the same height as the selects
    }
}

.options-list__option {
    flex: 1 1 0; //keep both sides equal
    @media (max-width: 760px) {
        //move to two columns instead of four
        flex: 1 0 35%;
    }
    @media (max-width: 380px) {
        //and then to one column
        flex: 1 0 100%;
    }
}

.students {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 1rem 0;
}
</style>

<style>
@page {
    size: A4 portrait; /* try and enforce portrait orientation */
}

@media print {
    .students {
        display: block !important; /* This is required for '.student break-inside: avoid' to work (in manage-qr-codes-student.vue) */
    }

    body,
    .manage-qrcodes {
        background: white; /* ensure white background, may have been changed by custom styles */
    }

    #container,
    .island {
        padding: 0; /* to get more of the page estate */
    }

    .breadcrumb,
    h1,
    .bottom-drawer,
    #footer {
        display: none; /* not needed for print */
    }
}
</style>
