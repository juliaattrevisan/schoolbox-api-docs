<script>
export default {
    props: {
        getListEndpoint: {
            // The endpoint for fetching the list of items.
            type: String,
            required: true,
        },
        postItemEndpoint: {
            // The endpoint for posting a new item to.
            type: String,
            required: true,
        },
        putItemEndpointBase: {
            // The base endpoint for updating an item:
            // assume each item's put endpoint is this with the ID appended.
            type: String,
            required: true,
        },
        deleteItemEndpointBase: {
            // The endpoint for deleting an item:
            // assume each item's delete endpoint is this with the ID appended.
            type: String,
            required: true,
        },
    },

    mounted() {
        this.getData();
    },

    data() {
        return {
            isDataLoaded: false,
            hasDataLoadError: false,
            loadedData: [],
        };
    },

    methods: {
        async getData() {
            try {
                const response  = await fetch(this.getListEndpoint);
                this.loadedData = await response.json();
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
    <div>
        <div v-if="isDataLoaded">
            <div class="small-12 island">
                <template v-if="loadedData.length === 0">
                    <div id="listEmptyState" class="empty-state">
                        <i class="icon-list"></i>
                        <p>
                            No oAuth clients have been configured.
                            <a href="#">Add an oAuth client</a>.
                        </p>
                    </div>
                </template>
                <table v-else>
                    <tr>
                        <th>Name</th>
                        <th>Client ID</th>
                        <th>&nbsp;</th>
                    </tr>
                    <tr v-for="row in loadedData">
                        <td>{{ row.name }}</td>
                        <td>{{ row.clientId }}</td>
                        <td>
                            <a href="#" class="icon-delete" title="Delete this client"></a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div v-else-if="hasDataLoadError">
            <div class="small-12 island">
                <section class="content">
                    <div data-alert class="alert-box alert">
                        Sorry, we are currently unable to load this List data. Please contact Schoolbox Support it the
                        problem persists.
                    </div>
                </section>
            </div>
        </div>

        <div v-else>
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
        </div>
    </div>
</template>

<style scoped lang="scss">

</style>
