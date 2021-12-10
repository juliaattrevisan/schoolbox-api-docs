<script>

/**
 * A single group item, can join/leave, add toggle favourites/notifications
 */

export default {

    props: {
        group: {
            type: Object,
            required: true,
        }
    },

    methods: {

        /**
         * 
         */
        join () {
            
        },

        /**
         * 
         */
        leave () {
            
        },

        /**
         * 
         */
        favourite () {
            
        },

        /**
         * 
         */
        unfavourite () {
            
        },

        /**
         * 
         */
        notify () {
            
        },

        /**
         * 
         */
        unnotify () {
            
        },

    }

}

</script>

<template>
    <div class="small-12 medium-6 large-4 xlarge-3 island">
        <section>
            <div class="v-card small-12">
                <a :href="`/homepage/${group.id }`">
                    <!-- <div class="card-class-image" style="background-color:{{ group.colour }}; border-bottom: 10px solid {{ group.colour }};{%  if group.coverImageHash %} background-image: url('/storage/image.php?hash={{ group.coverImageHash }}&size=cardImageLarge'); background-size: cover; background-repeat: no-repeat; background-position: center; {% endif %}"></div> -->
                </a>
                <div class="card-content classes groups actions-small-1">
                    <div class="list-item">
                        <h3><a :href="`/homepage/${group.id}`" :title="group.name">{{ group.name }}</a></h3>
                    </div>
                    <!-- {% if ((not isPrivate) or isAccessWrite or isAccessAdmin) %}
                        <nav>
                            <a href="#" class="icon-more" data-dropdown="class-options{{ group.id }}" aria-expanded="false" title="Actions"></a>
                        </nav>
                        <ul data-dropdown-content class="f-dropdown" id="class-options{{ group.id }}">
                            {% if (isAccessWrite or isAccessAdmin) %}
                                <li><a href="{{ routeLookup('folder.getFolderModify', {id: group.id}) }}" class="icon-modify">Modify Folder</a></li>
                                <li><a href="/statistics/homepage/{{ group.id }}" class="icon-report">View Statistics</a></li>
                            {% endif %}
                            {% if isAccessAdmin %}
                                <li><a href="/group/modify/{{ group.id }}" class="icon-group">Modify Group</a></li>
                            {% endif %}
                            {% if ((not isPrivate) and (isAccessWrite or isAccessRead)) %}
                                <li><a href="/group/joinForm.php?leave={{ group.id }}&return=true" data-confirm-leave class="icon-logout">Leave Group</a></li>
                            {% endif %}
                        </ul>
                    {% endif %} -->
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>

</style>