<script>

/**
 * Generate a list of QR login codes for the current class / group. 
 * Using the QR code, the student can login and be redirected to the specified URL. 
 * The codes are set to a specific expiry. 
 * By default all users have the QR login code generated, however select students can also be chosen. 
 */

import qrcodeVue from 'qrcode.vue';

export default {
   
    components: {
        qrcodeVue
    },

    props: {
        endpointGenerate: { //the url to request that generates the QR codes
            type: String,
            required: true,
        },
    },

    data () {
        return {
            students: [],
            selectedStudents: [],
            destination: '',
            expiry: '',
            expiryMonth: '',
            loginUrl: '',
        }
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

                let data = await response.json();
                this.students = data.students;
                this.loginUrl = data.loginUrl;
                this.expiry = data.expiry;
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * To determine the display of the checkbox on the student
         */
        isInSelectedStudents(studentId) {
            return this.selectedStudents.some((student) => student.id === studentId);
        },

        /**
         * Update the selectedStudents list
         */
        onStudentSelected(student, isChecked) {
            if (isChecked) {
                this.selectedStudents = [student, ...this.selectedStudents];
            } else {
                this.selectedStudents = this.selectedStudents.filter((selectedStudent) => selectedStudent.id !== student.id); //or remove
            }
        },

        /**
         * Generate the QR codes for the selected students
         */
        async generateCodes() {
            try {
                const response = await fetch(this.endpointGenerate, {
                    method: 'POST',
                    body: this.getFormData(),
                });

                let data = await response.json();
                this.students = data.students;
                this.loginUrl = data.loginUrl;
                this.expiry = data.expiry;
            } catch (error) {
                console.warn(error);
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
    <div class="small-12 island">
        <div v-if="students.length === 0" class="empty-state">
            <p>No students</p>
        </div>
        <div v-else>
            <div class="v-card-list">
                <div class="v-card card" v-for="student in students" :key="student.id">
                    <input type="checkbox"
                           :id="'selectStudent-' + String(student.id)"
                           :checked="isInSelectedStudents(student.id)"
                           @change="onStudentSelected(student, $event.target.checked)"
                    >
                    <label :for="'selectStudent-' + String(student.id)" class="checkbox-label">
                        {{ student.fullname }}
                    </label>
                    <div v-if="student.token">
                        <qrcode-vue :value="loginUrl + student.token"/>
                    </div>
                    <div v-if="student.token">
                        Expires {{ expiry }}
                    </div>
                </div>
            </div>
        </div>
        <div>
            <select v-model="destination">
                <option value="homepage">Homepage</option>
                <option value="dashboard">Dashboard</option>
            </select>
            <select v-model="expiryMonth">
                <option value="1">1 month</option>
                <option value="3">3 months</option>
                <option value="6">6 months</option>
            </select>
        </div>
        <a @click.prevent="generateCodes()" class="button" title="Generate Codes">Generate Codes</a>
    </div>
</template>
