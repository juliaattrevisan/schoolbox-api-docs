<script>
export default {
    name: "pastoralClassReport",
    props: {
        // The route via which the report data is retrieved
        getReportDataRoute: {
            type: String,
            default: '',
            required: true,
        },
    },
    data () {
        return {
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },

}
</script>

<template>
    <div>
        <div v-if="showSkeletonLoader" class="row">
            <ul class="information-list">
                <li class="row" v-for="n in 3" :key="n">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
