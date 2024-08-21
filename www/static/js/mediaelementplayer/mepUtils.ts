/**
 * Return a config object with default Schoolbox options set.
 *
 * Usage:
 *    import { getConfigForVideo } from "@js/mediaelementplayer/mepUtils";
 *    const player = new MediaElementPlayer(el, getConfigForVideo());
 *
 * @param { object } overrideValueByKey - object containing key:value pairs that will override defaults.
 * @returns { object } - configuration object including overridden values.
 */
export function getConfigForVideo(overrideValueByKey?: object) {
    let config = {
        /**
         * If 'stretching' is set to 'auto', the video will not resize as the window resizes.
         * If 'stretching' is set to 'responsive', we cannot enforce our desired aspect ratio, as the
         * aspect ratio of the container will always match the aspect ratio of the video.  This is a shame,
         * because if we used the 'responsive' mode, we wouldn't have to set up a 'resize' event handler,
         * and resizing would be less janky.
         *    To get the resizing and container aspect ratio to work as we desire, we must set 'stretching'
         * to 'none', and create our own 'resize' event handler.
         *
         * See: https://github.com/mediaelement/mediaelement/blob/master/docs/api.md
         */
        stretching: "none",

        /**
         * @param { HTMLElement } media - the <mediaelementwrapper> DOM node (contains the <video> node)
         * @param { HTMLElement } node - the <video> DOM node
         * @param { MediaElementPlayer } instance - the relevant instance of MediaElementPlayer
         *
         * See: https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
         */
        success(media: HTMLElement, node: HTMLElement, instance: MediaElementPlayer) {
            /**
             * somewhere between mediaelement.js 4.2.7 and 7 the media element changed from <mediaelementwrapper> to a <div>
             * which would cause videos to bleed out of their containers again. This code ensures that the media element is
             * always 100% width and height of its container.
             */
            if (media !== null) {
                media.style.width = "100%";
                media.style.height = "100%";
            }

            if (instance.node.nodeName.toLowerCase() === "audio") {
                // The below code to fix aspect ratio need not be run when the instance is an audio player.
                return;
            }

            // This is the container for all mejs nodes (including <mediaelementwrapper>).
            const container = node.closest(".mejs__container") as HTMLElement;

            if (container === null) {
                return;
            }

            // The aspect ratio for the video container is fixed for all videos and all screen sizes.  This is
            // done so that portrait videos do not take up too much vertical space.
            const aspectRatio = 16 / 9;

            if (node.classList.contains("full-width")) {
                // TODO: This does not seem to be necessary anymore.
                // If this is necessary, it will be only for videos in CKEditor content saved prior to 23.0.1.
                node.style.width = "100%";
                container.style.height = `${Math.round(container.getBoundingClientRect().width / aspectRatio)}px`;
            }

            // Ensure the video poster fills the container with the correct aspect ratio, and has black bars
            // added on the top/bottom or sides if necessary instead of having a hidden part overflow the
            // bounds of the container.
            const poster = container.querySelector(".mejs__poster");
            if (poster !== null) {
                // TODO: This does not seem to be necessary anymore.
                // If this is necessary, it will be only for videos that do not have a letterboxed poster image.
                // Letterboxed poster images were added in SA-15531.
                // Another reason this line might still be useful is if we ever want to switch to another aspect ratio.
                // Test the effect of the below line by embedding a variety of videos, and then setting aspectRatio
                // to something overly wide or high (like 24:9, or 9:24) for example.  Then compare the video posters
                // with or without this line.
                poster.removeAttribute("style");
            }

            const replacementContainer: HTMLElement | null = container.closest(
                "[data-schoolboxmedia-replacement-container]",
            );

            /**
             * Ensure the video container adjusts in size correctly as the window size changes (both when it
             * is playing and not playing).
             * If this function is removed (with the MediaElementPlayer 'stretching' option remaining as 'none'):
             *  - downsizing the window from a large size will cause a horizontal scrollbar to appear on the
             *    video container
             *  - when upsizing the window from a small size, the video will remain small as the surrounding
             *    space grows
             *  - The video content size will not change as the window grows/shrinks.
             */
            function fixVideoPlayerDimensions() {
                if (replacementContainer !== null) {
                    // Set display to 'block' temporarily, so we can calculate dimensions.  This allows the container to
                    // grow if it is able to, when we set container.style.width to 'auto' below.
                    replacementContainer.style.display = "block";
                }

                container.style.width = "auto"; // Allow container width to grow or shrink as required.
                const containerW = container.getBoundingClientRect().width;
                const containerH = Math.round(containerW / aspectRatio);
                instance.setPlayerSize(containerW, containerH); // This sets container.style.width and container.style.height to 'px' values.

                if (replacementContainer !== null) {
                    // Set display to inline-block so eg. text-align:right on parent element will cause video to align right.
                    replacementContainer.style.display = "inline-block";
                }
            }

            fixVideoPlayerDimensions(); // Fix dimensions on page load.

            window.addEventListener("resize", fixVideoPlayerDimensions); // Fix dimensions again on resize events.
        },
    };

    if (overrideValueByKey !== undefined) {
        config = Object.assign(config, overrideValueByKey);
    }

    return config;
}
