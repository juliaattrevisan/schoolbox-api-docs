<script setup>
/**
 * Top bar that contains most navigation elements and toggles the side menu and notifications
 */

import { topMenuData } from "./headerData";

const emit = defineEmits(["openSideMenu", "toggleProfileMenu", "openNotificationsMenu"]);

const props = defineProps({
    unreadNotifications: {
        type: Number,
        required: false,
        default: 0,
        validator(value) {
            return typeof value === "number";
        },
    },
});

window.Vue.onMounted(() => {
    primaryMenuItems = Array.from(primary.value.querySelectorAll("li")); //get all the list menu list items
    primaryMenuItems.splice(0, 2); //remove the side menu button and the logo
    primaryMenuItemsMaxXPos = primaryMenuItems.map(
        (menuItem) => menuItem.getBoundingClientRect().x + menuItem.getBoundingClientRect().width,
    );
    initResizeObserver();
});

/**
 * Any changes to the element size are tracked by ResizeObserver so can update dimensions
 */
function initResizeObserver() {
    const resizeObserver = new ResizeObserver(() => updateTopNavItems());
    resizeObserver.observe(topBar.value);
}

const menuData = window.Vue.ref(topMenuData);
const topBar = window.Vue.ref(null);
const primary = window.Vue.ref(null);
let primaryMenuItems;
let primaryMenuItemsMaxXPos;
const secondary = window.Vue.ref(null);

/**
 * Show/hide top nav items based on space available
 */
function updateTopNavItems() {
    const secondaryXPos = secondary.value.getBoundingClientRect().x;
    primaryMenuItems.forEach((menuItem, index) => {
        menuData.value[index].isShown = secondaryXPos > primaryMenuItemsMaxXPos[index] ? true : false;
    });
}
</script>

<template>
    <div class="top-bar" ref="topBar">
        <nav class="primary" aria-label="Primary navigation" ref="primary">
            <ul>
                <li>
                    <button class="button-side-menu" aria-label="Open Side Menu" @click="$emit('openSideMenu')">
                        <svg width="18" height="14" viewBox="0 0 80 60" aria-hidden="true" focusable="false">
                            <rect width="100" height="10"></rect>
                            <rect width="100" y="25" height="10"></rect>
                            <rect width="100" y="50" height="10"></rect>
                        </svg>
                    </button>
                </li>
                <li>
                    <a class="logo" href="#" title="Return to the homepage">
                        <img src="sb-logo.png" alt="Schoolbox Pty Ltd" width="72" height="72" />
                    </a>
                </li>
                <li v-for="data in menuData" v-show="data.isShown">
                    <a :class="`link icon-${data.icon}`" :href="data.href" :title="data.hover">
                        <span>{{ data.title }}</span>
                    </a>
                </li>
            </ul>
        </nav>
        <nav class="secondary" aria-label="Secondary navigation" ref="secondary">
            <ul>
                <li>
                    <button
                        class="button-profile-menu"
                        aria-label="Toggle Profile Menu"
                        @click="$emit('toggleProfileMenu')"
                    >
                        <img src="admin-logo.jpeg" alt="Profile image" width="72" height="72" />
                    </button>
                </li>
                <li>
                    <button
                        class="button-show-notifications icon-notifications"
                        :data-unread="unreadNotifications"
                        aria-label="Show Notifications"
                        aria-expanded="false"
                        @click="$emit('openNotificationsMenu')"
                    >
                        {{ unreadNotifications > 0 ? unreadNotifications : "" }}
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<style lang="scss" scoped>
.top-bar {
    background-color: var(--navigation-background);
    height: 4.5rem;
}

ul {
    display: flex;
    margin: 0;
    padding: 0;
    li {
        list-style-type: none;
    }
}

.button-side-menu {
    align-items: center;
    background: none;
    display: flex;
    justify-content: center;
    height: 4.5rem;
    width: 4.5rem;
    svg {
        fill: var(--navigation-foreground);
    }
    &:hover {
        background-color: var(--navigation-hover);
    }
}

.logo {
    padding: 0 5px;
    img {
        max-width: none; //don't scale the logo on browser resize
    }
}

.link {
    color: var(--navigation-foreground);
    display: block;
    height: 4.5rem;
    padding: 0.875rem 1rem;
    text-align: center;
    span {
        display: block;
    }
    &:before {
        //increase the icon sizes
        font-size: 1.5rem;
        line-height: 1.5rem;
        padding: 0;
    }
    &:hover {
        background-color: var(--navigation-hover);
    }
}

.secondary {
    right: 0;
    position: absolute;
    top: 0;
}

.button-profile-menu {
    padding: 0;
}

.button-show-notifications {
    align-items: center;
    background: none;
    color: var(--navigation-foreground);
    display: flex;
    justify-content: center;
    height: 4.5rem;
    width: 4.5rem;
    &[data-unread]:not([data-unread="0"]) {
        background-color: #fa4a47;
        color: #fff;
    }
    &:before {
        padding-right: 5px; //bring the number in closer
    }
    &:hover {
        background-color: var(--navigation-hover);
    }
}
</style>
