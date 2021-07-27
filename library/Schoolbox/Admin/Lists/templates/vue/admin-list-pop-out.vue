<script>

/**
 * Update an invidual rows permissions
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import Toast from '@schoolbox/toast';

export default {

    components: {
        Toast
    },

    props: {
        caption: { //the title of the pop out
            type: String,
            required: true
        },
        fields: { //the fields to display within the pop out
            type: Array,
            required: true
        },
        selectedRow: { //to know which row to display
            type: Object,
            required: true
        },
        isOpen: { //to open and close the list
            type: Boolean,
            required: true
        },
        saveRoute: { //url of entire list save
            type: String,
            required: true
        }
    },

    data () {
        return {
            saveStatus: 'Save', //to update the button text when saving
            isProcessing: false //to prevent multiple rapid-fire submissions
        }
    },

    computed: {

        /**
         * Only show those fields relevant to the user type,
         * showing all for staff and just password change for all others
         *
         * @returns { Array of Objects }
         */
        roleSpecificPermissions () {
            if (this.selectedRow.type === 'staff') {
                return this.fields;
            } else {
                const onlyShowPasswordField = this.fields.filter((field) => {
                    return field.caption === 'Allow Password Change';
                });
                return onlyShowPasswordField;
            }
        }

    },

    watch: {

        /**
         * To open or close depending on boolean value
         *
         */
        isOpen (newValue) {
            if (newValue === true) {
                this.open();
            } else {
                this.close();
            }
        }

    },

    methods: {

        /**
         * Animate the view on
         */
        open () {
            this.$refs.outerContainer.style.display = 'block';
            gsap.to(this.$refs.innerContainer, { right: 0, duration: 0.4 } );
        },

        /**
         * Save all the permissions options, currently just using the
         * existing save all route
         */
        async onSaveSelected () {
            this.isProcessing = true;
            this.saveStatus = 'Saving...';
            this.$refs.toast.activate('Saving...');
            try {
                const formData = new FormData(this.$parent.$refs.form);
                const response = await axios.post(this.saveRoute, formData);
                if (response.status === 200) {
                    this.saveStatus = 'Save';
                    this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } finally {
                location.reload();
            }
        },

        /**
         * Show the access options
         */
        onClosePopOutSelected () {
            this.$emit('on-pop-out-closed');
        },

        /**
         * Close the view
         */
        close () {
            gsap.to(this.$refs.innerContainer, { right: '-100%', duration: 0.3, onComplete: () => {
                this.$refs.outerContainer.style.display = 'none'
            } } );
        },

    }

}
</script>

<template>
    <div class="outer-container" ref="outerContainer">
        <div class="inner-container checklist" ref="innerContainer">
            <h4>{{ selectedRow.name }} {{ caption }}</h4>
            <a href="#close-pop-out" class="close-button icon-close" @click.prevent="onClosePopOutSelected"></a>
            <ul class="option-list" data-option-list>
                <li v-for="(field, index) in roleSpecificPermissions" :key="index">
                <!-- <li v-for="(field, index) in fields" :key="index"> -->
                    <input
                        :id="field.caption"
                        type="checkbox"
                        v-model="selectedRow[field.id]"
                    />
                    <label :for="field.caption">{{ field.caption }}</label>
                </li>
            </ul>
            <button @click.prevent="onSaveSelected" class="save-button">{{ saveStatus }}</button>
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.outer-container {
    display: none;
    position: absolute;
    overflow: hidden;
    z-index: 1;
    right: 0px;
    top: 0px;
    width: 320px;
    height: 100%;
}

.inner-container {
    position: absolute;
    right: -100%;
    top: 0px;
    width: 100%;
    height: 100%;
    border: 2px solid $form-border-color;
    padding: 1rem;
    background: white;
}

.close-button {
    position: absolute;
    top: 0;
    right: 0;
    padding: 2px 2px 2px 10px;
    font-size: 1.5rem;
}

.save-button {
    width: 100%;
}

</style>
