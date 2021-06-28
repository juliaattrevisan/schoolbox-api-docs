<script>

/**
 * Update an invidual records permissions
 *
 */

import gsap from 'gsap/all';

export default {
    
    props: {
        permissionFields: { //the fields to display within the list item
            type: Array,
            required: true
        },
        selectedRecord: { //to know which options to display
            type: Object,
            required: true
        },
        isOpen: { //to open and close the list
            type: Boolean,
            required: true
        },
    },

    computed: {

        /**
         * Only show those fields relevant to the user type
         * 
         * @returns { Array of Objects }
         */
        roleSpecificPermissions () {
            if (this.selectedRecord.type === 'staff') {
                return this.permissionFields;
            } else {
                const onlyShowPasswordField = this.permissionFields.filter((field) => {
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
         * Save all the options
         * 
         */
        onSavePermissionsSelected () {
            // 
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
            <h4>{{ selectedRecord.name }} Permissions</h4>
            <a href="#close-permissions" class="close-permissions-button" @click.prevent="onClosePermissionsSelected">X</a>
            <ul class="option-list">
                <li v-for="(field, index) in roleSpecificPermissions" :key="index">
                    <input
                        :id="field.caption"
                        :name="field.caption"
                        type="checkbox"
                        v-model="selectedRecord[field.key]"
                    />
                    <label :for="field.caption">{{ field.caption }}</label>
                </li>
            </ul>
            <button @click.prevent="onSavePermissionsSelected" class="save-permissions-button">Save Permissions</button>
        </div>
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
    max-width: 365px;
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