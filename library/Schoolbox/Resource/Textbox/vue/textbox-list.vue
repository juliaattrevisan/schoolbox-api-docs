<script>

import axios from 'axios';

export default {
    name: 'textbox-list',
    props: {
        /**
         * Endpoint for fetching textbox usages.
         */
        textboxUsageEndpoint: {
            type: String,
            required: true,
        }
    },
    data() {
        return {
            textboxes: [],
            loading: true,
        }
    },
    mounted: function () {
        let self = this;
        axios
            .get(self.textboxUsageEndpoint)
            .then(function (response) {
                self.textboxes = response.data;
            })
    },
    methods: {
    },
    computed: {
    },
    filters: {}
}

</script>
<template>
    <ul class="information-list unsortable expandable-card">
        <li v-for="(usage, index) in textboxes">
            <div class="actions-small-1">
                <div class="expander" :data-collapser="'collapser-' + String(index)"></div>
                <div class="list-item">
                    <div class="small-12 column">
                        <label>
                            <span class="hide-for-medium-up">Textbox</span>
                            <input type="text" v-model="usage.textbox.heading">
                        </label>

                        <p class="meta" v-if="usage.folders.length > 0">
                            Pages:
                            <template v-for="(folder, index) in usage.folders">
                                <a :href="folder._links.view">{{ folder.title }}</a><template v-if="(index + 1) < usage.folders.length">, </template>
                            </template>
                        </p>

                        <p class="meta" v-if="usage.dashboards.length > 0">
                            Dashboards:
                            <template>{{ usage.dashboards.map((v) => v.name).join(', ') }}</template>
                        </p>
                    </div>
                </div>
                <nav>
                    <a class="icon-delete" :href="usage.textbox._links.delete" title="Delete"></a>
                </nav>
            </div>
            <div class="hide actions-small-1" data-collapse-state="collapsed" :data-collapsable="'collapser-' + String(index)">
                <article class="content">
                    {{ usage.textbox.body }}
                </article>
            </div>
        </li>
    </ul>
</template>
