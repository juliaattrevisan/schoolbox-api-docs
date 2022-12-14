<script setup lang="ts">

import SmallCard from '@schoolboxVue/components/card/small-card.vue';
import PopOut from '@schoolboxVue/components/popOut/pop-out.vue';

/**
 *
 */
</script>

<template>
    <pop-out>
        <div class="pop-out-markbook">
            <div class="pop-out-markbook__card-list">
                <small-card></small-card>
                <small-card></small-card>
                <small-card></small-card>
            </div>
            <ul class="pop-out-markbook__menu">
                <li class="pop-out-markbook__menu-item">View All Recent Activity</li>
            </ul>
            <hr class="pop-out__divider" />
            <ul class="pop-out-markbook__menu">
                <li class="pop-out-markbook__menu-item">Update Feedback</li>
                <li class="pop-out-markbook__menu-item">Review Submission</li>
                <li class="pop-out-markbook__menu-item">Publish Feedback</li>
                <li class="pop-out-markbook__menu-item">Exclude Student</li>
                <li class="pop-out-markbook__menu-item">Submission History</li>
            </ul>
        </div>
    </pop-out>
</template>

<style scoped lang="scss">
.pop-out-markbook {

        &__card-list, 
        &__menu {
            list-style-type: none;
            margin: 0;
            overflow: hidden;

            .c-card{
                &--small{
                    padding: 0.5rem 1.5rem;
                }
            }
        }

        &__menu {
            
            &-item {
                padding: 0.5rem 1.5rem;
                font-size: 16px;
                cursor: pointer;

                &:hover {
                    background-color: var(--content-ui-background);
                    color: var(--content-ui-foreground);
                }
            }
        }
    }
</style>
