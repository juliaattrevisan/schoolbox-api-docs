<script>

/**
 * Generate a list of QR login codes for the current class / group. 
 * Using the QR code, the student can login and be redirected to the specified URL. 
 * The codes are set to a specific expiry. 
 * By default all users have the QR login code generated, however select students can also be chosen. 
 */

import ManageQrCodesStudent from './manage-qr-codes-student';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';

export default {
   
    components: {
        ManageQrCodesStudent, //to generate and display QR codes
        BottomDrawer, //to provide submit actions
    },

    props: {
        endpointGenerate: { //the url to request that generates the QR codes
            type: String,
            required: true,
        },
    },

    data () {
        return {
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            students: [], //the entire list of students
            selectedStudents: [], //those students currently selected
            destination: 'homepage',
            expiry: '',
            expiryMonth: '1',
            loginUrl: '',
        }
    },

    computed: {

        /**
         * No qr codes to show if there's no students, so show a 'no students' display
         * 
         * @returns { Boolean }
         */
        hasStudents () {
            return this.students.length > 0; 
        },

        /**
         * To toggle the bottom-drawer, only show when there's students to submit
         * 
         * @returns { Boolean }
         */
        hasSelectedStudents () {
            return this.selectedStudents.length > 0; 
        },

    },

    mounted  () {
        this.loadStudents();
    },

    methods: {

        /**
         * Load the current list of students
         */
        async loadStudents() {
            try {
                const response = await fetch(this.endpointGenerate, {
                    method: 'POST',
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
            }
        },

        /**
         * To determine the display of the checkbox on the student
         * 
         * @param { Number }
         * 
         * @returns { Boolean }
         */
        isInSelectedStudents (studentId) {
            return this.selectedStudents.some((student) => student.id === studentId);
        },

        /**
         * Update the selected student list with the selected or unselected student
         * 
         * @params { Object } 
         */
        onStudentSelected (student) {
            if (this.isInSelectedStudents(student.id)) {
                this.selectedStudents = this.selectedStudents.filter((selectedStudent) => selectedStudent.id !== student.id); //remove if in the list
            } else {
                this.selectedStudents = [student, ...this.selectedStudents]; //otherwise add them in
            }
        },

        /**
         * Generate the QR codes for the selected students
         */
        async generateCodes () {
            try {
                const response = await fetch(this.endpointGenerate, {
                    method: 'POST',
                    body: this.getFormData(),
                });
                const data = await response.json();
                //only update selected students
                //watch for token changes
                //if it's null, check if we have a token yet, if we don't set the token
                //otherwise if the token is null and we already have a token, keep the current token
                this.expiry = data.expiry;
                this.loginUrl = data.loginUrl;
                this.students = data.students;
                this.selectedStudents = []; //clear the selected students once generated once
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * Get the FormData to submit to the QR generation endpoint
         * 
         * @returns { FormData } 
         */
        getFormData () {
            const formData = new FormData();
            this.selectedStudents.forEach((student) => {
                formData.append('students[]', student.id);
            });
            formData.append('destination', this.destination);
            formData.append('expiry', this.expiryMonth);
            return formData;
        },

    }
}
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
                        :loginUrl="loginUrl"
                        :expiry="expiry"
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
            Sorry, we are currently unable to load the student data. Please contact your support it the problem persists.
        </div>

        <div v-else class="loader"></div>

        <bottom-drawer :showDrawer="hasSelectedStudents">
            <div class="options-list">
                <div class="option-list__option">
                    <label class="required-with-tip">Login destination <span data-tooltip class="hint has-tip tip-top"
                        title="The page the student will be sent to after logging in with the QR code">?</span></label>
                    <select v-model="destination">
                        <option value="homepage">Homepage</option>
                        <option value="dashboard">Dashboard</option>
                    </select>
                </div>
                <div class="option-list__option">
                    <label>Login expiry <span data-tooltip class="hint has-tip tip-top"
                        title="How long the login codes will last for. After this time they will need to be regenerated">?</span></label>
                    <select v-model="expiryMonth">
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                    </select>
                </div>
            </div>
            <button @click.prevent="generateCodes()" class="button submit" title="Generate codes">Generate codes</button>
        </bottom-drawer>

    </div>

</template>

<style lang="scss" scoped>

.manage-qrcodes {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.options-list {
    display: flex;
    gap: 1rem; //@todo consider safari 14
}

    .option-list__option { 
        flex: 1 1 0; //keep both sides equal
    }

.students {
    display: flex;
    flex-wrap: wrap;
    padding: 1rem 0;
}

</style>
