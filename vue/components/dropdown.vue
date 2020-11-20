<template>
    <nav>
        <a href="#"
           class="icon-overflow"
           :data-dropdown="elementId"
           aria-expanded="false" title="Actions"></a>
        <ul :id="elementId" class="f-dropdown" data-dropdown-content>
            <slot></slot>
        </ul>
    </nav>
</template>

<script>
let ucid = 0;

export default {
    name: "dropdown",
    beforeCreate() {
        this.ucid = ucid.toString();
        ucid++;
    },
    data() {
        return {
            elementId: 'dropdown-' + String(this.ucid),
        }
    },
    mounted() {
        // Init the dropdown on this place
        this.$nextTick(() => this.jQuery(this.$el).foundation('dropdown', 'reflow'));
    },
    computed: {
        jQuery: () => window.jQuery,
    },
}
</script>
