<script>

/**
 * To maintain the QR code state when multiple re-generations have taken place
 */

import qrcodeVue from 'qrcode.vue';

export default {
   
    components: {
        qrcodeVue, //to display QR codes according to the link returned
    },

    props: {
        student: { //the student data
            type: Object,
            required: true,
        },
        selectedStudents: { //to know whether the student is currently selected or not
            type: Array,
            required: true,
        },
        loginUrl: { //
            type: String,
            required: true,
        },
        expiry: { //
            type: String,
            required: true,
        },
    },

    data () {
        return {
            hasQrCode: false, //to determine whether to show the qr code
            qrCodeValue: '',
            studentExpiry: '',
        }
    },

    computed: {

        /**
         * To determine the display of the checkbox on the student
         * 
         * @returns { Boolean }
         */
        isInSelectedStudents () {
            return this.selectedStudents.some((student) => student.id === this.student.id);
        },

    },

    watch: {

        /**
         * Every student is always returned, with those not selected just returning null token.
         * So we need to ensure that qr codes previously generated for a student aren't 
         * overwritten with every new generation request. 
         */
        student: {
            deep: true,
            handler (value) {
                if (value.token) { 
                    this.studentExpiry = this.expiry;
                    this.qrCodeValue = `${this.loginUrl}${value.token}`;
                    this.hasQrCode = true;
                } 
            }
        }

    },

    methods: {

        /**
         * Update the selected student list with the selected or unselected student
         * 
         * @params { Object } 
         */
        onStudentSelected () {
            this.$emit('student-selected', this.student);
        },

    }
}
</script>

<template>

    <div class="student" @click.prevent="onStudentSelected()">
        <input type="checkbox"
            :id="`selectStudent-${student.id}`"
            :checked="isInSelectedStudents"
            @change="onStudentSelected()"
        >
        <label :for="`selectStudent-${student.id}`" class="checkbox-label"></label>
        <div class="student__code">
            <transition name="fade">
                <div v-if="hasQrCode" class="student_code-option" key="qrCode">
                    <qrcode-vue :value="qrCodeValue" :size="300" />
                </div>
                <div v-else class="student_code-option" key="placeholder">&nbsp;</div>
            </transition>
        </div>
        <div class="student__details">
            <a :href="`/search/user/${student.id}`" title="View profile">{{ student.fullname }}</a>
            <h5 v-if="hasQrCode">Expires {{ studentExpiry }}</h5>
            <h5 v-else>&nbsp;</h5>
        </div>
    </div>
                
</template>

<style lang="scss" scoped>

.student {
    border: 1px solid var(--form-border-color);
    margin: 0 0.5rem 1rem 0.5rem;
    padding: 1rem;
    transition: background-color 0.3s;
    &:hover {
        background-color: var(--form-bg-hover-color);
    }
}

    .student__code {
        position: relative;
        height: 300px;
        width: 300px;
    }

    .student_code-option {
        background: #DDDDDD;
        height: 100%;
        position: absolute;
        width: 100%;
    }

    .student__details {
        padding: 1rem 0;
        text-align: center;
        h2 {
            margin-bottom: 0.5rem;
        }
        h5 {
            margin-bottom: 0; //remove the 1rem default
        }
    }
    

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}    

</style>
