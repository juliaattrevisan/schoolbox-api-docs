<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * An individual student with a linked QR code.
 * Split to consider the QR code state when multiple re-generations have taken place.
 */

import qrcodeVue from "qrcode.vue";
import { gsap } from "gsap/all";

export default {
    components: {
        qrcodeVue, //to display QR codes according to the link returned
    },

    props: {
        student: {
            //the student data
            type: Object,
            required: true,
        },
        selectedStudents: {
            //to know whether the student is currently selected or not
            type: Array,
            required: true,
        },
        expiry: {
            //when the qr code will expire
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
            hasQrCode: false, //to determine whether to show the qr code
            qrCodeValue: "", //the qr code is updated on every new generation request, so needs to be stored
            studentExpiry: "", //the expiry is updated on every new generation request, so needs to be stored
            qrCodeSize: 170, //the size of the QR code
        };
    },

    computed: {
        /**
         * To determine the display of the checkbox on the student
         *
         * @returns { Boolean }
         */
        isInSelectedStudents() {
            return this.selectedStudents.some((student) => student.id === this.student.id);
        },

        /**
         * So we can use the same qrCodeSize within css
         *
         * @returns { string }
         */
        qrCodeSizeWithPx() {
            return `${this.qrCodeSize}px`;
        },
    },

    watch: {
        /**
         * Every student is always returned, with those not selected just returning a null token.
         * So we need to ensure that qr codes previously generated for a student aren't
         * overwritten with every new generation request.
         */
        student: {
            deep: true,
            handler(value) {
                if (value.qrCodeValue) {
                    this.studentExpiry = this.expiry;
                    this.qrCodeValue = `${value.qrCodeValue}`;
                    this.hasQrCode = true;
                    this.animateQrCodeChange();
                }
            },
        },
    },

    methods: {
        /**
         * Update the selected student list with the selected or unselected student
         */
        onStudentSelected() {
            this.$emit("student-selected", this.student);
        },

        /**
         * To help the user realise there's been a change of QR code
         */
        animateQrCodeChange() {
            gsap.to(this.$refs.qrCodeContainer, {
                duration: 0.7,
                ease: "power2.inOut",
                onComplete: () => this.$refs.emptyState.remove(), //so we don't see the empty state in any subsequent animations
                opacity: 1,
                rotationY: 0,
                startAt: {
                    opacity: 0,
                    rotationY: 180,
                },
                transformPerspective: 1000,
            });
        },
    },
};
</script>

<template>
    <div :class="`student ${hasQrCode ? 'has-qr-code' : 'no-qr-code'}`" @click="onStudentSelected()">
        <div class="student__checkbox">
            <input
                type="checkbox"
                :id="`selectStudent-${student.id}`"
                :checked="isInSelectedStudents"
                @change="onStudentSelected()"
            />
            <label :for="`selectStudent-${student.id}`" class="checkbox-label"></label>
        </div>
        <div class="student__code" ref="container">
            <div class="student_code-option empty-state" ref="emptyState"></div>
            <div class="student_code-option qr-code-container" ref="qrCodeContainer">
                <qrcode-vue :value="qrCodeValue" :size="qrCodeSize" level="M" class="qr-code" />
            </div>
        </div>
        <div class="student__details">
            <a :href="`/search/user/${student.id}`" :title="`View ${student.fullname}`">{{ student.fullname }}</a>
            <h4>{{ folderNameWithCode }}</h4>
            <h5 v-if="hasQrCode">Expires {{ studentExpiry }}</h5>
            <h5 v-else>&nbsp;</h5>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.student {
    border: 1px solid var(--form-border-color);
    padding: 1rem;
    transition: background-color 0.3s; //shows that the student can be selected to toggle selection status
    width: calc(v-bind(qrCodeSizeWithPx) + 2rem); //the width of the qr code plus 1rem padding left and right
    &:hover {
        background-color: var(--form-bg-hover-color);
    }
}

.student__code {
    height: v-bind(qrCodeSizeWithPx); //should be the same as the qr code size
    position: relative;
    width: v-bind(qrCodeSizeWithPx);
}

.student_code-option {
    height: 100%;
    position: absolute; //so the qr code and empty-state can sit on top of each other
    top: 0px;
    width: 100%;
}

.qr-code-container {
    opacity: 0; //will be animated in with gsap once the code is set
}

.student__details {
    overflow: hidden;
    padding: 1rem 0;
    text-align: center;
    h4 {
        display: none; //only show in print
    }
    h4,
    h5 {
        margin-bottom: 0; //remove the 1rem default
    }
    h5 {
        font-style: italic;
    }
}

@media print {
    .student {
        border: 1px dashed black; //change to 'scissor cut' guide lines
        break-inside: avoid;
        display: inline-block;
        margin: 0 -1px -1px 0;
        padding: 2.5rem 1rem;
    }

    .no-qr-code {
        display: none; //only show those with QR codes
    }

    .student__checkbox {
        display: none; //remove the checkbox as you can't select a checkbox on a printed page *_*
    }

    .student__details {
        padding-bottom: 0;
        a,
        h4,
        h5 {
            color: black; //ensure any custom print styles are ignored
        }
        a {
            //remove default link styles
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }
        h4,
        h5 {
            display: block;
            font-size: 12px;
        }
    }
}
</style>
