<script>

/**
 * To maintain the QR code state when multiple re-generations have taken place
 */

import qrcodeVue from 'qrcode.vue';
import { gsap } from 'gsap/all';


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
                    this.animateQrCodeChange();
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

        /**
         * To help the user realise there's been a change of QR code
         */
        animateQrCodeChange () {
            gsap.to(this.$refs.code, { 
                duration: 0.5, 
                ease: 'power2.out',
                opacity: 1, 
                scale: 1,
                startAt: {
                    opacity: 0.5, 
                    scale: 0.75,
                }
            })
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
            <div class="student_code-option">&nbsp;</div>
            <div class="student_code-option qr-code" ref="code">
                <qrcode-vue :value="qrCodeValue" :size="300" />
            </div>
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
        top: 0px;
        width: 100%;
    }

    .qr-code {
        opacity: 0; //will be animated in with gsap once the code is set
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
    transition: opacity 0.3s, top 0.4s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
    top: 20px;
}    

</style>
