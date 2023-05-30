/**
 * Return a config object with default Schoolbox options set.
 *
 * Usage:
 *    import { getConfigForVideo } from "@js/mediaelementplayer/mepUtils";
 *    const player = new MediaElementPlayer(el, getConfigForVideo());
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
         * See: https://github.com/mediaelement/mediaelement/blob/master/docs/api.md
         */
        stretching: "none",

        /**
         * @param media - the <mediaelementwrapper> DOM node (contains the <video> node)
         * @param node - the <video> DOM node
         * @param instance - the relevant instance of MediaElementPlayer
         */
        success: function (media: HTMLElement, node: HTMLElement, instance: MediaElementPlayer) {
            // The aspect ratio for the video container is fixed for all videos and all screen sizes.  This is
            // done so that portrait videos do not take up too much vertical space.
            const aspectRatio = 16 / 9;

            // This is the container for all mejs nodes (including <mediaelementwrapper>).
            const container = node.closest(".mejs__container") as HTMLElement;

            if (container === null) {
                return;
            }

            if (node.classList.contains("full-width")) {
                node.style.width = "100%";
                container.style.height = Math.round(container.getBoundingClientRect().width / aspectRatio) + "px";
            }

            // Ensure the video poster fills the container with the correct aspect ratio, and has black bars
            // added on the top/bottom or sides if necessary instead of having a hidden part overflow the
            // bounds of the container.
            const poster = container.querySelector(".mejs__poster");
            if (poster !== null) {
                poster.removeAttribute("style");
            }

            // Ensure the video dimensions match the container dimensions when the video is playing.
            // Documentation: https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
            const containerW = container.getBoundingClientRect().width;
            const containerH = containerW / aspectRatio;
            instance.setPlayerSize(containerW, containerH);

            // Ensure the video container adjusts in size correctly as the window size changes (both when it
            // is playing and not playing).
            // If this function is removed (with the MediaElementPlayer 'stretching' option remaining as 'none'):
            //  * downsizing the window from a large size will cause a horizontal scrollbar to appear on the
            //    video container
            //  * when upsizing the window from a small size, the video will remain small as the surrounding
            //    space grows
            //  * The video content size will not change as the window grows/shrinks.
            window.addEventListener("resize", function () {
                container.style.width = "auto";
                const containerW = container.getBoundingClientRect().width;
                const containerH = Math.round(containerW / aspectRatio);

                container.style.height = containerH + "px";
                instance.setPlayerSize(containerW, containerH);
            });
        },
    };

    if (overrideValueByKey !== undefined) {
        config = Object.assign(config, overrideValueByKey);
    }

    return config;
}
