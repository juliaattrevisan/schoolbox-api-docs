<script>

import gsap from 'gsap/all';

export default {
    
    props: {
        accessFields: { //the fields to display within the list item
            type: Array,
            required: true
        },
        isOpen: { //to open and close the list
            type: Boolean,
            required: true
        },
    },

    watch: {
        
        isOpen (newValue) {
            if (newValue === true) {
                this.open();
            } else {
                this.close();
            }
        }

    },

    // data () {
        
    // },

    // created () {
        
    // },

    // mounted () {
        
    // },

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
         * Show the access options
         * 
         */
        onClosePermissionsSelected () {
            this.$emit('on-configure-access-closed');
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
            <h4>Access permissions for</h4>
            <a href="#close-permissions" class="close-permissions-button" @click.prevent="onClosePermissionsSelected">X</a>
            <ul class="option-list">
                <li v-for="(field, index) in accessFields" :key="index">
                    <!-- <input
                        id="role[{{ role.id }}]"
                        name="role[{{ role.id }}]"
                        type="checkbox"
                        value="{{ role.id }}"
                        {% if role.id in enabled %}checked{% endif %}
                    />
                    <label for="role[{{ role.id }}]">{{ role.name }}</label> -->
                    <input
                        :id="field.caption"
                        :name="field.caption"
                        type="checkbox"
                        value=""
                        checked
                    />
                    <label :for="field.caption">{{ field.caption }}</label>
                </li>
            </ul>
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
    width: 66%;
    max-width: 400px;
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
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
}

</style>