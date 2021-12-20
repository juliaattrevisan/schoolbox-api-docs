<script>

    import axios from "axios";

    /**
    * To display a list canvas Assignments
    */

    export default {

    name: 'canvas-assignments',

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {},

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     * https://vuejs.org/v2/guide/mixins.html
     *
     */
    mixins: [],

    /**
     * Directives
     * Register your own custom directives
     * https://vuejs.org/v2/guide/custom-directive.html
     *
     */
    directives: {},

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     * https://vuejs.org/v2/guide/components-props.html
     *
     */
    props: {},

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data () {
    return {
    hasAssignments: null,
    loading: false,
    assignments: [],
    error: false,
}
},

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     * https://vuejs.org/v2/guide/computed.html#Computed-Properties
     *
     */
    computed: {

},

    /**
     * Watchers.
     * Respond directly to changing data
     * https://vuejs.org/v2/guide/computed.html#Watchers
     *
     */
    watch: {

},

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     *
     * Other lifecycle hooks can be added here, ordered relative to their lifecycle order
     * see https://vuejs.org/v2/guide/instance.html#Lifecycle-Diagram for reference
     *
     */
    mounted () {
        this.loading = true; //Show skeleton
        axios.get(
            '/canvas/assignments',
            {
                headers: {
                    'X-Requested-With' : 'XMLHttpRequest'
                }
            }
        ).then((response) => {
            // Add ability to limit events:
            // need to filter, then sort, then slice
            this.loading = false;
            this.assignments = response.data;
            if(this.assignments.error) {
                this.error = this.assignments.error;
                this.hasAssignments = false;
                this.assignments = false;
            }
            // Empty? Don't show the calendar at all
            if (this.assignments.assignments.length === 0) {
                this.hasAssignments = false;
            } else {
                this.hasAssignments = true;
            }
        } ).catch(function(error) {
            this.loading = false;
            this.assignments = false;
        });
},

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {}

}

</script>

<template>
    <div>
        <div v-if="loading">
            Loading Data...
        </div>
        <div v-if="!hasAssignments && !loading && error">
            Error getting Assignments.
        </div>
        <div v-else-if="!hasAssignments && !loading">
            No Assignments
        </div>
        <ul v-else>
            <li v-for="assignment in assignments.assignments" :key="assignment.id">
                <p>
                {{ assignment.context_name }} <a :href="assignment.html_url">{{ assignment.title }}</a>
            </p>
                <p class="meta">Due: <time>{{ assignment.end_at }}</time></p>
            </li>
        </ul>

    </div>
</template>

<style lang="scss">
    //Styles linked to the component placed here
</style>
