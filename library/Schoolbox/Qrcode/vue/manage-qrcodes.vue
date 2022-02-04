<script>
export default {
    props: {
        endpointGenerate: {
            type: String,
            required: true,
        },
        loginUrl: {
            type: String,
            required: true,
        },
        students: {
            type: Array,
            required: true,
        }
    },

    data() {
        return {
            selectedStudents: [],
            destination: ''
        }
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
                window.location.href = window.location.pathname + '?destination=' + this.destination;
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
            return formData;
        },

    }
}
</script>
<template>
    <div>
        <div v-if="students.length === 0" class="empty-state">
            <p>No students</p>
        </div>
        <div v-else>
            <section class="content small-12">
                <div class="list-item">
                    <div class="row" v-for="student in students" :key="student.id">
                        <input type="checkbox"
                               :id="'selectStudent-' + String(student.id)"
                               :checked="isInSelectedStudents(student.id)"
                               @change="onStudentSelected(student, $event.target.checked)"
                        >
                        <label :for="'selectStudent-' + String(student.id)" class="checkbox-label">
                            {{ student.fullname }}
                        </label>
                        <p v-if="student.token">
                            {{ loginUrl + student.token }} {{ student.expiry }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
        <div>
        <select v-model="destination">
            <option value="homepage">Homepage</option>
            <option value="dashboard">Dashboard</option>
        </select>
        </div>
        <a @click.prevent="generateCodes()" class="button" title="Generate Codes">Generate Codes</a>
    </div>
</template>
