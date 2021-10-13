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


    },

    mounted () {
        getStudentData();
    },

    methods: {

        /**
         * Load the current student data
         */
        async getStudentData () {
            try {
                const response = await fetch(this.payloadUrl);
                const responseData = await response.json();
                debugger;
                // this.studentsData = responseData.data;
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

        </div>
    </div>

</template>

<style lang="scss" scoped>


</style>