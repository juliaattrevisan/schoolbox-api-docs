<script>

/**
 * Heading that contains most primary navigation elements
 */

export default {


    methods: {
       
        
    },

}

</script>

<template>
    <header>
       <nav class="primary">
            <ul>
                <li>
                    <a class="menu-icon">
                        <svg width="18" height="14" viewBox="0 0 80 60"><rect width="100" height="10"></rect> <rect width="100" y="25" height="10"></rect> <rect width="100" y="50" height="10"></rect></svg>
                    </a>
                </li>
                <li>
                    <a class="link icon-teacher" title="Navigate to your classes">
                        <span>Classes</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-timetable" title="View your timetable">
                        <span>Timetable</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-due-work" title="View assessments requiring action">
                        <span>Assessments</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-news" title="View your News Dashboard">
                        <span>News</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-calendar" title="View your calendar">
                        <span>Calendar</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-course" title="Create, modify and view courses">
                        <span>Courses</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-favourite" title="Goals & Achievements">
                        <span>Badges</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-user" title="Access your pastoral care dashboard">
                        <span>Pastoral</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-group" title="Navigate to available groups">
                        <span>Groups</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-sport" title="Access fixtures administration">
                        <span>Fixtures</span>
                    </a>
                </li>
                <li>
                    <a class="link icon-folder" title="Browse resources and homepages">
                        <span>Resources</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="secondary">
            
        </div>
    </header>
</template>

<style scoped>

header {
    background-color: var(--navigation-background);
    color: white;
    display: flex;
    height: 4.5rem;
}

    .primary {
        flex-grow: 1;
    }

        .primary ul {
            display: flex;
            padding: 0;
        }

        .primary ul li {
            list-style-type: none;
        }

    .menu-icon {
        align-items: center;
        display: flex;
        justify-content: center;
        height: 4.5rem;
        width: 4.5rem;
    }

        .menu-icon svg {
            fill: var(--navigation-foreground);
        }

    .link {
        color: var(--navigation-foreground);
        display: block;
        height: 4.5rem;
        padding: 0.875rem 1rem;
        text-align: center;
    }

        .link span {
            display: block;
        }

        .link:before {
            font-size: 1.5rem;
            line-height: 1.5rem;
            padding: 0;
        }

    .secondary {
        background-color: green;
        flex: 0 0 144px;
    }

</style>