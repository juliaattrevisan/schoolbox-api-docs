<script setup>

/**
 * Top bar that contains most navigation elements and toggles the side menu and notifications
 */

const emit = defineEmits(['openSideMenu', 'toggleProfileMenu']);

</script>

<template>
    <div class="top-bar">
       <nav class="primary" aria-label="Primary navigation">
            <ul>
                <li>
                    <button class="button-side-menu" aria-label="Open Side Menu" @click="$emit('openSideMenu')">
                        <svg width="18" height="14" viewBox="0 0 80 60" aria-hidden="true" focusable="false"><rect width="100" height="10"></rect> <rect width="100" y="25" height="10"></rect> <rect width="100" y="50" height="10"></rect></svg>
                    </button>
                </li>
                <li>
                    <a class="logo" href="#" title="Return to the homepage">
                        <img src="sb-logo.png" alt="Schoolbox Pty Ltd" width="72" height="72" />
                    </a>
                </li>
                <li>
                    <a class="link icon-teacher" href="#" title="Navigate to your classes">
                        <span>Classes</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-timetable" href="#" title="View your timetable">
                        <span>Timetable</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-due-work" href="#" title="View assessments requiring action">
                        <span>Assessments</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-news" href="#" title="View your News Dashboard">
                        <span>News</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-calendar" href="#" title="View your calendar">
                        <span>Calendar</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-course" href="#" title="Create, modify and view courses">
                        <span>Courses</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-favourite" href="#" title="Goals & Achievements">
                        <span>Badges</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-user" href="#" title="Access your pastoral care dashboard">
                        <span>Pastoral</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-group" href="#" title="Navigate to available groups">
                        <span>Groups</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-sport" href="#" title="Access fixtures administration">
                        <span>Fixtures</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-folder" href="#" title="Browse resources and homepages">
                        <span>Resources</span>
                    </a>
                </li>
            </ul>
        </nav>
        <nav class="secondary" aria-label="Secondary navigation">
            <ul>
                <li>
                    <button class="button-profile-menu" aria-label="Toggle Profile Menu" @click="$emit('toggleProfileMenu')">
                        <img src="admin-logo.jpeg" alt="Profile image" width="72" height="72" />
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<style lang="scss" scoped>

.top-bar {
    background-color: var(--navigation-background);
    display: flex;
    height: 4.5rem;
}

    .primary {
        flex-grow: 1; //fill out the entire width excluding the 9rem left for .secondary
        ul li {
            margin-right: 10px;
        }
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

    .link {
        color: var(--navigation-foreground);
        display: block;
        height: 4.5rem;
        padding: 0.875rem 1rem;
        text-align: center;
        span {
            display: block;
        }
        &:hover {
            background-color: var(--navigation-hover);
        }
        &:before { //increase the icon sizes
            font-size: 1.5rem;
            line-height: 1.5rem;
            padding: 0;
        }
    }

    .secondary {
        flex: 0 0 9rem; //to side on the right hand side
    }

    .button-profile-menu {
        padding: 0;
    }

</style>