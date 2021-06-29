<script>

/**
 * Update an invidual rows permissions
 *
 */

import gsap from 'gsap/all';
import axios from 'axios';
import Toast from '@schoolbox/toast';

export default {
    
    components: {
        Toast
    },

    props: {
        permissionColumns: { //the columns to display within the list
            type: Array,
            required: true
        },
        selectedRow: { //to know which row permission fields to display
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
            saveStatus: 'Save Permissions' //to update the button text when saving
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
                return this.permissionColumns;
            } else {
                const onlyShowPasswordField = this.permissionColumns.filter((field) => {
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
         * 
         */
        open () {
            this.$refs.outerContainer.style.display = 'block';
            gsap.to(this.$refs.innerContainer, { right: 0, duration: 0.4 } );
        },

        /**
         * Save all the permissions options, currently just using the 
         * existing save all route
         * 
         */
        async onSavePermissionsSelected () {
            this.saveStatus = 'Saving...';
            this.$refs.toast.activate('Saving...');
            try {
                const formData = new FormData(this.$parent.$refs.form);
                const response = await axios.post(this.saveRoute, formData);
                if (response.status === 200) {
                    this.saveStatus = 'Save Permissions';
                    this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } 
        },

        /**
         * Show the access options
         * 
         */
        onClosePermissionsSelected () {
            this.$emit('on-permissions-closed');
        },

        /**
         * Close the view
         * 
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
    <div class="permissions-outer-container" ref="outerContainer">
        <div class="permissions-inner-container checklist" ref="innerContainer">
            <h4>{{ selectedRow.name }} Permissions</h4>
            <a href="#close-permissions" class="close-permissions-button" @click.prevent="onClosePermissionsSelected">X</a>
            <ul class="option-list" data-option-list>
                <li v-for="(field, index) in roleSpecificPermissions" :key="index">
                    <input
                        :id="field.caption"
                        type="checkbox"
                        v-model="selectedRow[field.key]"
                    />
                    <label :for="field.caption">{{ field.caption }}</label>
                </li>
            </ul>
            <button @click.prevent="onSavePermissionsSelected" class="save-permissions-button">{{ saveStatus }}</button>
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.permissions-outer-container {
    display: none;
    position: absolute;
    z-index: 1;
    right: 0px;
    top: 0px;
    width: 60%;
    max-width: 295px;
    height: 100%;
    overflow: hidden;
}

.permissions-inner-container {
    position: absolute;
    right: -100%;
    top: 0px;
    width: 100%;
    height: 100%;
    border: 2px solid $form-border-color;
    padding: 1rem;
    background: white;
}

.close-permissions-button {
    position: absolute;
    top: 0.5rem;
    right: 1rem;
    padding: 5px 10px;
    font-size: 1.5rem;
    &:hover {
        background-color: $content-ui-hover;
    }
}

.save-permissions-button {
    width: 100%;
}

</style>