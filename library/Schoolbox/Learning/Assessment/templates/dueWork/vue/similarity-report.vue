<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions. 
 * The list can be filtered by similarity score and submission status
 */



export default {

    components: {
        
    },
    
    props: {
        payloadUrl: { //where to get the data from
            type: String,
            required: true
        },
    },
    
    data () {
        return {
            studentsData: [], //hold all student data
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
        }
    },

    computed: {

        /**
         * To show a note showing no students have submitted yet
         * 
         * @returns { Boolean }
         */
        hasNoSubmissions () {
            return this.isDataLoaded && this.studentsData.length === 0;
        }

    },

    mounted () {
        this.getStudentData();
    },

    methods: {

        /**
         * Load the current student data
         */
        async getStudentData () {
            try {
                const response = await fetch(this.payloadUrl);
                const responseData = await response.json();
                this.studentsData = responseData;
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },

    }

}
</script>

<template>

    <div class="row">
        <div class="small-12 columns">

            <div v-if="hasNoSubmissions" class="empty-state">
                <i class="icon-staff-students"></i>
                <p>Students have not yet submitted any work. Once submitted you may return to this page to review the similarity results for each submission.</p>
            </div>

        </div>
    </div>

</template>

<style lang="scss" scoped>


</style>