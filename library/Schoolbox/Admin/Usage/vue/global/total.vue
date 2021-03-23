<script>
import axios from 'axios';
import {Chart} from 'highcharts-vue';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "total",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
       'endpoint',
       'isS3Adapter'
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        highcharts: Chart
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {

            /* True, when data is currently loading; used to show skeleton
             */
            loading: true,

            /* All the data for this visualisation
             */
            allData: [],

            /* Chart options
             */
            chartOptions: {}
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        let self = this;

        /* Show skeleton
         */
        this.loading = true;

        /* Fetch payload asynchronously
         */
        let request = {
            url: self.endpoint,
            method: 'get',
            responseType: 'json'
        }

        /* Only works if the adapter is non-aws
         */
        axios.request(request)
            .then(function (response) {

                /* Load into vue component
                 */
                self.allData = response.data;

                let categories = Array();
                let values = Array();

                self.allData.forEach(function(element, index)
                {
                    categories.push(element.label);
                    values.push(parseInt(element.size));
                });

                self.chartOptions = {
                    chart: {
                        height: 150,
                        spacingRight: 48,
                        type: 'bar'
                    },

                    credits: {
                        enabled: false
                    },

                    legend: {
                        enabled: false
                    },

                    plotOptions: {
                        bar: {
                            /* https://stackoverflow.com/questions/25721067/highchart-data-label-issue-in-bar-chart
                             */
                            dataLabels: {
                                enabled: true,
                                formatter: function() {
                                    return '<span style="color: #777777">' + this.y + '%</span>'
                                }
                            }
                        }
                    },

                    time: {
                        useUTC: false
                    },

                    title: {
                        text: 'Total Usage'
                    },

                    tooltip: {
                        enabled: false
                    },

                    xAxis: {
                        categories: ['']
                    },

                    yAxis: {
                        allowDecimals: false,
                        min: 0,
                        max: 100,
                        title: {
                            text: 'Percentage'
                        }
                    },

                    series: [
                        {
                            color: '#82b5d8',
                            name: '',
                            data: values
                        }
                    ]
                }
            })
            .catch(error => {
                if (error.response) {
                    self.loading = false;
                }
            })
            .then(function () {

                /* Hide skeleton
                 */
                self.loading = false;
            });
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
    }
}
</script>
<template>
    <div>
        <template v-if="!this.isS3Adapter">
            <template v-if="this.loading">
                <table class="skeleton">
                    <thead>
                    <tr>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
            </template>
            <template v-else>
                <highcharts :options="chartOptions"></highcharts>
            </template>
        </template>
    </div>
</template>