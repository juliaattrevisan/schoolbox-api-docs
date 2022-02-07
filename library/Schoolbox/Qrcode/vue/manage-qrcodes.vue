<script>
import qrcodeVue from 'qrcode.vue';

export default {
    props: {
        endpointGenerate: {
            type: String,
            required: true,
        },
    },
    components: {
        qrcodeVue
    },

    data() {
        return {
            students: [],
            selectedStudents: [],
            destination: '',
            expiry: '',
            expiryMonth: '',
            loginUrl: '',
        }
    },

    mounted: function () {
        this.loadStudents();
    },

    methods: {
        isInSelectedStudents(studentId) {
            return this.selectedStudents.some((student) => student.id === studentId);
        },

        onStudentSelected(student, isChecked) {
            if (isChecked) {
                this.selectedStudents = [student, ...this.selectedStudents];
            } else {
                this.selectedStudents = this.selectedStudents.filter((selectedStudent) => selectedStudent.id !== student.id); //or remove
            }
        },

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
