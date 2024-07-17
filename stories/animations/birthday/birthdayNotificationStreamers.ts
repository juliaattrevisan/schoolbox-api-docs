/**
 * animate the birthday notification streamers
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import Streamer from "./Streamer";
// import { getStreamerCount } from "../animateBadgeImage";

gsap.registerPlugin(Physics2DPlugin);

/**
 * Set up an array of Streamers
 * Put them in the centre width of the Canvas and spread them along the vertical axis a bit so they can 'flick' out
 * @param { HTMLCanvasElement} canvas - the canvas element to draw the streamers on
 * @returns { Array }
 */
function getStreamers(canvas: HTMLCanvasElement) {
    const streamers = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let streamer; // reuse reference in loop
    for (let i = 0; i < 60; i += 1) {
        streamer = new Streamer({
            x: canvasDimensions.width / 2,
            y: gsap.utils.random(50, 300),
            radius: gsap.utils.random(5, 30),
        });
        streamers.push(streamer);
    }
    return streamers;
}

/**
 * Each streamer needs to be removed and redrawn each 'tick'
 * @param { Array }
 * @param { CanvasRenderingContext2D }
 * @param { width, height }
 */
function redrawStreamers(streamers, context, canvasDimensions) {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    streamers.forEach((streamer) => streamer.draw(context));
}

/**
 * Each streamer has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param {GSAPTimeline} timeline - the timeline to add the animations to
 * @param {Streamer} streamer - the streamer to animate
 * @param { width, height }
 */
function addStreamerAnimation(timeline: GSAPTimeline, streamer, canvasDimensions) {
    timeline.to(
        streamer,
        {
            delay: "random(0, 0.2)",
            duration: "random(0.5, 3)",
            rotation: "random(0, 360)",
            x: Math.random() < 0.5 ? -100 : canvasDimensions.width + 100, // streamer moves either to the left or right edge
            physics2D: {
                // https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: "random(0, 10)",
                angle: "random(0, 360)",
                accelerationAngle: "random(0, 0.5)",
                friction: "random(0, 0.1)",
                gravity: "random(100, 200)",
                velocity: "random(800, 1000)",
            },
        },
        0, // 0 so there's no delay between the animations, otherwise each streamer would only animate after the previous is complete
    );
}

/**
 * Get all the streamers, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param {HTMLCanvasElement} canvas - the canvas element to draw the streamers on
 */
function animateStreamers(canvas: HTMLCanvasElement) {
    const streamers = getStreamers(canvas);
    const context = getContext(canvas);
    const canvasDimensions = getCanvasDimensions(canvas);
    const timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.5, // wait prior to animating the streamers ot best sync to the badge animation
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
