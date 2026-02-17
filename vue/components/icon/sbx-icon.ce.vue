<script setup lang="ts">
/**
 * To display an icon
 */

interface Props {
    /**
     * The name of the icon
     */
    name?: string;
    /**
     * The size of the icon
     */
    size?: string;
    /**
     * The color of the icon
     */
    color?: string;
}

const props = withDefaults(defineProps<Props>(), {
    name: "",
    size: "m",
    color: "currentColor",
});

/**
 * The primary size options of the icon
 */
const sizeMap: Record<string, string> = {
    xs: "0.25rem",
    s: "0.5rem",
    m: "1rem",
    l: "1.5rem",
    xl: "2rem",
};

/**
 * The computed size of the icon
 * @returns {string} The size of the icon
 */
const computedSize = window.Vue.computed(() => {
    return sizeMap[props.size as string] || (props.size as string) || sizeMap.m;
});

/**
 * Alert (in the console) that an icon name is invalid (to assist with debugging)
 * @returns false, for template usage
 */
function alertInvalidIconName(): false {
    console.error(`Invalid icon name: ${props.name}`);
    return false;
}
</script>

<template>
    <span class="sbx-icon" aria-hidden="true" data-test="sbx-icon">
        <slot>
            <svg
                v-if="props.name === 'add'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 14h-14v-14h-4v14h-14v4h14v14h4v-14h14z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'advanced-list'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0h16v4h-16v-4zM16 8h10v4h-10v-4zM0 0h12v12h-12v-12zM16 20h16v4h-16v-4zM16 28h10v4h-10v-4zM0 20h12v12h-12v-12z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'alert'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 0l-16 32h32l-16-32zM18 28h-4v-4h4v4zM14 20v-10h4v10h-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'anchor'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 16h-8.485l2.508 2.508c-1.793 2.757-4.656 4.749-8.022 5.318v-14.248c1.765-0.772 3-2.529 3-4.578 0-2.761-2.239-5-5-5s-5 2.239-5 5c0 2.049 1.235 3.806 3 4.578v14.248c-3.366-0.569-6.23-2.561-8.022-5.318l2.508-2.508h-8.485v8.485l3.091-3.090c2.004 2.754 4.858 4.842 8.18 5.876l4.729 4.729 4.729-4.729c3.322-1.034 6.176-3.122 8.18-5.876l3.091 3.091v-8.485zM13 5c0-1.654 1.346-3 3-3s3 1.346 3 3-1.346 3-3 3-3-1.346-3-3z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'approve'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M12 28l-12-12 4-4 8 8 16-16 4 4-20 20z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'archive'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 16h-32v16h32v-16zM20 24v2h-8v-4h2v2h4v-2h2v2zM10 8h12v2h-12v-2zM12 4h8v2h-8v-2zM10 2h12.001l6.857 12h3.143l-8-14h-16l-8 14h3.143l6.857-12zM8 12h16v2h-16v-2z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'attach'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M22 8v16c0 3.308-2.692 6-6 6s-6-2.692-6-6v-18c0-2.206 1.794-4 4-4s4 1.794 4 4v16c0 1.103-0.897 2-2 2s-2-0.897-2-2v-14h-2v14c0 2.2 1.8 4 4 4s4-1.8 4-4v-16c0-3.3-2.7-6-6-6s-6 2.7-6 6v18c0 4.4 3.6 8 8 8s8-3.6 8-8v-16h-2z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'ai'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M7.187 11.68H0V3.79h2.173v5.86h5.014v2.03zM32 28.21h-2.173v-5.86h-5.014v-2.03H32v7.89zM32 16h-2.173c0-7.63-6.197-13.827-13.827-13.827-5.92 0-11.106 3.714-13.12 9.162l-1.967-.77C4.3 4.134 9.75 0 16 0c8.837 0 16 7.163 16 16zM16 32C7.163 32 0 24.837 0 16h2.173c0 7.63 6.197 13.827 13.827 13.827 5.92 0 11.106-3.714 13.12-9.162l1.967.77C27.7 27.866 22.25 32 16 32zM17.093 23.998h-2.187c0-3.605-2.823-6.427-6.427-6.427v-2.187c3.605 0 6.427-2.823 6.427-6.427h2.187c0 3.605 2.823 6.427 6.427 6.427v2.187c-3.605 0-6.427 2.823-6.427 6.427zM12.933 16c1.218.686 2.226 1.693 2.913 2.913.686-1.218 1.693-2.226 2.913-2.913-1.218-.686-2.226-1.693-2.913-2.913-.686 1.218-1.693 2.226-2.913 2.913z"
                    fill="currentColor"
                />
            </svg>

            <svg
                v-else-if="props.name === 'audio'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 10v12h4l10 10v-32l-10 10zM27.122 0.878l-1.435 1.434c2.71 3.886 4.313 8.6 4.313 13.688s-1.602 9.801-4.313 13.688l1.435 1.434c3.058-4.264 4.878-9.475 4.878-15.122s-1.82-10.858-4.878-15.122zM21.356 6.644l-1.459 1.459c1.331 2.333 2.103 5.025 2.103 7.897s-0.771 5.564-2.103 7.897l1.459 1.459c1.666-2.729 2.644-5.924 2.644-9.356s-0.978-6.627-2.644-9.356z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'badge'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24.486 20.485l0.75-4.658 2.764-3.827-2.762-3.827-0.75-4.658-4.661-0.754-3.827-2.761-3.827 2.761-4.659 0.754-0.75 4.658-2.764 3.827 2.762 3.827 0.75 4.658 2.074 0.336-3.237 9.182 3.451-0.766 2.206 2.762 3.063-8.673 0.932 0.673 0.933-0.673 3.063 8.673 2.206-2.762 3.451 0.766-3.241-9.183zM16 20c-4.418 0-8-3.582-8-8s3.582-8 8-8c4.418 0 8 3.582 8 8v0c0 4.418-3.582 8-8 8v0z"
                ></path>
                <path
                    d="M8.639 9.547l3.681 3.588-0.869 5.065 4.549-2.391 4.549 2.391-0.869-5.065 3.681-3.588-5.086-0.739-2.274-4.609-2.274 4.609-5.086 0.739z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'boarder-leave'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M28 14v6h-24v-16h-4v24h4v-4h24v4h4v-14zM12 12v-6h-4v10h16v-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'breadcrumbs'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M8 24l6-8-6-8h-8l6 8-6 8zM26 24l6-8-6-8h-8l6 8-6 8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'calendar'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M28 2v-2h-4v2h-6v-2h-4v2h-6v-2h-4v2h-4v30h32v-30h-4zM10 30h-8v-6h8v6zM10 22h-8v-6h8v6zM10 14h-8v-6h8v6zM20 30h-8v-6h8v6zM20 22h-8v-6h8v6zM20 14h-8v-6h8v6zM30 22h-8v-6h8v6zM30 14h-8v-6h8v6z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'canvas-lms'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M4.643 15.965c0-0 0-0.001 0-0.001 0-2.355-1.762-4.298-4.040-4.582l-0.023-0.002c-0.369 1.376-0.581 2.956-0.581 4.585s0.212 3.209 0.61 4.713l-0.029-0.128c2.3-0.283 4.063-2.225 4.063-4.578 0-0.002 0-0.005 0-0.007v0z"
                ></path>
                <path
                    d="M7.327 14.517c-0.001 0-0.003 0-0.005 0-0.803 0-1.453 0.651-1.453 1.453s0.651 1.453 1.453 1.453c0.801 0 1.45-0.648 1.453-1.448v-0.010c0-0.002 0-0.004 0-0.006 0-0.797-0.646-1.442-1.442-1.442-0.002 0-0.004 0-0.006 0h0z"
                ></path>
                <path
                    d="M27.357 15.965c0 0 0 0 0 0 0 2.355 1.762 4.298 4.040 4.582l0.023 0.002c0.369-1.376 0.581-2.956 0.581-4.585s-0.212-3.209-0.61-4.713l0.029 0.128c-2.299 0.29-4.060 2.231-4.063 4.585v0z"
                ></path>
                <path
                    d="M24.663 14.517c-0.005-0-0.010-0-0.016-0-0.803 0-1.453 0.651-1.453 1.453s0.651 1.453 1.453 1.453c0.797 0 1.445-0.642 1.453-1.437v-0.021c-0.002-0.795-0.643-1.44-1.437-1.448h-0.001z"
                ></path>
                <path
                    d="M15.965 27.357c-0 0-0.001 0-0.001 0-2.355 0-4.298 1.762-4.582 4.040l-0.002 0.023c1.376 0.369 2.956 0.581 4.585 0.581s3.209-0.212 4.713-0.61l-0.128 0.029c-0.287-2.3-2.23-4.063-4.585-4.063-0 0-0 0-0 0h0z"
                ></path>
                <path
                    d="M15.965 23.224c-0.002 0-0.004 0-0.006 0-0.803 0-1.453 0.651-1.453 1.453s0.651 1.453 1.453 1.453c0.801 0 1.45-0.648 1.453-1.448v-0.010c0-0.8-0.648-1.448-1.447-1.448h-0z"
                ></path>
                <path
                    d="M15.965 4.643c0 0 0 0 0 0 2.355 0 4.298-1.762 4.582-4.040l0.002-0.023c-1.376-0.369-2.956-0.581-4.585-0.581s-3.209 0.212-4.713 0.61l0.128-0.029c0.287 2.3 2.229 4.063 4.584 4.063 0 0 0.001 0 0.001 0h-0z"
                ></path>
                <path
                    d="M15.965 5.879c-0 0-0 0-0.001 0-0.8 0-1.448 0.648-1.448 1.448s0.648 1.448 1.448 1.448c0.8 0 1.448-0.648 1.448-1.448v0c0-0.8-0.648-1.448-1.447-1.448h-0z"
                ></path>
                <path
                    d="M24.009 24.009c-0.835 0.834-1.352 1.988-1.352 3.261 0 1.077 0.369 2.067 0.988 2.852l-0.007-0.010c2.692-1.607 4.881-3.796 6.442-6.403l0.046-0.084c-0.777-0.602-1.765-0.965-2.838-0.965-1.278 0-2.436 0.515-3.278 1.349l0-0z"
                ></path>
                <path
                    d="M21.082 21.082c-0.263 0.263-0.425 0.625-0.425 1.026 0 0.801 0.649 1.451 1.451 1.451s1.451-0.649 1.451-1.451c0-0.401-0.162-0.763-0.425-1.026v0c-0.263-0.261-0.626-0.422-1.026-0.422s-0.762 0.161-1.026 0.422l0-0z"
                ></path>
                <path
                    d="M7.951 7.951c0.836-0.837 1.353-1.992 1.353-3.269 0-1.073-0.365-2.061-0.979-2.845l0.008 0.010c-2.69 1.607-4.878 3.795-6.439 6.402l-0.046 0.084c0.774 0.605 1.762 0.971 2.835 0.971 1.276 0 2.432-0.517 3.269-1.353l-0 0z"
                ></path>
                <path
                    d="M8.826 8.826c-0.263 0.263-0.425 0.625-0.425 1.026 0 0.801 0.649 1.451 1.451 1.451s1.451-0.649 1.451-1.451c0-0.401-0.162-0.763-0.425-1.026v0c-0.263-0.261-0.626-0.422-1.026-0.422s-0.762 0.161-1.026 0.423l0-0z"
                ></path>
                <path
                    d="M24 7.931c0.837 0.835 1.992 1.352 3.268 1.352 1.073 0 2.060-0.365 2.844-0.978l-0.010 0.008c-1.607-2.69-3.795-4.878-6.401-6.439l-0.084-0.046c-0.606 0.774-0.971 1.762-0.971 2.834 0 1.277 0.518 2.432 1.354 3.269l0 0z"
                ></path>
                <path
                    d="M23.125 10.867c0.263-0.263 0.425-0.625 0.425-1.026 0-0.801-0.649-1.451-1.451-1.451s-1.451 0.649-1.451 1.451c0 0.401 0.162 0.763 0.425 1.026v0c0.263 0.261 0.626 0.422 1.026 0.422s0.762-0.161 1.026-0.423l-0 0z"
                ></path>
                <path
                    d="M7.941 24c-0.836-0.836-1.992-1.354-3.268-1.354-1.073 0-2.061 0.366-2.845 0.98l0.010-0.008c1.603 2.69 3.788 4.877 6.392 6.437l0.083 0.046c0.609-0.775 0.976-1.765 0.976-2.84 0-1.273-0.515-2.426-1.348-3.262l0 0z"
                ></path>
                <path
                    d="M8.816 21.063c-0.263 0.263-0.425 0.625-0.425 1.026 0 0.801 0.649 1.451 1.451 1.451s1.451-0.649 1.451-1.451c0-0.401-0.162-0.763-0.425-1.026v0c-0.263-0.261-0.626-0.422-1.026-0.422s-0.762 0.161-1.026 0.423l0-0z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'chat'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 0v24h10l12 8v-8h10v-24h-32zM8 12h-4v-4h4v4zM16 20c-3.314 0-6-2.686-6-6h12c0 3.314-2.686 6-6 6zM28 12h-4v-4h4v4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'class-list'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M20 2v-2h-8v2h-10v30h28v-30h-10zM26 28h-20v-22h6v2h8v-2h6v22zM10 12h12v4h-12v-4zM10 20h12v4h-12v-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'click-view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M6 32v-32l20 16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'clipboard'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26.347 9.614l-2.582 16.426c-0.224 1.424-1.56 2.397-2.984 2.172l-15.644-2.46c-1.424-0.224-2.397-1.559-2.173-2.983l2.583-16.426c0.218-1.382 1.482-2.339 2.857-2.189l-0.011 0.069c-0.216 1.425 0.764 2.756 2.189 2.972l10.777 1.635c1.419 0.215 2.744-0.755 2.969-2.17 1.339 0.293 2.234 1.583 2.018 2.955zM10.359 15.652c-0.293 0.21-0.36 0.617-0.151 0.911l2.236 3.12c0.235 0.416 0.609 0.384 0.912 0.149l6.552-4.732c0.292-0.211 0.358-0.619 0.147-0.911s-0.619-0.358-0.911-0.147l-6.021 4.348-1.854-2.588c-0.21-0.293-0.617-0.36-0.911-0.151z"
                ></path>
                <path
                    d="M29.069 12.762v16.628c0 1.441-1.169 2.61-2.61 2.61h-15.837c-1.441 0-2.61-1.169-2.61-2.61v-1.813l13.965 2.196c1.424 0.224 2.76-0.749 2.984-2.172l2.582-16.426c0.042-0.267 0.042-0.531 0.005-0.785 0.898 0.412 1.521 1.32 1.521 2.373z"
                ></path>
                <path
                    d="M10.886 2.415l3.564 0.476c-0.035-0.163-0.054-0.331-0.054-0.504 0-1.318 1.068-2.386 2.386-2.386s2.386 1.068 2.386 2.386c0 0.396-0.096 0.77-0.267 1.099l3.518 0.47c0.357 0.048 0.608 0.376 0.56 0.733l-0.267 2.005c-0.047 0.357-0.376 0.608-0.733 0.561l-11.534-1.54c-0.357-0.048-0.608-0.376-0.56-0.733l0.268-2.005c0.047-0.357 0.376-0.608 0.733-0.561zM16.798 1.679c-0.418 0-0.756 0.338-0.756 0.756s0.338 0.756 0.756 0.756 0.756-0.338 0.756-0.756-0.338-0.756-0.756-0.756z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'close'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M28 0l-12 12-12-12-4 4 12 12-12 12 4 4 12-12 12 12 4-4-12-12 12-12z"></path>
            </svg>
            <svg
                v-else-if="['cloud', 'cloudy'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M6 14c0-4.418 3.582-8 8-8v0c3.734 0 6.843 2.569 7.726 6.027 0.093-0.004 0.181-0.027 0.274-0.027 3.314 0 6 2.686 6 6v0c2.209 0 4 1.791 4 4s-1.791 4-4 4v0h-22c-3.314 0-6-2.686-6-6s2.686-6 6-6v0z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'collab-docs'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M20 0h-16v32h24v-24l-8-8zM8 24l2-4h4l2 4h-8zM12 18c-2.209 0-4-1.791-4-4s1.791-4 4-4c2.209 0 4 1.791 4 4s-1.791 4-4 4zM20.473 24l-2-4h3.527l2 4h-3.527zM20 18c-0.361 0-0.704-0.063-1.037-0.153 0.646-1.142 1.037-2.443 1.037-3.847 0-1.402-0.394-2.702-1.031-3.849 0.331-0.088 0.672-0.151 1.031-0.151 2.209 0 4 1.791 4 4s-1.791 4-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'collapse'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 14h32v4h-32v-4z"></path>
            </svg>
            <svg
                v-else-if="['comment', 'blog'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0v24h10l12 8v-8h10v-24h-32z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'compass'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M 16.195312 5.609375 C 8.917969 5.609375 3 11.511719 3 18.804688 C 3 26.097656 8.917969 32 16.195312 32 C 23.472656 32 29.390625 26.082031 29.390625 18.804688 C 29.402344 11.511719 23.488281 5.609375 16.195312 5.609375 Z M 16.195312 28.207031 C 11 28.207031 6.792969 24 6.792969 18.804688 C 6.792969 13.609375 11 9.402344 16.195312 9.402344 C 21.390625 9.402344 25.597656 13.609375 25.597656 18.804688 C 25.597656 24 21.390625 28.207031 16.195312 28.207031 Z M 16.195312 28.207031 "
                />
                <path
                    d="M 12.175781 4.007812 L 11.375 1.027344 C 11.320312 0.816406 11.441406 0.601562 11.65625 0.546875 C 14.632812 -0.171875 17.730469 -0.1875 20.710938 0.535156 C 20.921875 0.585938 21.042969 0.800781 20.988281 1 C 20.988281 1 20.988281 1 20.988281 1.015625 L 20.1875 3.992188 C 20.136719 4.195312 19.933594 4.3125 19.734375 4.261719 C 17.398438 3.699219 14.964844 3.699219 12.644531 4.261719 C 12.441406 4.3125 12.230469 4.195312 12.175781 4.007812 Z M 12.175781 4.007812 "
                />
                <path
                    d="M 17.011719 21.582031 L 12.054688 22.945312 L 13.429688 18.003906 C 13.699219 17.054688 14.445312 16.308594 15.394531 16.039062 L 20.335938 14.664062 L 18.972656 19.621094 C 18.707031 20.566406 17.972656 21.316406 17.011719 21.582031 Z M 17.011719 21.582031 "
                />
            </svg>
            <svg
                v-else-if="props.name === 'components'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M6 0h-4v24h-2v8h8v-8h-2zM32 0h-8v8h2v24h4v-24h2zM18 0h-4v12h-2v8h2v12h4v-12h2v-8h-2z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'copy'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 6l-6-6h-10v8.343l4 4v11.657h12zM10 8h-10v24h16v-18l-6-6z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'countdown'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M9.575 1.206c-1.001-0.748-2.229-1.206-3.575-1.206-3.314 0-6 2.686-6 6 0 0.958 0.245 1.852 0.645 2.655 2.061-3.374 5.193-6.015 8.93-7.449zM31.355 8.655c0.399-0.804 0.645-1.697 0.645-2.655 0-3.314-2.686-6-6-6-1.345 0-2.574 0.458-3.575 1.206 3.736 1.435 6.868 4.075 8.93 7.449zM30 18c0-7.733-6.267-14-14-14s-14 6.267-14 14c0 4.376 2.009 8.28 5.153 10.847l-3.153 3.153h24l-3.153-3.153c3.144-2.567 5.153-6.471 5.153-10.847zM18 22h-8v-4h4v-6h4v10z"
                ></path>
            </svg>
            <svg
                v-else-if="['course-builder', 'course'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26 16c-0.732 0-1.424 0.15-2.072 0.39l-2.786-3.343c0.532-0.896 0.858-1.929 0.858-3.047 0-3.313-2.686-6-6-6s-6 2.687-6 6c0 1.117 0.326 2.151 0.858 3.047l-2.786 3.343c-0.648-0.24-1.341-0.39-2.072-0.39-3.314 0-6 2.687-6 6s2.686 6 6 6 6-2.687 6-6c0-1.117-0.326-2.151-0.858-3.047l2.786-3.343c0.648 0.24 1.34 0.39 2.072 0.39s1.424-0.15 2.072-0.39l2.786 3.343c-0.532 0.896-0.858 1.929-0.858 3.047 0 3.313 2.686 6 6 6s6-2.687 6-6c0-3.313-2.686-6-6-6zM16 14c-2.206 0-4-1.794-4-4s1.794-4 4-4 4 1.794 4 4-1.794 4-4 4zM26 26c-2.206 0-4-1.794-4-4s1.794-4 4-4 4 1.794 4 4-1.794 4-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'course-outline'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.837 0-16 7.163-16 16s7.163 16 16 16 16-7.163 16-16-7.163-16-16-16zM12 26l-8-8 4-4 4 4 10-10 4 4-14 14z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'criteria'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 4h16v8h-16v-8zM16 12h16v8h-16v-8zM0 20h16v8h-16v-8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'curriculum'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M4 26v-20l-4-2v24l16 4 16-4v-24l-4 2v20l-12 4zM6 0v22l8 4v-22zM26 0l-8 4v22l8-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'curriculum-details'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 0h-14l-6 6v26h20v-26h-16l4-4h14v26h2v-28h-4zM16 26l-2-2-2 2v-6.369c0.629 0.224 1.295 0.369 2 0.369s1.371-0.145 2-0.369v6.369zM14 10c2.209 0 4 1.791 4 4s-1.791 4-4 4-4-1.791-4-4c0-2.209 1.791-4 4-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'danger'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0l-16 16 15.833 16 16.167-16-16-16zM16 26l-2-4h2l2-6-6 2 2-12h4l-4 10 8-2-4 8h2l-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'day-view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0h32v32h-32v-32z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'delete'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M4 10h24v22h-24v-22zM22 2v-2h-12v2h-10v4h32v-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'directory'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M30 8v-4h-2v-4h-26v30h6v2h6v-2h14v-6h2v-4h-2v-4h2v-4h-2v-4zM16 4c2.209 0 4 1.791 4 4s-1.791 4-4 4c-2.209 0-4-1.791-4-4v0c0-2.209 1.791-4 4-4v0zM14 14h4l2 4h-8zM26 28h-12v-2h-6v2h-2v-4h20z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'division'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 32c11.958-7.958 12-22 12-22v-4l-12-6-12 6v4s0.042 14.042 12 22z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'document'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M18 10v-10h-14v32h24v-22h-8zM28 8l-8-8v8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'dollar'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M19.446 13.756q-3.849-1.438-4.718-1.98t-0.868-1.229q0-0.639 0.684-1.070t2.188-0.431q2.911 0 6.914 1.5l2.364-4.87c-2.713-0.987-5.378-1.513-8-1.625v-4.051h-4v4.206c-1.968 0.261-3.63 0.779-4.934 1.598q-2.871 1.805-2.871 5.030 0 1.724 0.673 2.986t2.070 2.236 4.191 2.012q3.086 1.134 3.8 1.5c0.473 0.245 0.82 0.487 1.033 0.727 0.2 0.215 0.323 0.504 0.323 0.822 0 0.006-0 0.011-0 0.017v-0.001q0 0.766-0.801 1.254t-2.52 0.488q-1.992 0-4.375-0.519c-1.762-0.386-3.296-0.883-4.761-1.51l0.171 0.065v5.621c1.148 0.473 2.498 0.869 3.898 1.123l0.125 0.019c1.099 0.188 2.434 0.288 3.977 0.316v4.011h4v-4.233c1.044-0.16 1.976-0.405 2.866-0.734l-0.1 0.032q2.539-0.934 3.886-2.619c0.84-1.024 1.349-2.348 1.349-3.79 0-0.023-0-0.047-0-0.070l0 0.004q0-2.363-1.438-3.92t-5.125-2.914z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'down-arrow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 26l16-16-4-4-12 12-12-12-4 4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'download'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 23.999l12-12h-8v-12h-8v12h-8zM28 28h-24l0 4h24z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'drag-drop'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 12h4v4h-4v-4zM0 20h4v4h-4v-4zM0 28h4v4h-4v-4zM8 28h4v4h-4v-4zM16 28h4v4h-4v-4zM28 4v16h-16v-16h16zM32 0h-24v24h24v-24z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'drop-box'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M18 10v-10h-14v32h10v-8h-6l8-8 8 8h-6v8h10v-22h-8zM20 0v8h8z"></path>
            </svg>
            <svg
                v-else-if="['dropdown', 'sort-down'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 8h-32l16 16z"></path>
            </svg>
            <svg
                v-else-if="['dialog-agent'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M7.187 11.68H0V3.79h2.173v5.86h5.014v2.03zM32 28.21h-2.173v-5.86h-5.014v-2.03H32v7.89zM32 16h-2.173c0-7.63-6.197-13.827-13.827-13.827-5.92 0-11.106 3.714-13.12 9.162l-1.967-.77C4.3 4.134 9.75 0 16 0c8.837 0 16 7.163 16 16zM16 32C7.163 32 0 24.837 0 16h2.173c0 7.63 6.197 13.827 13.827 13.827 5.92 0 11.106-3.714 13.12-9.162l1.967.77C27.7 27.866 22.25 32 16 32zM17.093 23.998h-2.187c0-3.605-2.823-6.427-6.427-6.427v-2.187c3.605 0 6.427-2.823 6.427-6.427h2.187c0 3.605 2.823 6.427 6.427 6.427v2.187c-3.605 0-6.427 2.823-6.427 6.427zM12.933 16c1.218.686 2.226 1.693 2.913 2.913.686-1.218 1.693-2.226 2.913-2.913-1.218-.686-2.226-1.693-2.913-2.913-.686 1.218-1.693 2.226-2.913 2.913z"
                    fill="currentColor"
                />
            </svg>
            <svg
                v-else-if="['due-work', 'import', 'submissions'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32.001 22l-4-4h-4l4 4h-8c0 2.209-1.791 4-4 4s-4-1.791-4-4v0h-8l4-4h-4l-4 4v10h32.001zM20 10v-10h-8v10h-6l10 10 10-10h-6z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'edit-columns'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M20 0h12v32h-12v-32zM0 20h12v12h-12v-12zM0 0h12v12h-12v-12z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'education-maintenance'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 10v-10h-14v32h9.984l0.016-6-4-4v-4l4-4v6h4v-6l4 4v4l-4 4v6h10v-22h-10zM20 0v8h8l-8-8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'email'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 18l14-14h-28zM16 24l-16-16v20h32v-20z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'eng-second-lang'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 6h2v4h-2v-4zM0 0v24h10l12 8v-8h10v-24zM12 6h-4v4h2v2h-2v4h4v2h-6v-14h6zM20 6v12h-2v-6h-2v6h-2v-14h6zM28 18h-6v-14h2v12h4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'engage-logo'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M19.352 11.359l12.207-9.828 0.441 2.423-12.648 7.404z"></path>
                <path d="M18.782 11.944l13.218-7.808-0.169 5.569-13.049 2.239z"></path>
                <path d="M18.637 12.144l13.191-2.296-2.453 7.228-10.738-4.932z"></path>
                <path d="M19.747 12.873l9.573 4.349-0.236 1.329-9.337-5.678z"></path>
                <path d="M22.368 19.934l6.515-1.274-10.337-6.334 3.822 7.607z"></path>
                <path d="M18.346 12.326l3.785 7.444-4.713-2.476 0.928-4.969z"></path>
                <path d="M16.581 21.554l0.837-4.132 4.64 2.586-5.477 1.546z"></path>
                <path d="M17.163 21.554l5.186-1.438 4.623 1.702-9.809-0.264z"></path>
                <path d="M16.726 21.745l10.483 0.236-6.233 3.867-4.249-4.104z"></path>
                <path d="M21.194 25.931l6.152-3.804-1.648 5.651-4.504-1.847z"></path>
                <path d="M21.385 26.222l4.231 1.702v2.448l-4.231-4.149z"></path>
                <path d="M12.594 11.456l-12.155-9.828-0.439 2.424 12.594 7.404z"></path>
                <path d="M13.161 12.042l-13.161-7.808 0.168 5.568 12.993 2.239z"></path>
                <path d="M13.306 12.241l-13.135-2.296 2.443 7.229 10.692-4.933z"></path>
                <path d="M12.2 12.97l-9.532 4.349 0.236 1.329 9.296-5.678z"></path>
                <path d="M9.591 20.031l-6.488-1.274 10.293-6.333-3.805 7.607z"></path>
                <path d="M13.596 12.424l-3.769 7.443 4.692-2.474-0.924-4.969z"></path>
                <path d="M15.354 21.651l-0.834-4.131-4.621 2.586 5.455 1.545z"></path>
                <path d="M14.774 21.651l-5.165-1.438-4.603 1.701 9.768-0.264z"></path>
                <path d="M15.209 21.841l-10.439 0.238 6.207 3.867 4.232-4.104z"></path>
                <path d="M10.759 26.027l-6.125-3.803 1.64 5.651 4.485-1.848z"></path>
                <path d="M10.569 26.319l-4.213 1.702v2.448l4.213-4.15z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'eportfolio'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M24 10v-10h-16v10h-8v22h32v-22h-8zM12 4h8v6h-8v-6z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'equals'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 20h32v4h-32v-4zM0 8h32v4h-32v-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'export'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M18 10v-10h-14v18h8v-6l8 8-8 8v-6h-8v10h24v-22h-8zM20 0v8h8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'external-link'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M28 28h-24v-24h12v-4h-16v32h32v-16h-4v12z"></path>
                <path d="M20.686 0l4.242 4.243-16.97 16.97 2.828 2.828 16.97-16.97 4.243 4.242v-11.314h-11.314z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'favourite'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0.783l4.945 10.019 11.055 1.606-8 7.798 1.888 11.011-9.888-5.198-9.889 5.198 1.889-11.011-8-7.798 11.055-1.606z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'favourite-hollow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 12.408l-11.056-1.606-4.944-10.019-4.944 10.019-11.056 1.606 8 7.798-1.889 11.011 9.889-5.198 9.887 5.198-1.887-11.011zM16 23.307l-6.701 3.523 1.279-7.462-5.42-5.284 7.49-1.089 3.352-6.79 3.351 6.79 7.491 1.089-5.42 5.284 1.279 7.462z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'files'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M8 6h-4v26h16v-4h-12zM20 0h-10v26h18v-18h-8zM22 0l6 6h-6z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'filter'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 3.581-16 8 0 2.637 2.563 4.968 6.498 6.426l5.502 7.86v9.714h8v-9.714l5.502-7.86c3.935-1.457 6.498-3.789 6.498-6.426 0-4.419-7.164-8-16-8zM16 12c-7.744 0-12-3.004-12-4s4.256-4 12-4c7.744 0 12 3.004 12 4s-4.256 4-12 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'flame'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 10c0 4-2 6-2 6-2-12-10-16-10-16 2 10-8 14-8 22 0 10 10 10 10 10s-8-4-4-12c0 0 0 2 2 4 0 0 0-6 4-10 0 6 6 6 6 12 0 4-4 6-4 6s10 0 10-10c0-8-4-12-4-12z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'folder'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M28 10v-4h-16v-4h-12v24l4-16zM8 14l-4 16h24l4-16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'forms'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M18 10v-10h-14v32h24v-22h-10zM24 26h-16v-2h16v2zM24 20h-16v-2h16v2zM28 8l-8-8v8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'forum'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M12 12v14h6l8 6v-6h6v-14zM18 8v-8h-18v14h4v6l4-3v-9z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'geosnapshot'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 20.274h10.969l-10.969-10.969v10.969z"></path>
                <path d="M0 6.203l9.486 9.484v-15.688h-6.233s-3.253-0.048-3.253 3.646z"></path>
                <path d="M11.654 0.004v10.969l10.969-10.969h-10.969z"></path>
                <path d="M32 11.737h-10.969l10.969 10.969v-10.969z"></path>
                <path d="M20.189 31.996v-10.969l-10.969 10.969h10.969z"></path>
                <path d="M32 25.797l-9.486-9.484v15.688h6.236s3.253 0.047 3.253-3.646z"></path>
                <path d="M6.203 32l9.484-9.486h-15.688v6.236s-0.048 3.25 3.646 3.25z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'goal'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 18c0 5.514-4.486 10-10 10s-10-4.486-10-10 4.486-10 10-10c1.448 0.002 2.823 0.314 4.063 0.873l-0.063-0.025v-4.26c-1.197-0.372-2.573-0.586-3.999-0.588h-0.001c-7.732 0-14 6.268-14 14s6.268 14 14 14 14-6.268 14-14c-0.001-1.427-0.216-2.803-0.614-4.099l0.026 0.099h-4.259c0.534 1.177 0.846 2.552 0.848 3.999v0.001z"
                ></path>
                <path
                    d="M14 22c-2.209-0-3.999-1.791-3.999-4 0-1.104 0.448-2.104 1.171-2.828l3.144-3.144c-0.107-0.006-0.209-0.028-0.316-0.028-0.002 0-0.004 0-0.006 0-3.317 0-6.006 2.689-6.006 6.006s2.689 6.006 6.006 6.006c3.317 0 6.006-2.689 6.006-6.006 0-0.002 0-0.004 0-0.006v0c0-0.107-0.026-0.207-0.031-0.313l-3.141 3.141c-0.722 0.724-1.72 1.172-2.823 1.172-0.002 0-0.004 0-0.005 0h0z"
                ></path>
                <path
                    d="M32 6h-6v-6l-6 6v3.879l-7.063 7.059c-0.272 0.272-0.44 0.648-0.44 1.063 0 0.83 0.673 1.503 1.503 1.503 0.415 0 0.791-0.168 1.063-0.44l7.063-7.063h3.875z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'google-drive'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M22.097 2.083l-10.843 0.11 9.354 16.201 10.843-0.108-9.354-16.202zM9.354 3.987l-9.354 16.201 5.515 9.337 9.354-16.202-5.515-9.336zM9.354 3.987l-9.354 16.201 5.515 9.337 9.354-16.202-5.515-9.336zM13.292 20.468l-5.328 9.443h18.708l5.328-9.443h-18.708z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'grip'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M6 0h2v2h-2v-2zM6 6h2v2h-2v-2zM6 12h2v2h-2v-2zM6 18h2v2h-2v-2zM6 24h2v2h-2v-2zM6 30h2v2h-2v-2zM12 0h2v2h-2v-2zM12 6h2v2h-2v-2zM12 12h2v2h-2v-2zM12 18h2v2h-2v-2zM12 24h2v2h-2v-2zM12 30h2v2h-2v-2zM18 0h2v2h-2v-2zM18 6h2v2h-2v-2zM18 12h2v2h-2v-2zM18 18h2v2h-2v-2zM18 24h2v2h-2v-2zM18 30h2v2h-2v-2zM24 0h2v2h-2v-2zM24 6h2v2h-2v-2zM24 12h2v2h-2v-2zM24 18h2v2h-2v-2zM24 24h2v2h-2v-2zM24 30h2v2h-2v-2z"
                ></path>
            </svg>
            <svg
                v-else-if="['group', 'team'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M6 6c-3.314 0-6 2.686-6 6s2.686 6 6 6c0-2.26 0.782-4.323 2.051-6-1.269-1.677-2.051-3.74-2.051-6zM26 18c3.314 0 6-2.687 6-6s-2.686-6-6-6c0 2.26-0.782 4.323-2.051 6 1.269 1.677 2.051 3.74 2.051 6zM22 6c0 3.313-2.686 6-6 6s-6-2.687-6-6c0-3.314 2.686-6 6-6s6 2.686 6 6zM6 18c-3.314 0-6 2.686-6 6v8h6v-14zM26 18v14h6v-8c0-3.314-2.686-6-6-6zM22 18c0-3.314-2.686-6-6-6s-6 2.686-6 6v14h12v-14z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'group-managers'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M28 10v-4h-16v-4h-12v24l4-16zM8 14l-4 16h24l4-16h-24zM22 28h-8l2.802-4.203c-1.62-0.512-2.802-2.008-2.802-3.797 0-2.209 1.791-4 4-4s4 1.791 4 4c0 1.789-1.182 3.286-2.802 3.797l2.802 4.203z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'heart-like'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M15.998 32l16.002-16v-10l-6-6h-6l-4 4-4-4h-6l-6 6v10l15.998 16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'help'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.163-16 16s7.164 16 16 16c8.837 0 16-7.164 16-16s-7.163-16-16-16zM17.107 25.7c-0.419 0.415-1.003 0.622-1.75 0.622-0.766 0-1.358-0.203-1.777-0.608s-0.629-0.978-0.629-1.716c0-0.766 0.205-1.345 0.615-1.736s1.007-0.588 1.791-0.588c0.757 0 1.342 0.2 1.757 0.602s0.622 0.976 0.622 1.723c0 0.72-0.21 1.287-0.629 1.702zM21.619 13.238c-0.456 0.729-1.321 1.55-2.598 2.461-0.875 0.647-1.429 1.14-1.661 1.477s-0.349 0.779-0.349 1.326v0.82h-3.623v-1.012c0-0.875 0.187-1.636 0.561-2.283s1.058-1.335 2.051-2.064c0.957-0.684 1.588-1.239 1.894-1.668s0.458-0.907 0.458-1.436c0-0.593-0.219-1.044-0.656-1.354s-1.048-0.465-1.832-0.465c-1.367 0-2.926 0.446-4.676 1.34l-1.49-2.994c2.032-1.14 4.189-1.709 6.467-1.709 1.878 0 3.37 0.451 4.478 1.354s1.661 2.105 1.661 3.609c0 1.003-0.228 1.868-0.684 2.598z"
                ></path>
            </svg>
            <svg
                v-else-if="['hidden', 'view-hollow'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 24c-0.016 0-0.036 0-0.055 0-4.388 0-7.945-3.557-7.945-7.945 0-0.653 0.079-1.287 0.227-1.894l-0.011 0.054-2.761-2.761c-1.708 1.836-3.295 3.646-5.454 4.546 5.333 2.222 7.164 10 16 10 0.014 0 0.031 0 0.048 0 1.233 0 2.424-0.18 3.549-0.514l-0.088 0.022-1.724-1.724c-0.534 0.132-1.149 0.211-1.781 0.216l-0.003 0z"
                ></path>
                <path
                    d="M32 16c-5.333-2.222-7.164-10-16-10-2.625 0-4.62 0.694-6.283 1.718l-5.717-5.718-2 2 26 26 2-2-5.462-5.462c2.602-2.374 4.501-5.304 7.462-6.538zM16 8c0.002 0 0.004 0 0.007 0 4.414 0 7.992 3.578 7.992 7.992 0 1.711-0.538 3.296-1.453 4.596l0.017-0.025-2.949-2.949c0.226-0.5 0.386-1.031 0.386-1.614 0-2.209-1.791-4-4-4v0c-0.583 0-1.116 0.161-1.614 0.386l-2.947-2.947c1.269-0.899 2.848-1.438 4.554-1.438 0.003 0 0.006 0 0.009 0h-0z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'history'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 16.828l5.414-5.414-2.828-2.828-6.586 6.586v6.828h4zM16 0c-4.419 0-8.393 1.817-11.285 4.715l-4.715-4.715v12h12l-4.473-4.473c2.17-2.176 5.164-3.527 8.473-3.527 6.617 0 12 5.383 12 12s-5.383 12-12 12c-5.934 0-10.863-4.334-11.819-10h-4.043c0.986 7.89 7.704 14 15.862 14 8.837 0 16-7.164 16-16s-7.163-16-16-16z"
                ></path>
            </svg>
            <svg
                v-else-if="['home', 'house'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 0l-8 10v-6h-4v11l-4 5h4v12h4v-10h6v10h14v-12h4l-16-20zM22 26h-4v-4h4v4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'homepagefeed'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 14l-4 3.333v-1.333h-4v4.667l-4 3.333h4v8h6v-4h4v4h6v-8h4zM0.878 4.878l1.434 1.435c3.886-2.71 8.6-4.313 13.688-4.313s9.801 1.602 13.688 4.313l1.434-1.435c-4.264-3.058-9.475-4.878-15.122-4.878s-10.858 1.82-15.122 4.878zM6.644 10.644l1.459 1.459c2.333-1.331 5.025-2.103 7.897-2.103s5.564 0.771 7.897 2.103l1.459-1.459c-2.729-1.666-5.924-2.644-9.356-2.644s-6.627 0.978-9.356 2.644z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'image'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 0v32h32v-32h-32zM20 28h-16l8.066-16 5.95 12 4.033-8 5.95 12h-8zM24 12c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4c0 2.209-1.791 4-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'image-gallery'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 2v-2h-24v24h2v-22zM28 6v-2h-24v24h2v-22zM8 8v24h24v-24h-24zM24 28h-12l6.050-12 3.967 8 2.017-4 3.967 8h-4zM25 18c-1.657 0-3-1.343-3-3s1.343-3 3-3 3 1.344 3 3-1.343 3-3 3z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'image-slideshow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.163-16 16s7.164 16 16 16 16-7.164 16-16c0-8.837-7.164-16-16-16zM12 26v-20l12 10-12 10z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'info'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.164-16 16s7.164 16 16 16 16-7.164 16-16-7.164-16-16-16zM16 4c1.105 0 2 0.895 2 2s-0.895 2-2 2c-1.105 0-2-0.895-2-2s0.895-2 2-2zM20 26h-8v-2h2v-10h-2v-2h6v12h2z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'integration-student'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 8c0 4.418-3.582 8-8 8s-8-3.582-8-8c0-4.418 3.582-8 8-8s8 3.582 8 8zM26 32h-4v-16h4v16zM32 22v4h-16v-4h16zM19.484 28h-5.484v-8.699c-1.192 0.443-2.569 0.699-4.005 0.699-1.843 0-3.587-0.421-5.142-1.173l0.071 0.031c-2.943 3.4-4.923 9.121-4.923 13.142h20c0-1.215-0.182-2.586-0.516-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'intellischool'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M31.989 23.327c-0.011-0.115-0.027-0.216-0.048-0.315l0.003 0.019c-0.019-0.098-0.044-0.194-0.072-0.29s-0.063-0.188-0.101-0.281-0.081-0.182-0.128-0.27c-0.054-0.102-0.106-0.185-0.161-0.266l0.006 0.010c-0.061-0.091-0.119-0.17-0.181-0.245l0.004 0.005c-0.066-0.081-0.131-0.153-0.201-0.222l-0-0c-0.070-0.070-0.144-0.138-0.221-0.201s-0.157-0.125-0.241-0.178-0.168-0.106-0.256-0.153-0.179-0.089-0.271-0.125-0.188-0.071-0.281-0.101-0.193-0.053-0.29-0.073c-0.177-0.037-0.381-0.058-0.59-0.058-0.001 0-0.002 0-0.004 0h0c-0.002 0-0.004 0-0.006 0-0.103 0-0.205 0.005-0.305 0.015l0.013-0.001c-0.115 0.011-0.216 0.027-0.315 0.047l0.019-0.003c-0.098 0.019-0.194 0.044-0.289 0.073s-0.188 0.063-0.281 0.101-0.183 0.081-0.271 0.125-0.174 0.098-0.257 0.153-0.162 0.115-0.239 0.178c-0.081 0.066-0.153 0.132-0.222 0.201l-0 0c-0.070 0.069-0.135 0.141-0.197 0.217l-0.004 0.005q-0.094 0.116-0.178 0.24c-0.049 0.071-0.1 0.155-0.146 0.241l-0.008 0.015q-0.070 0.133-0.125 0.27c-0.038 0.092-0.072 0.188-0.101 0.281-0.017 0.058-0.029 0.116-0.043 0.174l-3.41-0.449c0.792-0.873 1.285-2.031 1.309-3.303l0-0.005c0.047-2.325-1.496-4.313-3.622-4.973l1.933-2.156c0.054 0.041 0.106 0.083 0.162 0.121q0.152 0.101 0.313 0.188c0.087 0.049 0.196 0.101 0.307 0.148l0.023 0.009c0.113 0.046 0.228 0.088 0.344 0.125s0.236 0.063 0.356 0.089 0.241 0.042 0.363 0.054 0.243 0.018 0.365 0.018 0.244-0.006 0.366-0.018 0.242-0.030 0.361-0.054 0.238-0.054 0.356-0.089 0.231-0.077 0.344-0.125c0.135-0.056 0.244-0.108 0.349-0.166l-0.018 0.009q0.161-0.086 0.313-0.188c0.101-0.068 0.2-0.141 0.294-0.218s0.184-0.159 0.271-0.246 0.168-0.177 0.246-0.271 0.15-0.193 0.219-0.294 0.131-0.206 0.188-0.313 0.109-0.218 0.157-0.331c0.083-0.197 0.156-0.431 0.207-0.673l0.005-0.026c0.024-0.119 0.041-0.24 0.054-0.361s0.017-0.244 0.017-0.366-0.006-0.244-0.017-0.366-0.030-0.242-0.054-0.362-0.054-0.238-0.090-0.355-0.076-0.231-0.122-0.344-0.099-0.223-0.157-0.331-0.121-0.212-0.188-0.313-0.141-0.199-0.219-0.294-0.159-0.188-0.246-0.271-0.176-0.168-0.271-0.246-0.193-0.15-0.294-0.218-0.206-0.13-0.313-0.188-0.218-0.109-0.331-0.156-0.228-0.088-0.344-0.125-0.236-0.063-0.356-0.089-0.241-0.042-0.361-0.054-0.244-0.018-0.366-0.018-0.244 0.006-0.365 0.018-0.243 0.030-0.363 0.054-0.238 0.053-0.356 0.089-0.231 0.077-0.344 0.125-0.223 0.099-0.331 0.156-0.212 0.121-0.313 0.188-0.2 0.141-0.294 0.218-0.188 0.159-0.271 0.246-0.168 0.177-0.246 0.271c-0.071 0.086-0.143 0.182-0.209 0.281l-0.008 0.013c-0.068 0.102-0.131 0.206-0.188 0.313s-0.109 0.218-0.156 0.331-0.087 0.228-0.125 0.344-0.063 0.235-0.089 0.355-0.042 0.241-0.054 0.362-0.017 0.243-0.017 0.366 0.006 0.244 0.017 0.366 0.030 0.242 0.054 0.361c0.028 0.145 0.059 0.264 0.096 0.38l-0.007-0.025c0.044 0.143 0.086 0.257 0.134 0.368l-0.009-0.024q0.071 0.17 0.156 0.331c0.058 0.108 0.121 0.212 0.188 0.313 0.075 0.112 0.146 0.208 0.222 0.3l-0.005-0.006c0.058 0.071 0.121 0.137 0.184 0.203l-0.036 0.041-2.122 2.366c-0.182-0.038-0.401-0.069-0.624-0.085l-0.016-0.001c-0.049-0.002-0.107-0.003-0.165-0.003-0.746 0-1.455 0.159-2.095 0.446l0.032-0.013c-0.147 0.066-0.318 0.104-0.499 0.104-0.449 0-0.842-0.238-1.061-0.594l-0.003-0.005c-0.67-1.077-1.848-1.784-3.191-1.784-0.882 0-1.693 0.305-2.333 0.815l0.008-0.006c-1.024 0.817-1.521 2.13-1.356 3.438l0.165 1.271c0.001 0.010 0.002 0.022 0.002 0.033 0 0.145-0.11 0.264-0.251 0.279l-0.001 0c-1.435 0.15-2.544 1.351-2.546 2.813v0c0 0.434 0.125 0.831 0.304 1.2l-1.394 0.421c-0.033-0.079-0.064-0.144-0.099-0.206l0.005 0.010c-0.041-0.079-0.081-0.145-0.123-0.208l0.005 0.007c-0.044-0.063-0.090-0.125-0.14-0.188-0.051-0.063-0.103-0.119-0.156-0.173v0c-0.056-0.055-0.113-0.107-0.173-0.156s-0.125-0.096-0.188-0.139-0.129-0.084-0.197-0.119-0.139-0.070-0.211-0.099-0.146-0.056-0.22-0.079c-0.058-0.019-0.134-0.039-0.211-0.054l-0.015-0.003c-0.076-0.015-0.153-0.026-0.231-0.034s-0.155-0.011-0.233-0.011-0.156 0.004-0.234 0.011-0.154 0.019-0.231 0.034-0.152 0.034-0.226 0.057-0.148 0.049-0.22 0.079-0.141 0.063-0.211 0.099-0.135 0.077-0.2 0.12-0.125 0.090-0.188 0.139-0.117 0.101-0.173 0.156-0.108 0.113-0.156 0.173-0.096 0.125-0.139 0.188-0.083 0.132-0.121 0.201-0.069 0.139-0.099 0.211-0.057 0.144-0.079 0.219c-0.019 0.058-0.039 0.134-0.054 0.211l-0.003 0.015c-0.013 0.063-0.025 0.142-0.033 0.222l-0.001 0.009c-0.008 0.070-0.012 0.151-0.012 0.233s0.004 0.163 0.013 0.243l-0.001-0.010c0.008 0.090 0.020 0.169 0.036 0.247l-0.002-0.014c0.015 0.076 0.034 0.152 0.057 0.226s0.049 0.148 0.078 0.22 0.063 0.142 0.099 0.211 0.078 0.136 0.121 0.201 0.089 0.125 0.139 0.188 0.102 0.118 0.156 0.173 0.113 0.107 0.173 0.157 0.125 0.096 0.188 0.139 0.132 0.083 0.2 0.12 0.139 0.070 0.211 0.1 0.146 0.056 0.22 0.079 0.151 0.041 0.226 0.056 0.154 0.027 0.231 0.034 0.156 0.011 0.234 0.011 0.155-0.004 0.233-0.011 0.154-0.019 0.231-0.034c0.092-0.018 0.168-0.038 0.243-0.061l-0.016 0.004c0.074-0.023 0.148-0.049 0.22-0.079s0.142-0.063 0.211-0.1 0.136-0.077 0.201-0.12 0.125-0.089 0.188-0.139 0.117-0.102 0.173-0.157c0.054-0.054 0.105-0.11 0.153-0.169l0.003-0.004c0.050-0.063 0.096-0.125 0.14-0.188 0.038-0.056 0.077-0.121 0.113-0.189l0.006-0.012c0.032-0.056 0.065-0.126 0.095-0.197l0.005-0.014c0.025-0.056 0.051-0.129 0.074-0.204l0.004-0.016c0.022-0.074 0.042-0.15 0.057-0.226s0.026-0.153 0.034-0.231 0.011-0.156 0.011-0.233-0.004-0.156-0.011-0.233c0-0.031-0.010-0.063-0.014-0.094l1.553-0.469c0.338 0.432 0.792 0.759 1.315 0.936l0.020 0.006c0.117 0.035 0.205 0.13 0.231 0.248l0 0.002c0.43 1.711 1.955 2.957 3.77 2.957 1.382 0 2.595-0.722 3.283-1.81l0.009-0.016c0.479-0.768 1.406-1.131 2.266-0.85 0.468 0.159 1.007 0.25 1.567 0.25 0.007 0 0.014-0 0.021-0h-0.001c1.241-0.005 2.377-0.453 3.258-1.194l-0.008 0.006 3.9 0.513c0 0.032-0.004 0.063-0.004 0.097 0 0.099 0.005 0.199 0.014 0.298 0.012 0.115 0.028 0.216 0.048 0.315l-0.003-0.019c0.023 0.117 0.048 0.214 0.078 0.309l-0.005-0.019c0.029 0.095 0.063 0.188 0.101 0.281s0.081 0.183 0.125 0.27c0.054 0.102 0.105 0.185 0.16 0.266l-0.006-0.010q0.084 0.125 0.178 0.24c0.066 0.081 0.132 0.153 0.201 0.222l0 0c0.069 0.069 0.141 0.135 0.217 0.197l0.005 0.004c0.077 0.063 0.157 0.125 0.239 0.178s0.169 0.106 0.257 0.154 0.178 0.089 0.271 0.125 0.188 0.071 0.281 0.101 0.191 0.053 0.289 0.073c0.080 0.017 0.181 0.033 0.283 0.043l0.012 0.001c0.090 0.010 0.194 0.015 0.299 0.015h0c0.21-0 0.415-0.022 0.614-0.062l-0.020 0.003c0.098-0.019 0.194-0.044 0.29-0.073s0.188-0.063 0.281-0.101 0.183-0.081 0.271-0.125 0.173-0.099 0.256-0.154 0.163-0.115 0.241-0.178 0.151-0.131 0.221-0.201c0.069-0.069 0.135-0.141 0.197-0.217l0.004-0.005c0.059-0.070 0.117-0.149 0.171-0.23l0.006-0.010c0.049-0.071 0.1-0.155 0.147-0.241l0.008-0.015c0.047-0.087 0.090-0.178 0.128-0.27s0.071-0.188 0.101-0.281 0.052-0.192 0.072-0.29c0.017-0.080 0.033-0.181 0.043-0.283l0.001-0.012c0.009-0.099 0.014-0.199 0.014-0.298s-0.002-0.207-0.011-0.306z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'international-baccalaureate'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M15.168 0h1.375c0.048 0.010 0.108 0.019 0.169 0.024l0.006 0c1.874 0.092 3.629 0.494 5.25 1.158l-0.106-0.038q4.614 1.831 7.438 5.932c1.288 1.842 2.198 4.042 2.57 6.42l0.011 0.088q0.063 0.393 0.121 0.785v3.296c-0.011 0.034-0.021 0.076-0.029 0.119l-0.001 0.006c-0.211 1.956-0.76 3.74-1.589 5.358l0.040-0.086c-1.922 3.782-4.881 6.426-8.834 7.956-0.893 0.357-1.94 0.644-3.025 0.815l-0.084 0.011-1.008 0.154h-3.36c-0.024-0.010-0.054-0.019-0.085-0.026l-0.004-0.001c-0.759-0.136-1.528-0.23-2.276-0.414-2.286-0.569-4.283-1.569-5.995-2.916l0.034 0.026c-2.573-2-4.398-4.533-5.442-7.625-0.125-0.366-0.229-0.739-0.344-1.109v-0.063c1.425-0.005 2.813-0.151 4.154-0.425l-0.135 0.023c1.808-0.354 3.407-0.869 4.918-1.547l-0.141 0.057c0.081-0.025 0.139-0.099 0.139-0.187 0-0.009-0.001-0.018-0.002-0.026l0 0.001c-0.005-1 0-2.006-0.003-3.008 0-1.045 0.004-2.091 0-3.136-0.004-0.629-0.313-1.040-0.918-1.207-0.235-0.061-0.518-0.112-0.808-0.141l-0.025-0.002c-0.203-0.026-0.41-0.027-0.612-0.039v-0.563c1.938-0.104 3.875-0.159 5.777-0.647v0.204c0.004 2.384 0.012 4.768 0.008 7.151 0 1.765 0 3.531-0.039 5.295-0.020 0.866-0.131 1.731-0.208 2.596-0.009 0.102 0.041 0.125 0.107 0.164 1.583 0.867 3.255 1.479 5.044 1.746 1.466 0.219 2.903 0.109 4.294-0.425 1.438-0.55 2.563-1.464 3.288-2.84 0.601-1.144 0.691-2.365 0.463-3.611-0.518-2.828-2.846-4.466-5.317-4.619-1.152-0.071-2.233 0.188-3.273 0.654-0.313 0.141-0.617 0.303-0.933 0.458 0-0.031-0.008-0.056-0.008-0.082 0-0.832 0-1.664 0.003-2.496 0.006-0.075 0.038-0.141 0.087-0.191l-0 0c0.931-0.97 1.78-2.034 2.526-3.171l0.052-0.084c1.445-2.192 2.522-4.766 3.071-7.53l0.023-0.137c0.019-0.097-0.009-0.143-0.1-0.179-1.64-0.689-3.542-1.117-5.536-1.183l-0.026-0.001c-0.165-0.006-0.36-0.010-0.555-0.010-2.234 0-4.359 0.466-6.283 1.307l0.101-0.039q-5.168 2.207-7.822 7.167c-0.114 0.213-0.22 0.431-0.33 0.646-0.004-0.012-0.007-0.025-0.007-0.040 0-0.020 0.005-0.038 0.014-0.054l-0 0.001c0.207-0.432 0.396-0.875 0.625-1.294q2.472-4.515 7.101-6.776c1.587-0.796 3.435-1.347 5.387-1.549l0.069-0.006c0.333-0.036 0.668-0.063 1.001-0.093zM21.438 20.688c0 0.93-0.050 1.854-0.319 2.75-0.209 0.695-0.547 1.297-1.227 1.639-0.52 0.261-1.078 0.32-1.649 0.282-0.486-0.033-0.946-0.151-1.353-0.433-0.477-0.331-0.744-0.809-0.925-1.345-0.262-0.776-0.339-1.581-0.363-2.393-0.032-1.079 0.004-2.154 0.299-3.203 0.041-0.143 0.089-0.284 0.143-0.423 0.392-1.006 1.151-1.495 2.207-1.559 0.526-0.032 1.045 0.014 1.534 0.229 0.703 0.313 1.076 0.893 1.303 1.593 0.246 0.758 0.317 1.542 0.349 2.332 0.007 0.178 0.001 0.354 0.001 0.53zM10.38 7.871c-1.313 0-2.313-0.991-2.313-2.283s1.005-2.281 2.313-2.281 2.313 0.992 2.313 2.283-1.003 2.281-2.313 2.281z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'international-student'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 16c-4.418 0-8 3.582-8 8s3.582 8 8 8c4.418 0 8-3.582 8-8v0c0-4.418-3.582-8-8-8v0zM30.313 21h-2.742c-0.424-1.46-1.095-2.729-1.972-3.826l0.017 0.022c2.090 0.513 3.78 1.91 4.679 3.763l0.018 0.042zM27 24c-0.005 0.711-0.083 1.401-0.229 2.066l0.012-0.066h-5.566c-0.138-0.601-0.217-1.291-0.217-2s0.079-1.399 0.229-2.062l-0.012 0.062h5.566c0.134 0.599 0.212 1.289 0.217 1.997l0 0.003zM24 31c-0.037 0-0.073-0.005-0.109-0.006-1.077-1.091-1.906-2.43-2.392-3.924l-0.020-0.071h5.043c-0.506 1.564-1.335 2.903-2.413 3.995l0.001-0.001c-0.037 0.001-0.073 0.006-0.109 0.006zM21.479 21c0.506-1.564 1.335-2.903 2.413-3.995l-0.001 0.001c0.037 0 0.073-0.006 0.109-0.006s0.073 0.005 0.109 0.006c1.077 1.091 1.906 2.43 2.392 3.924l0.020 0.071zM22.385 17.196c-0.86 1.075-1.53 2.344-1.936 3.727l-0.019 0.077h-2.742c0.918-1.894 2.607-3.291 4.649-3.794l0.048-0.010zM17.295 22h2.899c-0.123 0.601-0.194 1.292-0.194 2s0.071 1.399 0.205 2.067l-0.011-0.067h-2.899c-0.187-0.599-0.295-1.287-0.295-2s0.108-1.401 0.308-2.049l-0.013 0.049zM17.688 27h2.745c0.424 1.46 1.095 2.729 1.972 3.826l-0.017-0.022c-2.091-0.513-3.782-1.91-4.682-3.763l-0.018-0.042zM25.617 30.804c0.859-1.075 1.529-2.344 1.933-3.727l0.019-0.077h2.742c-0.918 1.894-2.607 3.291-4.649 3.794l-0.048 0.010zM30.705 26h-2.899c0.123-0.601 0.194-1.292 0.194-2s-0.071-1.399-0.205-2.067l0.011 0.067h2.899c0.187 0.599 0.295 1.287 0.295 2s-0.108 1.401-0.308 2.049l0.013-0.049z"
                ></path>
                <path
                    d="M13.688 24.031c0-0.003 0-0.008 0-0.012 0-1.9 0.518-3.679 1.422-5.202l-0.026 0.047-0.014-0.017c-1.485 0.721-3.23 1.142-5.074 1.142s-3.589-0.421-5.145-1.173l0.071 0.031c-2.942 3.398-4.921 9.116-4.921 13.136h17.459c-2.31-1.897-3.771-4.753-3.771-7.951 0-0 0-0.001 0-0.001v0z"
                ></path>
                <path
                    d="M17.99 7.996c0 4.416-3.58 7.996-7.996 7.996s-7.996-3.58-7.996-7.996c0-4.416 3.58-7.996 7.996-7.996s7.996 3.58 7.996 7.996z"
                ></path>
            </svg>
            <svg
                v-else-if="['lesson-plan', 'learning-support'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M30 28h-4v4h-4v-4h-6.412v-0.662l2.258-1.304h10.154v-20.004h-24v5.168c-0.726 0.224-1.4 0.564-2 1.002v-6.2h-2v-6h32v6h-2z"
                ></path>
                <path
                    d="M6 8h20v16h-4.722c1.602-1.196 2.092-3.428 1.068-5.204-1.106-1.912-3.552-2.568-5.464-1.464l-4.532 2.616c0.268-0.73 0.414-1.52 0.414-2.344 0-3.744-3.024-6.78-6.764-6.8z"
                ></path>
                <path
                    d="M8.798 24.226c0.036-0.024 8.77-5.068 8.77-5.068 0.956-0.554 2.18-0.226 2.732 0.732 0.552 0.956 0.224 2.178-0.732 2.732l-8.526 4.922c0.606 1.528 0.958 3.144 0.958 4.456h-12c0-2.412 1.188-5.844 2.954-7.884 0.926 0.434 1.956 0.684 3.046 0.684 0.994 0 1.938-0.208 2.798-0.574zM10.8 17.6c0 2.652-2.148 4.8-4.8 4.8-2.65 0-4.8-2.148-4.8-4.8 0-2.65 2.15-4.8 4.8-4.8 2.652 0 4.8 2.15 4.8 4.8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'link'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M6 20c-1.084 0-2-0.916-2-2v-4c0-1.084 0.916-2 2-2h7.63c-0.831-2.319-3.034-4-5.63-4h-2c-3.3 0-6 2.7-6 6v4c0 3.3 2.7 6 6 6h2c2.596 0 4.798-1.681 5.63-4h-7.63zM26 8h-2c-2.596 0-4.798 1.681-5.63 4h7.63c1.084 0 2 0.916 2 2v4c0 1.084-0.916 2-2 2h-7.63c0.831 2.319 3.034 4 5.63 4h2c3.3 0 6-2.7 6-6v-4c0-3.3-2.7-6-6-6zM24 16c0 1.105-0.895 2-2 2h-12c-1.105 0-2-0.895-2-2v0c0-1.105 0.895-2 2-2h12c1.105 0 2 0.895 2 2v0z"
                ></path>
            </svg>
            <svg
                v-else-if="['list', 'itineraryitem'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 6h4v4h-4v-4zM0 14h4v4h-4v-4zM0 22h4v4h-4v-4zM8 6h24v4h-24v-4zM8 14h24v4h-24v-4zM8 22h24v4h-24v-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'list-view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0h32v4h-32v-4zM0 14h32v4h-32v-4zM0 28h32v4h-32v-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'live-marks'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M12.324 17.039q-0.258 1-1.477 4.945h2.977q-1.148-3.696-1.293-4.179c-0.098-0.323-0.164-0.578-0.207-0.766z"
                ></path>
                <path d="M28.062 8l-8-8v8h8z"></path>
                <path
                    d="M18.062 10v-10h-14v32h24v-22h-10zM15.23 26.734l-0.828-2.719h-4.164l-0.828 2.719h-2.609l4.031-11.469h2.961l4.047 11.469zM20.59 17.195h1.711v3.047h3.024v1.711h-3.024v3.008h-1.711v-3.008h-3.024v-1.711h3.024z"
                ></path>
            </svg>
            <svg
                v-else-if="['location', 'venue'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-6.627 0-12 5.373-12 12l12 20 12-20c0-6.627-5.373-12-12-12zM16 16c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4c0 2.209-1.791 4-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'locked'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26 16h-2v-12c0-2.209-1.791-4-4-4h-8c-2.209 0-4 1.791-4 4v12h-2c-2.209 0-4 1.791-4 4v8c0 2.209 1.791 4 4 4h20c2.209 0 4-1.791 4-4v-8c0-2.209-1.791-4-4-4zM18 28h-4v-8h4v8zM20 16h-8v-12h8v12z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'logout'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 20h-4v-12l-6-4h10v2h4v-6h-20v24l12 8v-8h8v-6h-4zM32 12.001l-8-8v3.999h-8v8h8v4.001z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'lti'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 8l4-4-4-4v8zM14 24l-3.975 4 3.975 4v-8zM18 16h4v2h-4v-2zM28 12h4v18h-4v-18zM0 2h4v16h-4v-16zM10 10h4v6h-4v-6zM14 14v-4h18v4h-18zM0 22v-4h22v4h-22zM4 6v-4h14v4h-14zM14 30v-4h14v4h-14z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'match'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0h4v32h-4v-32z"></path>
                <path d="M32 4s0-4-6-4-6 2-12 2c-4 0-6-2-6-2v16s0 4 6 4 6-2 12-2c4 0 6 2 6 2z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'medical'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16.062-0c-8.837 0-16 7.164-16 16s7.163 16 16 16 16-7.163 16-16-7.163-16-16-16zM24.062 18h-6v6h-4v-6h-6v-4h6v-6h4v6h6z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'menu'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 4h32v4h-32v-4zM0 14h32v4h-32v-4zM0 24h32v4h-32v-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'mic'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 14v2c0 4.411-3.589 8-8 8s-8-3.589-8-8v-2h-2v2c0 4.816 3.45 8.864 8 9.797v4.203h-4v2h12v-2h-4v-4.203c4.55-0.933 8-4.981 8-9.797v-2h-2zM16 20c2.2 0 4-1.8 4-4v-12c0-2.2-1.8-4-4-4s-4 1.8-4 4v12c0 2.2 1.8 4 4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'mic-muted'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M19.384 25.384l-1.601-1.601c-0.575 0.132-1.168 0.217-1.783 0.217-4.411 0-8-3.589-8-8v-2h-2v2c0 4.816 3.45 8.864 8 9.797v4.203h-4v2h12v-2h-4v-4.203c0.477-0.098 0.934-0.249 1.384-0.412zM23.963 21.963c1.266-1.665 2.037-3.719 2.037-5.963v-2h-2v2c0 1.696-0.533 3.268-1.439 4.561l-2.949-2.948c0.226-0.498 0.388-1.031 0.388-1.612v-12c0-2.2-1.8-4-4-4s-4 1.8-4 4v6l-8-8-2 2 26 26 2-2-6.037-6.037z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'mic-recording'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M12 16c0 2.2 1.8 4 4 4s4-1.8 4-4v-6c0-2.2-1.8-4-4-4s-4 1.8-4 4v6zM18 2c0 1.105-0.895 2-2 2s-2-0.895-2-2c0-1.105 0.895-2 2-2s2 0.895 2 2zM12 4c0 1.105-0.895 2-2 2s-2-0.895-2-2c0-1.105 0.895-2 2-2s2 0.895 2 2zM24 4c0 1.105-0.895 2-2 2s-2-0.895-2-2c0-1.105 0.895-2 2-2s2 0.895 2 2zM24 14v2c0 4.411-3.589 8-8 8s-8-3.589-8-8v-2h-2v2c0 4.816 3.45 8.864 8 9.797v4.203h-4v2h12v-2h-4v-4.203c4.55-0.933 8-4.981 8-9.797v-2h-2z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'microsoft-teams'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 28.402l18 3.596v-31.998l-18 3.236zM5.034 10.233l8.628-0.525v2.157l-3.236 0.147v9.436l-2.157-0.125v-9.229l-3.236 0.13zM21.587 11.124c0.005 0 0.012 0 0.018 0 0.395 0 0.772-0.082 1.112-0.231l-0.018 0.007c1.038-0.447 1.751-1.461 1.751-2.641 0-1.182-0.715-2.196-1.735-2.635l-0.019-0.007c-0.323-0.142-0.699-0.225-1.095-0.225-0.007 0-0.013 0-0.020 0h0.001c-0.005-0-0.012-0-0.018-0-0.393 0-0.767 0.083-1.105 0.232l0.018-0.007c-0.188 0.079-0.309 0.181-0.477 0.292v4.699c0.168 0.112 0.291 0.213 0.477 0.293 0.32 0.142 0.693 0.224 1.085 0.224 0.009 0 0.017-0 0.026-0h-0.001zM27 14.741v8.628h0.688c0.522-0.002 1.030-0.053 1.523-0.148l-0.051 0.008c0.523-0.094 0.991-0.264 1.418-0.499l-0.024 0.012c0.417-0.23 0.764-0.538 1.032-0.907l0.006-0.009c0.256-0.373 0.409-0.834 0.409-1.33 0-0.036-0.001-0.072-0.002-0.107l0 0.005v-5.653zM27.088 12.083c0.518 0.517 1.233 0.837 2.023 0.837 1.581 0 2.863-1.282 2.863-2.863s-1.282-2.863-2.863-2.863c-1.581 0-2.863 1.282-2.863 2.863 0 0.402 0.083 0.785 0.233 1.132l-0.007-0.019c0.153 0.354 0.361 0.657 0.615 0.913l-0-0zM20 25.707c0.14 0.044 0.316 0.084 0.494 0.114l0.025 0.003c0.252 0.038 0.545 0.061 0.842 0.063h0.002c0.461-0.003 0.908-0.054 1.339-0.148l-0.042 0.008c0.464-0.095 0.876-0.262 1.246-0.489l-0.017 0.009c0.374-0.233 0.681-0.538 0.91-0.898l0.007-0.012c0.228-0.38 0.363-0.84 0.363-1.33 0-0.035-0.001-0.070-0.002-0.104l0 0.005v-9.985h-5.168z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'mobile'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M6 0v32h20v-32h-20zM18 30h-4v-4h4v4zM24 24h-16v-20h16v20z"></path>
            </svg>
            <svg
                v-else-if="['modify-pen', 'modify'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 24v8h8zM12 28l-8-8 20-20 8 8-20 20z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'month-view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 0h8v8h-8v-8zM12 0h8v8h-8v-8zM24 0h8v8h-8v-8zM0 12h8v8h-8v-8zM12 12h8v8h-8v-8zM24 12h8v8h-8v-8zM0 24h8v8h-8v-8zM12 24h8v8h-8v-8zM24 24h8v8h-8v-8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'more'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M12 0h8v8h-8v-8zM12 12h8v8h-8v-8zM12 24h8v8h-8v-8z"></path>
            </svg>
            <svg
                v-else-if="['mostly-cloudy', 'weather'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18.666 12.667c3.482 0 6.692 1.349 9.172 3.534l4.162-2.992-6.197-2.176 2.413-6.109-6.39 1.521-1.271-6.444-4.554 4.734-4.554-4.734-1.273 6.444-6.389-1.521 2.413 6.109-6.197 2.176 5.334 3.834-4.037 5.181 1.063 0.056c1.056-0.471 2.188-0.804 3.389-0.907 2.113-5.147 7.085-8.706 12.89-8.706 0.010 0 0.020 0 0.031 0h-0.002z"
                ></path>
                <path
                    d="M28.524 25.253c-0.692-4.847-4.819-8.586-9.858-8.586-5.192 0-9.411 3.973-9.902 9.036-0.624-0.225-1.343-0.36-2.093-0.369l-0.004-0c-3.682 0-6.666 2.985-6.666 6.667v0h32c0-2.784-1.375-5.233-3.476-6.747z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'mute'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M22 18v-18l-9 9-9-9-2 2 26 26 2-2zM6 22h6l10 10v-6l-16-16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'network'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M28 8v-4h-16v-4h-12v24l4-16zM18 28h10l4-16h-24l-4 16h10v2h-14v2h32v-2h-14z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'news'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26 10v-6h-26v24h26l6-18h-6zM10 24h-6v-8h6v8zM20 24h-6v-2h6v2zM22 18h-8v-2h8v2zM22 12h-18v-4h18v4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'next'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M26 16l-16-16-4 4 12 12-12 12 4 4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'notifications'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 2c0 1.105-0.895 2-2 2s-2-0.895-2-2c0-1.105 0.895-2 2-2s2 0.895 2 2zM16 32c2.209 0 4-1.791 4-4h-8c0 2.209 1.791 4 4 4zM26 20v-6c0-5.523-4.477-10-10-10s-10 4.477-10 10v6c0 2.209-1.791 4-4 4v2h28v-2c-2.209 0-4-1.791-4-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'notifications-hollow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 0c-1.105 0-2 0.895-2 2s0.895 2 2 2c1.105 0 2-0.895 2-2v0c0-1.105-0.895-2-2-2v0z"></path>
                <path d="M20 28h-8c0 2.209 1.791 4 4 4s4-1.791 4-4v0z"></path>
                <path
                    d="M16 6.4c4.195 0.005 7.595 3.405 7.599 7.6v6c0 0.002 0 0.005 0 0.007 0 1.342 0.416 2.588 1.126 3.614l-0.014-0.021h-17.422c0.696-1.005 1.111-2.251 1.111-3.593 0-0.002 0-0.005 0-0.007v0-6c0.005-4.195 3.404-7.595 7.599-7.6h0zM16 4c-5.523 0-10 4.477-10 10v0 6c0 2.209-1.791 4-4 4v0 2h28v-2c-2.209 0-4-1.791-4-4v0-6c0-5.523-4.477-10-10-10v0z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'office-365'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M19.835 0l-17.213 6.645v19.195l0.009 0.003 6.159-2.57v-15.121l11.153-2.573v22.52l-17.291-2.248 17.185 6.15 9.543-3.080v-25.707z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'open'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M26 0h-20v24l12 8v-8h8v-24zM22 20h-4v-12l-6-4h10v16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'pause'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.163-16 16s7.164 16 16 16 16-7.164 16-16c0-8.837-7.164-16-16-16zM14 24h-4v-16h4v16zM22 24h-4v-16h4v16z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'phone'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M22 8h-4v4h-4v-4h-4v6l-6 6v12h24v-12l-6-6v-6zM16 24c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4-1.791 4-4 4zM16 0c-5.835 0-11.29 1.587-16 4.316v7.684h6v-6.127c3.107-1.199 6.474-1.873 10-1.873s6.893 0.674 10 1.873v6.127h6v-7.684c-4.71-2.729-10.165-4.316-16-4.316z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'pin'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M30 10h-12l-6-6v-4l-12 12h4l6 6v12l20-20zM20.415 22.414l2-2 9.586 9.586-2 2-9.586-9.586z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'pin-hollow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M30 10h-12l-6-6v-4l-12 12h4l6 6v12zM5.394 10l4.606-4.606 7.006 7.006h7.199l-11.804 11.806v-7.201zM20.415 22.414l2-2 9.586 9.586-2 2-9.586-9.586z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'pinterest'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.163-16 16 0 6.552 3.94 12.18 9.577 14.654-0.045-1.117-0.008-2.458 0.278-3.674 0.308-1.299 2.059-8.718 2.059-8.718s-0.511-1.022-0.511-2.532c0-2.371 1.374-4.142 3.086-4.142 1.455 0 2.158 1.093 2.158 2.402 0 1.463-0.933 3.651-1.413 5.678-0.401 1.697 0.851 3.082 2.525 3.082 3.031 0 5.073-3.893 5.073-8.507 0-3.507-2.362-6.131-6.658-6.131-4.853 0-7.877 3.619-7.877 7.662 0 1.394 0.411 2.377 1.055 3.138 0.296 0.35 0.337 0.49 0.23 0.892-0.077 0.294-0.253 1.003-0.326 1.284-0.107 0.405-0.435 0.55-0.801 0.4-2.235-0.913-3.277-3.361-3.277-6.113 0-4.545 3.833-9.995 11.435-9.995 6.109 0 10.129 4.42 10.129 9.165 0 6.276-3.489 10.966-8.633 10.966-1.727 0-3.352-0.934-3.909-1.994 0 0-0.929 3.686-1.126 4.398-0.339 1.234-1.003 2.466-1.61 3.428 1.439 0.425 2.959 0.656 4.534 0.656 8.835 0 15.999-7.163 15.999-16s-7.164-16-15.999-16z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'podcast'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 2c8.468 0 15.333 6.865 15.333 15.333 0 2.583-0.641 5.014-1.769 7.149 1.541-2.46 2.436-5.366 2.436-8.483 0-8.837-7.163-16-16-16s-16 7.163-16 16c0 3.117 0.894 6.023 2.436 8.483-1.128-2.135-1.769-4.567-1.769-7.149 0-8.468 6.865-15.333 15.333-15.333zM16 6c6.075 0 11 4.925 11 11 0 1.802-0.442 3.498-1.21 4.999 1.183-1.825 1.877-3.996 1.877-6.332 0-6.443-5.223-11.667-11.667-11.667s-11.667 5.223-11.667 11.667c0 2.336 0.694 4.507 1.877 6.332-0.768-1.501-1.21-3.197-1.21-4.999 0-6.075 4.925-11 11-11zM16 10c3.682 0 6.667 2.985 6.667 6.667 0 1.019-0.236 1.982-0.645 2.845 0.825-1.186 1.311-2.625 1.311-4.179 0-4.050-3.283-7.333-7.333-7.333s-7.333 3.283-7.333 7.333c0 1.554 0.487 2.993 1.311 4.179-0.409-0.864-0.645-1.826-0.645-2.846 0-3.682 2.985-6.667 6.667-6.667zM20 16c0 2.209-1.791 4-4 4s-4-1.791-4-4c0-2.209 1.791-4 4-4s4 1.791 4 4zM20 26c0-2.945-1.054-4-4-4s-4 1.055-4 4l2 6h4l2-6z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'poll'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M24 0h8v32h-8v-32zM12 10h8v22h-8v-22zM0 20h8v12h-8v-12z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'position'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M22 6c0 3.314-2.686 6-6 6s-6-2.686-6-6c0-3.314 2.686-6 6-6s6 2.686 6 6z"></path>
                <path
                    d="M23.069 20.54c-0.631-2.040-1.55-4.047-2.67-5.602-1.333 0.659-2.813 1.063-4.399 1.063s-3.066-0.404-4.399-1.063c-1.12 1.556-2.038 3.563-2.67 5.604-4.303 0.664-8.931 2.146-8.931 5.459 0 5.832 14.363 6 16 6s16-0.168 16-6c0-3.313-4.629-4.794-8.931-5.46zM16 30c-7.732 0-14-1.791-14-4 0-1.407 2.548-2.642 6.393-3.355-0.246 1.188-0.393 2.335-0.393 3.355h16c0-1.020-0.147-2.17-0.393-3.355 3.844 0.713 6.393 1.948 6.393 3.355 0 2.209-6.269 4-14 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'previous'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M6 16l16-16 4 4-12 12 12 12-4 4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'print'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M8 0h16v8h-16v-8zM32 10h-32v16h4v6h24v-6h4v-16zM24 28h-16v-6h16v6zM28 18h-4v-4h4v4z"></path>
            </svg>
            <svg
                v-else-if="['progress', 'result', 'report'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 6l6 6-6 6-6-6-10 10 4 4 6-6 6 6 10-10 6 6v-16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'project'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 10v-4.006h-14v-5.994h-2v32h2v-22h14zM8.001 14h15.999v4h-15.999v-4zM30 0v21.976h-14v4.024h14v6h2v-32h-2z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'quiz'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M22 26l-8-8 4-4 4 4 10-10v-8h-32v32h32v-16l-10 10zM4 6h14v2h-14v-2zM4 12h10.343l-2 2h-8.343v-2zM4 18h4.343l2 2h-6.343v-2zM4 26v-2h10.343l2 2h-12.343z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'rain'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M28 12c0-3.314-2.686-6-6-6v0c-0.094 0-0.182 0.024-0.274 0.027-0.883-3.459-3.992-6.027-7.726-6.027-4.418 0-8 3.582-8 8v0c-3.314 0-6 2.686-6 6s2.686 6 6 6v0h22c2.209 0 4-1.791 4-4s-1.791-4-4-4v0z"
                ></path>
                <path d="M26 22s-2 6-4 6-2-4 4-6z"></path>
                <path d="M16 26s-2 6-4 6-2-4 4-6z"></path>
                <path d="M10 22s-2 6-4 6-2-4 4-6z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'random-image'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 2v-2h-24v24h2v-22zM28 6v-2h-24v24h2v-22zM8 8v24h24v-24h-24zM20.966 28.458c-0.364 0.362-0.874 0.542-1.526 0.542-0.668 0-1.183-0.177-1.55-0.53-0.364-0.353-0.548-0.852-0.548-1.496 0-0.668 0.179-1.172 0.537-1.514s0.879-0.513 1.561-0.513c0.661 0 1.17 0.175 1.533 0.524 0.361 0.35 0.541 0.851 0.541 1.502 0 0.627-0.182 1.122-0.548 1.484zM24.899 17.592c-0.397 0.636-1.151 1.351-2.265 2.146-0.763 0.565-1.245 0.994-1.448 1.288s-0.305 0.679-0.305 1.156v0.715h-3.159v-0.882c0-0.763 0.164-1.426 0.489-1.991 0.327-0.565 0.923-1.164 1.788-1.8 0.834-0.596 1.384-1.080 1.652-1.454 0.266-0.374 0.398-0.791 0.398-1.252 0-0.517-0.191-0.91-0.572-1.18s-0.913-0.405-1.597-0.405c-1.192 0-2.551 0.389-4.077 1.168l-1.299-2.611c1.773-0.994 3.653-1.49 5.639-1.49 1.638 0 2.939 0.393 3.905 1.18s1.448 1.836 1.448 3.147c0 0.874-0.198 1.629-0.596 2.265z"
                ></path>
            </svg>
            <svg
                v-else-if="['reach', 'reach-logo'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M15.997 0.003c-8.817 0-15.997 7.176-15.997 15.997s7.181 15.997 15.997 15.997 16.003-7.176 16.003-15.997-7.181-15.997-16.003-15.997zM15.997 1.331c4.652 0 8.806 2.172 11.488 5.561-0.043 0.016-0.085 0.048-0.122 0.085-13.273 14.085-20.681 0.887-22.997 0.106 2.676-3.495 6.894-5.752 11.631-5.752zM1.328 16c0-2.847 0.818-5.508 2.231-7.76 9.241 9.512 11.207 19.699 11.579 22.402-7.691-0.446-13.809-6.841-13.809-14.643zM16.741 30.648c2.9-11.642 11.807-22.185 11.807-22.185l0.021-0.016c1.338 2.21 2.103 4.796 2.103 7.552 0 7.839-6.182 14.261-13.931 14.648z"
                ></path>
                <path
                    d="M19.338 9.117c0 1.792-1.453 3.245-3.245 3.245s-3.245-1.453-3.245-3.245c0-1.792 1.453-3.245 3.245-3.245s3.245 1.453 3.245 3.245z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'refresh'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M27.3 12h4.7v-12l-4.704 4.704c-2.89-2.9-6.876-4.704-11.296-4.704-8.836 0-16 7.163-16 16s7.164 16 16 16c8.158 0 14.876-6.11 15.862-14h-4.043c-0.957 5.666-5.885 10-11.819 10-6.617 0-12-5.383-12-12s5.383-12 12-12c3.31 0 6.298 1.356 8.468 3.532l-4.468 4.468h7.3z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'reply'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 14l-14-14v8c0 0-18 4-18 24h2c0 0 0-12 16-12v8l14-14z"></path>
            </svg>
            <svg
                v-else-if="['resource-booking', 'camera'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26 6v4h-2v-8h-16v8h-8v20h32v-24h-6zM10 26c-3.314 0-6-2.686-6-6s2.686-6 6-6 6 2.686 6 6-2.686 6-6 6zM20 10h-8v-4h8v4zM28 18h-6v-4h6v4zM13 20c0 1.657-1.343 3-3 3s-3-1.343-3-3c0-1.657 1.343-3 3-3s3 1.343 3 3z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'rss'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M8 28c0 2.209-1.791 4-4 4s-4-1.791-4-4c0-2.209 1.791-4 4-4s4 1.791 4 4zM16 32h4c0-11.046-8.954-20-20-20v4c8.822 0 16 7.178 16 16zM28 32h4c0-17.673-14.327-32-32-32v4c15.439 0 28 12.561 28 28z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'schoolbox'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M15.334 10.887c2.676-0.876 3.667-0.939 4.985-0.939l0.68 0.002h4.609v-1.95c0-0.786-0.643-1.43-1.429-1.43h-16.001c-0.786 0-1.43 0.644-1.43 1.43v5.111c0.188 0.185 0.331 0.42 0.435 0.642 1.544-0.561 5.729-2.071 8.152-2.865zM25.507 11.711c-0.304 0-1.232 0-2.188 0.257-2.19 0.585-2.881 1.355-3.491 2.033-0.536 0.599-1.043 1.165-2.305 1.462-0.26 0.060-0.515 0.092-0.755 0.092-1.265 0-1.906-0.817-2.121-1.167l-0.062-0.101-0.456-0.27h0.903l0.060 0.097c0.17 0.276 0.674 0.918 1.676 0.918 0.201 0 0.414-0.027 0.635-0.078 1.101-0.259 1.535-0.743 2.036-1.3 0.657-0.733 1.403-1.565 3.744-2.191 1.024-0.274 2.002-0.274 2.323-0.274h6.166c0 0-0.101-0.613-4.008-0.613-3.645 0-5.001 0-6.667 0-1.698 0-2.448-0.082-5.469 0.908s-8.725 3.073-8.725 3.073-0.183-0.781-0.574-1.068c-0.39-0.287-0.625-0.235-0.313 0.182s-0.155 1.277-1.093 1.771c-0.938 0.494-1.181 0.465-1.572 0.804 0 0-0.602 0.152-1.725 0.096-1.125-0.056-1.421-0.104-1.517-0.040s0.586 0.506 1.605 0.659c0.727 0.108 1.121 0.138 1.346 0.228 1.441 0.484 2.083 2.233 6.542 2.233 0.716 0 1.412-0.044 2.094-0.129-0.272 0.419-0.624 0.973-0.984 1.563-0.071 0.001-0.143 0.003-0.214 0.003-1.472 0-2.675-0.262-3.651-0.641v3.783c0 0.787 0.643 1.43 1.43 1.43h16.001c0.786 0 1.429-0.644 1.429-1.43v-8.279c-1.768 0.787-3.973 1.767-5.352 2.369 0.23 0.603 0.596 1.654 0.718 2.508 0.183 1.276 0.051 2.63-0.469 1.562-0.448-0.918-1.799-2.758-2.169-3.257-1.493 0.611-3.639 1.428-5.868 1.78-0.447 0.544-0.916 1.095-1.353 1.571-1.535 1.673-1.841 1.443-1.841 1.443 0.038-0.038 0.078-0.080 0.12-0.124 0.014-0.015 0.030-0.032 0.045-0.049 0.028-0.030 0.056-0.060 0.086-0.092 0.019-0.021 0.039-0.043 0.059-0.065 0.027-0.030 0.054-0.060 0.083-0.091 0.022-0.025 0.045-0.050 0.067-0.075 0.027-0.031 0.055-0.062 0.083-0.094 0.025-0.028 0.050-0.056 0.075-0.085 0.027-0.031 0.054-0.063 0.082-0.095s0.055-0.064 0.083-0.096c0.025-0.029 0.050-0.059 0.075-0.088 0.031-0.037 0.063-0.074 0.094-0.111 0.020-0.024 0.041-0.049 0.062-0.074 0.192-0.229 0.391-0.472 0.59-0.719 0.008-0.009 0.015-0.019 0.023-0.029 0.047-0.058 0.093-0.116 0.139-0.175 0.011-0.014 0.022-0.028 0.033-0.042 0.045-0.058 0.091-0.115 0.136-0.172 0.011-0.014 0.021-0.027 0.032-0.041 0.046-0.058 0.091-0.117 0.136-0.175 0.009-0.012 0.018-0.023 0.027-0.035 0.047-0.061 0.093-0.121 0.138-0.181 0.004-0.005 0.008-0.010 0.012-0.016 0.154-0.203 0.301-0.4 0.435-0.586 0.045-0.062 0.090-0.124 0.134-0.185 0.022-0.030 0.044-0.061 0.066-0.091s0.044-0.061 0.066-0.091c0.028-0.038 0.056-0.076 0.084-0.114 0.016-0.021 0.032-0.043 0.047-0.064 0.033-0.044 0.065-0.088 0.098-0.132 0.010-0.013 0.020-0.027 0.030-0.040 0.036-0.049 0.072-0.097 0.108-0.145 0.005-0.007 0.011-0.015 0.016-0.022 0.122-0.163 0.239-0.317 0.346-0.458 0.031-0.041 0.061-0.079 0.090-0.118 0.008-0.011 0.017-0.023 0.025-0.033 0.027-0.035 0.052-0.068 0.078-0.101 0.006-0.008 0.012-0.015 0.017-0.023 0 0 0-0 0-0 0.032-0.042 0.063-0.082 0.092-0.12 0-0 0-0.001 0-0.001 0.006-0.007 0.012-0.015 0.017-0.022 0.011-0.014 0.021-0.027 0.031-0.040 0.015-0.019 0.030-0.038 0.043-0.056 0.019-0.025 0.037-0.048 0.054-0.070 0.005-0.006 0.010-0.013 0.015-0.019 0.014-0.018 0.026-0.033 0.038-0.049 0.010-0.012 0.020-0.026 0.029-0.037 0.001-0.001 0.001-0.002 0.001-0.002 0.035-0.045 0.061-0.078 0.078-0.099l0.991-0.272c0 0-0.244 0.328-0.627 0.825 2.81-0.806 5.389-2.415 7.968-4.845 1.654-1.559 4.084-1.559 5.099-1.559 0.807 0 3.608 0 4.679 0 0.063-0.075 0.119-0.149 0.16-0.223 0.108-0.188 0.144-0.344 0.141-0.475h-6.493zM16.874 12.275c0.667-0.247 1.359-0.042 1.543 0.458 0.176 0.474-0.166 1.042-0.77 1.311 0.314-0.202 0.48-0.512 0.384-0.771-0.118-0.321-0.595-0.44-1.066-0.265-0.472 0.174-0.757 0.575-0.638 0.898 0.026 0.071 0.073 0.131 0.131 0.181-0.215-0.095-0.38-0.251-0.457-0.46-0.185-0.5 0.207-1.105 0.873-1.352z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'screen-capture'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M6 22v-4h-2v6h8v-2h-6z"></path>
                <path d="M4 8v6h2v-4h6v-2h-8z"></path>
                <path d="M26 8h-6v2h6v4h2v-6h-2z"></path>
                <path d="M26 22h-6v2h8v-6h-2v4z"></path>
                <path d="M0 4h32v2h-32v-2z"></path>
                <path d="M30 6.001v19.999h-28v-19.999h-2v21.999h32v-21.999h-2z"></path>
                <path d="M20 16c0 2.209-1.791 4-4 4s-4-1.791-4-4c0-2.209 1.791-4 4-4s4 1.791 4 4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'search'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 20h-3.074c1.905-2.124 3.074-4.922 3.074-8 0-6.627-5.372-12-11.999-12s-12.001 5.373-12.001 12c0 6.627 5.373 12 12.001 12 3.078 0 5.876-1.169 7.999-3.073v3.073l8 8 4-4-8-8zM12.001 20c-4.411 0-8.001-3.589-8.001-8s3.589-8 8.001-8c4.411 0 7.999 3.589 7.999 8s-3.589 8-8 8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'settings'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 18v-4l-4.42-1.105c-0.274-1.024-0.676-1.993-1.195-2.889l2.343-3.905-2.829-2.828-3.905 2.343c-0.897-0.519-1.866-0.921-2.889-1.195l-1.105-4.42h-4l-1.105 4.42c-1.024 0.274-1.993 0.676-2.889 1.195l-3.905-2.343-2.829 2.828 2.343 3.905c-0.519 0.897-0.921 1.866-1.195 2.889l-4.42 1.105v4l4.42 1.105c0.274 1.024 0.676 1.993 1.195 2.889l-2.343 3.905 2.829 2.829 3.905-2.343c0.897 0.519 1.866 0.921 2.89 1.195l1.105 4.42h4l1.105-4.42c1.024-0.274 1.993-0.676 2.89-1.195l3.905 2.343 2.829-2.829-2.343-3.905c0.519-0.896 0.921-1.866 1.195-2.889l4.42-1.105zM8 16c0-4.411 3.589-8 8-8s8 3.589 8 8c0 4.411-3.589 8-8 8s-8-3.589-8-8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'share'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26 20c-1.617 0.001-3.083 0.645-4.158 1.689l0.001-0.001-9.917-4.961c0.036-0.21 0.063-0.461 0.073-0.715l0-0.011c-0.011-0.266-0.037-0.516-0.078-0.762l0.005 0.035 9.918-4.961c1.074 1.031 2.535 1.666 4.145 1.666 3.308 0 5.99-2.682 5.99-5.99s-2.682-5.99-5.99-5.99c-3.308 0-5.99 2.682-5.99 5.99 0 0.004 0 0.008 0 0.012v-0.001c0.011 0.266 0.037 0.516 0.078 0.762l-0.005-0.035-9.917 4.961c-1.078-1.043-2.549-1.686-4.17-1.686-3.314 0-6 2.686-6 6s2.686 6 6 6c1.621 0 3.092-0.643 4.171-1.687l-0.002 0.002 9.918 4.959c-0.037 0.21-0.063 0.459-0.074 0.713l-0 0.012c0 3.314 2.686 6 6 6s6-2.686 6-6c0-3.314-2.686-6-6-6v0z"
                ></path>
            </svg>
            <svg
                v-else-if="['showcase', 'achievement', 'trophy'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 0v4h-16v-4h-8v4h1c0 2.457 0.682 14.649 13 21.995v4.005h-2l-2 2h12l-2-2h-2v-4.005c12.318-7.346 13-19.538 13-21.995h1v-4h-8zM3 4h3v4h2c0.299 5.105 1.11 9.625 2.45 12.861-7.009-6.634-7.45-14.95-7.45-16.86zM21.55 20.86c1.341-3.235 2.152-7.756 2.45-12.861h2v-4h3c0 1.911-0.441 10.226-7.45 16.86z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'shuffle'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M6 22h-6v4h6c2.196-0.005 4.224-0.723 5.864-1.936l-0.027 0.019c-0.807-1.008-1.48-2.17-1.962-3.427l-0.030-0.089c-1.045 0.881-2.375 1.433-3.845 1.433z"
                ></path>
                <path
                    d="M32 8l-6-6v4h-4c-2.196 0.005-4.224 0.723-5.864 1.936l0.027-0.019c0.807 1.007 1.48 2.17 1.962 3.426l0.030 0.089c1.045-0.88 2.375-1.432 3.845-1.432h4v4z"
                ></path>
                <path
                    d="M32 24l-6-6v4h-4c-3.312-0.004-5.996-2.688-6-6v-0c0-5.514-4.486-10-10-10h-6v4h6c3.312 0.004 5.996 2.688 6 6v0c0 5.514 4.486 10 10 10h4v4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'social-stream'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0v8h6v6l6-6h20v-8zM0 26h6v6l6-6h20v-8h-32z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'sort-up'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 24h32l-16-16z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'sortable'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 12h32L16 0z" />
                <path d="M32 18.25H0l16 12z" />
            </svg>
            <svg
                v-else-if="props.name === 'sport'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M2.461 24.499c2.184-2.174 3.539-5.181 3.539-8.499s-1.354-6.325-3.539-8.499c-1.551 2.465-2.461 5.373-2.461 8.499s0.911 6.035 2.461 8.499zM5 4.401c3.073 2.916 5 7.028 5 11.599s-1.927 8.683-5 11.599c2.412 2.288 5.53 3.829 9 4.263v-31.724c-3.47 0.434-6.588 1.975-9 4.263zM22 16c0-4.571 1.927-8.683 5-11.599-2.412-2.288-5.53-3.829-9-4.263v31.724c3.47-0.434 6.588-1.975 9-4.263-3.073-2.916-5-7.028-5-11.599zM26 16c0 3.318 1.355 6.325 3.539 8.499 1.551-2.465 2.461-5.373 2.461-8.499s-0.911-6.035-2.461-8.499c-2.184 2.174-3.539 5.181-3.539 8.499z"
                ></path>
            </svg>
            <svg
                v-else-if="['staff', 'pastoral'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M2.004 25.155c-1.227 2.094-2.004 4.816-2.004 6.845h12c0-2.030-0.777-4.751-2.004-6.845-1.226 0.537-2.574 0.845-3.996 0.845s-2.77-0.308-3.996-0.845zM12 16c0 3.314-2.687 6-6 6s-6-2.686-6-6 2.686-6 6-6c3.313 0 6 2.686 6 6zM27.077 18.858c-1.544 0.725-3.261 1.142-5.077 1.142s-3.532-0.417-5.077-1.142c-1.206 1.394-2.238 3.183-3.051 5.080 1.403 2.724 2.127 5.832 2.127 8.063h16c0-4.021-1.98-9.742-4.923-13.142zM30 8c0 4.418-3.582 8-8 8s-8-3.582-8-8 3.581-8 8-8c4.418 0 8 3.582 8 8z"
                ></path>
            </svg>
            <svg
                v-else-if="['staff-students', 'player', 'user'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 20c-1.816 0-3.532-0.417-5.077-1.142-2.943 3.4-4.923 9.121-4.923 13.142h20c0-4.021-1.98-9.742-4.923-13.142-1.544 0.725-3.261 1.142-5.077 1.142zM24 8c0 4.418-3.582 8-8 8s-8-3.582-8-8 3.582-8 8-8c4.418 0 8 3.582 8 8z"
                ></path>
            </svg>
            <svg
                v-else-if="['sun', 'sunny', 'clear'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M26.668 17.204l5.332-3.835-6.201-2.173 2.412-6.108-6.387 1.52-1.273-6.443-4.553 4.733-4.553-4.733-1.272 6.443-6.389-1.52 2.413 6.108-6.196 2.176 5.333 3.833-4.037 5.181h0.008l-0.002 0.003 6.56 0.341-0.593 6.541 5.7-3.259 3.037 5.824 3.035-5.824 5.703 3.256-0.596-6.54 6.558-0.343z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'surveys'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M18 20l-6-6 14-14 6 6-14 14zM24 28h-20v-22h6v2h2.343l6-6h-0.343v-2h-8v2h-10v30h28v-16.343l-4 4zM8 18v6h6z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'synergetic'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16.893 17.79c-0.215 0.107-5.369 2.654-10.67 2.654-0.69 0-1.355-0.081-1.995-0.217l11.773 11.773 14.522-14.522c-1.247-0.979-2.912-1.922-4.745-1.922-4.338 0-8.84 2.212-8.885 2.234zM15.107 14.21c0.215-0.107 5.369-2.654 10.67-2.654 0.69 0 1.355 0.081 1.995 0.217l-11.773-11.773-14.522 14.523c1.247 0.979 2.911 1.922 4.745 1.922 4.338 0 8.84-2.212 8.885-2.234z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'task'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M20 2v-2h-8v2h-10v30h28v-30h-10zM16 26l10-10v12h-20v-22h6v2h8v-2h6v2l-10 10-4-4-4 4 8 8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'teacher'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 29l-8-6v5.001c0 2.209 3.582 4 8 4s8-1.791 8-4v-4.999l-8 5.999zM32 12.002l-15.949-12.002-16.051 11.998 16 12.002 10-7.499v5.499l2 2 2-2v-8.498z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'textbox'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0v32h32v-32h-32zM24 12h-2l-2-2h-2v12h2v2h-8v-2h2v-12h-2l-2 2h-2v-4h16v4z"></path>
            </svg>
            <svg
                v-else-if="['thunder', 'storm'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M28 12c0-3.314-2.686-6-6-6v0c-0.094 0-0.182 0.024-0.274 0.027-0.883-3.459-3.992-6.027-7.726-6.027-4.418 0-8 3.582-8 8v0c-3.314 0-6 2.686-6 6s2.686 6 6 6v0h22c2.209 0 4-1.791 4-4s-1.791-4-4-4v0z"
                ></path>
                <path d="M20 22h-8l-2 6h4l-2 4 10-6h-6l4-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'tiles'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M0 0h8v8h-8v-8zM12 0h8v8h-8v-8zM24 0h8v8h-8v-8zM0 12h8v8h-8v-8zM12 12h8v8h-8v-8zM24 12h8v8h-8v-8zM0 24h8v8h-8v-8zM12 24h8v8h-8v-8zM24 24h8v8h-8v-8z"
                ></path>
            </svg>
            <svg
                v-else-if="['time-value', 'hourglass'].includes(props.name)"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 4.343v-0.343c0-2.209-1.791-4-4-4h-24c-2.209 0-4 1.791-4 4v0.343c0 1.061 0.421 2.078 1.172 2.829l8.828 8.828-8.828 8.828c-0.75 0.75-1.172 1.768-1.172 2.828v0.343c0 2.209 1.791 4 4 4h24c2.209 0 4-1.791 4-4v-0.343c0-1.061-0.421-2.078-1.172-2.828l-8.828-8.828 8.828-8.828c0.75-0.75 1.172-1.768 1.172-2.829zM30 4.343c0 0.534-0.208 1.037-0.586 1.414l-10.242 10.243 10.242 10.243c0.378 0.378 0.586 0.88 0.586 1.414v0.343c0 1.103-0.897 2-2 2h-24c-1.103 0-2-0.897-2-2v-0.343c0-0.534 0.208-1.037 0.586-1.414l10.242-10.243-10.242-10.243c-0.378-0.378-0.586-0.88-0.586-1.414v-0.343c0-1.103 0.897-2 2-2h24c1.103 0 2 0.897 2 2v0.343zM14 12v10h4v-10l6-6h-16z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'timetable'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.837 0-16 7.163-16 16s7.163 16 16 16 16-7.163 16-16c0-8.837-7.163-16-16-16zM18 18h-10v-4h6v-8.667h4v12.667z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'twitter'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M32 6.044c-1.177 0.522-2.442 0.875-3.771 1.034 1.356-0.813 2.397-2.099 2.888-3.632-1.269 0.753-2.675 1.299-4.17 1.593-1.197-1.276-2.903-2.073-4.793-2.073-3.625 0-6.565 2.94-6.565 6.565 0 0.515 0.058 1.016 0.17 1.496-5.456-0.274-10.294-2.887-13.532-6.859-0.565 0.969-0.889 2.097-0.889 3.3 0 2.278 1.159 4.287 2.92 5.465-1.076-0.034-2.089-0.33-2.973-0.821-0.001 0.028-0.001 0.055-0.001 0.082 0 3.181 2.263 5.834 5.266 6.437-0.551 0.15-1.131 0.23-1.73 0.23-0.423 0-0.834-0.042-1.235-0.118 0.835 2.608 3.26 4.507 6.133 4.559-2.247 1.76-5.078 2.81-8.154 2.81-0.53 0-1.052-0.031-1.566-0.092 2.905 1.863 6.356 2.95 10.064 2.95 12.076 0 18.68-10.004 18.68-18.68 0-0.285-0.007-0.568-0.020-0.85 1.282-0.925 2.396-2.082 3.276-3.399z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'undo'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 18l14 14v-8c0 0 18-4 18-24h-2c0 0 0 12-16 12v-8l-14 14z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'unit'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M23.717 14c-0.892-3.444-3.994-6-7.717-6s-6.825 2.556-7.717 6h-8.283v4h8.283c0.892 3.444 3.994 6 7.717 6s6.825-2.556 7.717-6h8.283v-4h-8.283zM16 20c-2.206 0-4-1.794-4-4s1.794-4 4-4 4 1.794 4 4c0 2.206-1.794 4-4 4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'unlocked'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M28 0h-8c-2.209 0-4 1.791-4 4v12h-12c-2.209 0-4 1.791-4 4v8c0 2.209 1.791 4 4 4h20c2.209 0 4-1.791 4-4v-8c0-2.209-1.791-4-4-4h-4v-12h8v6h4v-6c0-2.209-1.791-4-4-4zM16 28h-4v-8h4v8z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'up-arrow'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M16 6l-16 16 4 4 12-12 12 12 4-4z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'up-down'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M2 14h28l-14-14z"></path>
                <path d="M30 18h-28l14 14z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'upload'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M12 23.999h8v-12h8l-12-12-12 12h8zM28 28h-24l0 4h24z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'veracross'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M23.3776 9.59985H8.57764V11.6443H23.3776V9.59985Z" fill="currentColor" />
                <path
                    d="M22.3554 13.1111H9.64426C8.04426 13.1111 6.75537 14.4 6.75537 16C6.75537 17.6 8.04426 18.8889 9.64426 18.8889V16.8444C9.15537 16.8444 8.79982 16.4444 8.79982 16C8.79982 15.5555 9.19982 15.1555 9.64426 15.1555H22.3554C22.8443 15.1555 23.1998 15.5555 23.1998 16C23.1998 16.4444 22.7998 16.8444 22.3554 16.8444V18.8889C23.9554 18.8889 25.2443 17.6 25.2443 16C25.2443 14.4 23.9554 13.1111 22.3554 13.1111Z"
                    fill="currentColor"
                />
                <path
                    d="M15.9999 3.46655C9.111 3.46655 3.46655 9.06655 3.46655 15.9999C3.46655 22.9332 9.06655 28.5332 15.9999 28.5332C22.9332 28.5332 28.5332 22.9332 28.5332 15.9999C28.5332 9.06655 22.8888 3.46655 15.9999 3.46655ZM20.9332 25.4666V16.8443H18.8888V26.2666C18.311 26.4443 17.6888 26.5332 17.0666 26.6221V16.8443H15.0221V26.6221C14.3999 26.5777 13.7777 26.4443 13.1999 26.2666V16.8443H11.1554V25.4666C7.73322 23.6888 5.42211 20.0888 5.42211 15.9999C5.42211 10.1332 10.2221 5.33322 16.0888 5.33322C21.9554 5.33322 26.7554 10.1332 26.7554 15.9999C26.7554 21.8666 24.3999 23.6888 21.0221 25.4666H20.9332Z"
                    fill="currentColor"
                />
            </svg>

            <svg
                v-else-if="props.name === 'video'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 6l-8 8v-8h-24v20h24v-8l8 8z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'video-error'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M32 26v-20l-8 8v-8h-16l-6-6-2 2 30 30 2-2-8-8v-4l8 8z"></path>
                <path d="M0 26h20l-20-20v20z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 6c-8.836 0-10.666 7.778-16 10 5.333 2.222 7.164 10 16 10s10.666-7.778 16-10c-5.334-2.222-7.164-10-16-10zM16 24c-4.419 0-8-3.581-8-8s3.581-8 8-8c4.418 0 8 3.581 8 8s-3.582 8-8 8zM16 12c-2.21 0-4 1.79-4 4s1.79 4 4 4c2.209 0 4-1.79 4-4s-1.791-4-4-4z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'week-view'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path d="M0 0h8v32h-8v-32zM12 0h8v32h-8v-32zM24 0h8v32h-8v-32z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'wiki'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0c-8.836 0-16 7.163-16 16s7.164 16 16 16 16-7.164 16-16c0-8.837-7.164-16-16-16zM22.461 23.643h-3.691l-2.070-8.030c-0.078-0.286-0.208-0.877-0.393-1.772-0.184-0.896-0.291-1.497-0.318-1.804-0.042 0.376-0.146 0.981-0.314 1.814s-0.297 1.427-0.387 1.783l-2.060 8.009h-3.68l-3.9-15.286h3.189l1.955 8.344c0.341 1.541 0.589 2.875 0.742 4.005 0.042-0.397 0.137-1.013 0.287-1.845 0.151-0.833 0.291-1.48 0.424-1.94l2.227-8.563h3.063l2.227 8.563c0.097 0.383 0.22 0.969 0.366 1.757s0.257 1.464 0.335 2.028c0.069-0.544 0.181-1.222 0.335-2.034 0.152-0.812 0.293-1.469 0.418-1.971l1.945-8.344h3.189l-3.89 15.286z"
                ></path>
            </svg>
            <svg
                v-else-if="props.name === 'withdraw-achievement'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M24 0v4h-14v4h10v12h-10v3.117c1.181 1.022 2.488 1.972 3.873 2.807l0.127 0.071v4.004h-2l-2 2h12l-2-2h-2v-4.004c12.318-7.346 13-19.539 13-21.996h1v-4zM21.55 20.861c1.341-3.236 2.151-7.756 2.45-12.861h2v-4h3c0 1.911-0.441 10.226-7.45 16.861z"
                ></path>
                <path
                    d="M3 4h3v1.172l2-2v-3.172h-8v4h1c0 0.851 0.095 2.881 0.712 5.459l1.679-1.678c-0.332-1.772-0.391-3.141-0.391-3.781z"
                ></path>
                <path d="M8 10v-4l-8 8 8 8v-4h10v-8h-10z"></path>
            </svg>
            <svg
                v-else-if="props.name === 'wolfram-alpha'"
                class="sbx-icon__svg"
                viewBox="0 0 32 32"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 5.333l4.944-4.55 1.325 6.587 6.675-0.775-2.8 6.108 5.855 3.296-5.855 3.296 2.8 6.108-6.675-0.775-1.325 6.587-4.944-4.55-4.944 4.55-1.325-6.587-6.675 0.775 2.8-6.108-5.855-3.296 5.855-3.296-2.8-6.108 6.675 0.775 1.325-6.587z"
                ></path>
            </svg>
            <template v-else-if="alertInvalidIconName()"></template>
        </slot>
    </span>
</template>

<style scoped>
.sbx-icon {
    align-items: center;
    color: v-bind(color);
    height: v-bind(computedSize);
    vertical-align: middle; /* Ensure the icon is vertically centered when used inline with text */
    display: flex;
    justify-content: center;
    width: v-bind(computedSize);
}

.sbx-icon__svg {
    fill: currentColor;
    height: v-bind(computedSize);
    width: v-bind(computedSize);
}

::slotted(svg) {
    fill: currentColor;
    height: v-bind(computedSize);
    vertical-align: middle; /* Ensure the icon is vertically centered when used inline with text */
    width: v-bind(computedSize);
}
</style>
