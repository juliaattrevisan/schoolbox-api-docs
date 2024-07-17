/**
 * animate the birthday notification streamers
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import type { CanvasDimensions } from "@js/util/canvasUtils";
import type GSAPTimeline from "gsap";
import Streamer from "./Streamer";
// import { getStreamerCount } from "../animateBadgeImage";

gsap.registerPlugin(Physics2DPlugin); // register the physics2D plugin, otherwise the physics2D property won't be recognised

/**
 * Set up an array of Streamers
 * Put them in the centre width of the Canvas and spread them along the vertical axis a bit so they can 'flick' out
 * @param { HTMLCanvasElement} canvas - the canvas element to draw the streamers on
 * @returns {Streamer[]} - the streamers to animate
 */
function getStreamers(canvas: HTMLCanvasElement) {
    const streamers = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let streamer; // reuse reference in loop
    for (let i = 0; i < 50; i += 1) {
        streamer = new Streamer({
            x: gsap.utils.random(0, canvasDimensions.width),
            y: -50,
            radius: gsap.utils.random(6, 12),
        });
        streamers.push(streamer);
    }
    return streamers;
}

/**
 * Each streamer needs to be removed and redrawn each 'tick'
 * @param {Streamer[]} streamers - the streamers to redraw
 * @param {CanvasRenderingContext2D} context - the context to draw the streamers on
 * @param {CanvasDimensions} canvasDimensions - the dimensions of the canvas
 */
function redrawStreamers(streamers: Streamer[], context: CanvasRenderingContext2D, canvasDimensions: CanvasDimensions) {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    streamers.forEach((streamer) => streamer.draw(context));
}

/**
 * Each streamer has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param {GSAPTimeline} timeline - the timeline to add the animations to
 * @param {Streamer} streamer - the streamer to animate
 */
function addStreamerAnimation(timeline: GSAPTimeline, streamer: Streamer) {
    timeline.to(
        streamer,
        {
            delay: "random(0, 3)",
            duration: "random(3.5, 6.5)",
            radius: "random(0, 0.5)",
            rotation: "random(0, 360)",
            repeat: -1,
            y: "random(100, 200)",
        },
        0, // 0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}

/**
 * Get all the streamers, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param {HTMLCanvasElement} canvas - the canvas element to draw the streamers on
 */
function animateStreamers(canvas: HTMLCanvasElement) {
    const streamers = getStreamers(canvas);
    const context = getContext(canvas) as CanvasRenderingContext2D;
    const canvasDimensions = getCanvasDimensions(canvas);
    const timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.2, // wait prior to animating the streamers ot best sync to the badge animation
        repeat: 0,
        onUpdate: () => redrawStreamers(streamers, context, canvasDimensions), // to draw the new position of the streamers each tick
    });
    streamers.forEach((streamer) => addStreamerAnimation(timeline, streamer, canvasDimensions));
}

/**
 * Set up both the badge and the streamers animations
 * @param {HTMLCanvasElement} canvas - the canvas element to draw the streamers on
 */
export function init(canvas: HTMLCanvasElement) {
    animateStreamers(canvas);
}
