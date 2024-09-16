/**
 * To play the finishing animation of the countdown
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import type GSAPTimeline from "gsap";
import Star, { redrawStars } from "../../../../library/Schoolbox/Learning/Badge/Award/js/animations/Star";

gsap.registerPlugin(Physics2DPlugin); // register the physics2D plugin, otherwise the physics2D property won't be recognised

let timeline: GSAPTimeline; // to be able to kill the timeline on destroy
let canvas: HTMLCanvasElement; // to be able to display the animation and destroy it on completion

/**
 * Set up an array of Stars
 * Put them all in the middle of the canvas, so they can 'explode' out from the centre
 * @returns - the stars to animate
 */
function getStars(): Star[] {
    const stars = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let star; // reuse reference in loop
    // for (let i = 0; i < getStarCount(canvas); i += 1) {
    for (let i = 0; i < 200; i += 1) {
        star = new Star({
            x: canvasDimensions.width / 2,
            y: canvasDimensions.height / 2,
            radius: gsap.utils.random(5, 50),
        });
        stars.push(star);
    }
    return stars;
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param star - the star to animate
 */
function addStarAnimation(star: Star): void {
    timeline.to(
        star,
        {
            duration: 3,
            radius: "random(0, 0.3)",
            rotation: "random(0, 360)",
            physics2D: {
                // https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: 0,
                angle: "random(0, 360)",
                accelerationAngle: "random(0, 0.1)",
                friction: "random(0, 0.1)",
                gravity: "random(300, 500)",
                velocity: "random(500, 1550)",
            },
        },
        0, // 0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}

/**
 * Set up both the badge and the stars animations
 */
function destroy(): void {
    timeline.kill();
    const context = getContext(canvas) as CanvasRenderingContext2D;
    context.clearRect(0, 0, canvas.width, canvas.height);
    canvas.remove();
}

/**
 * Get all the stars, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 */
function animateStars(): void {
    const stars = getStars();
    const context = getContext(canvas);
    if (!context) {
        return;
    }
    const canvasDimensions = getCanvasDimensions(canvas);
    timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), // to draw the new position of the stars each tick
        onComplete: () => destroy(),
    });
    stars.forEach((star) => addStarAnimation(star));
}

/**
 * Create a canvas element to draw the stars on, using the position of the domRect
 * @param domRect - The bounding client rectangle of the target element
 */
function createCanvas(domRect: DOMRect): void {
    canvas = document.createElement("canvas");
    canvas.width = domRect.width;
    canvas.height = domRect.height;
    canvas.style.position = "absolute";
    canvas.style.left = `${domRect.left}px`;
    canvas.style.top = `${domRect.top + window.scrollY}px`;
    canvas.style.zIndex = "var(--sbx-depth-pop-over)";
    document.body.appendChild(canvas);
}

/**
 * Play the finishing animation of the countdown
 * @param  domRect - The bounding client rectangle of the target element
 */
export function play(domRect: DOMRect): void {
    createCanvas(domRect);
    animateStars();
}
