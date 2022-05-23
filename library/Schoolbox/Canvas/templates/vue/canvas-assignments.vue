<script>

    /**
    * To display a list canvas Assignments
    */

    export default {

    name: 'canvas-assignments',

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
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     *
     * Other lifecycle hooks can be added here, ordered relative to their lifecycle order
     * see https://vuejs.org/v2/guide/instance.html#Lifecycle-Diagram for reference
     *
     */
    mounted () {
        this.getData();
},

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
        async getData() {
            this.loading = true; //Show skeleton
            try {
                const response = await fetch(
                    '/canvas/assignments',
                    {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With' : 'XMLHttpRequest'
                        }
                    }
                )

                // Add ability to limit events:
                // need to filter, then sort, then slice
                this.assignments = await response.json();
                if(this.assignments.error) {
                    this.error = this.assignments.status;
                    this.hasAssignments = false;
                    this.assignments = false;
                }
                // Empty? Don't show the calendar at all
                if (this.assignments.assignments.length === 0) {
                    this.hasAssignments = false;
                } else {
                    this.hasAssignments = true;
                }
            } catch (error) {
                this.loading = false;
                this.error = error
                this.assignments = false;
            } finally {
                this.loading = false;
            }
        }
    }

}

</script>

<template>
    <section class="content">
        <div v-if="loading">
            <div class="loader"></div>
        </div>
        <div v-if="!hasAssignments && !loading && error" class="">
            We are unable to retrieve any assignments from canvas due to a connection error.
        </div>
        <div v-else-if="!hasAssignments && !loading">
            You currently have no assignments that are due in the next 30 days
        </div>
        <ul v-else class="canvas-assignments">
            <li v-for="assignment in assignments.assignments" :key="assignment.id">
                {{ assignment.context_name }} <a :href="assignment.html_url">{{ assignment.title }}</a>
                <div v-if="assignment.end_at" class="meta">Due: <time>{{ assignment.end_at }}</time></div>
                <div v-else class="meta">&nbsp;</div>
            </li>
        </ul>

    </section>
</template>

<style lang="scss">
    //Styles linked to the component placed here
    .canvas-assignments {
        margin-bottom: 0px;
        margin-left: 0.5rem;
    }
    .canvas-assignments li {
        display: block;
    }
</style>
