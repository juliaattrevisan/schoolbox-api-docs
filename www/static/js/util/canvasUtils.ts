/**
 * HTML canvas util methods to ge the object, size and clear once used.
 */

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 * @param canvas { HTMLCanvasElement }
 * @return { Number, Number }
 */
export function getCanvasDimensions(canvas: HTMLCanvasElement) {
    return {
        width: canvas.width,
        height: canvas.height,
    };
}

/**
 * Get the context
 * https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
 *
 * @param canvas { HTMLCanvasElement }
 * @return { CanvasRenderingContext2D|null }
 */
export function getContext(canvas: HTMLCanvasElement) {
    return canvas.getContext("2d");
}

/**
 * Will be decreased on smaller screens, as there's not as much space to show them.
 * This gives us about 600 at max width (972px) and decreases as we get down to around 168 at (272px),
 * which is an iPhone 5, a pretty small screen.
 *
 * @param canvas { HTMLCanvasElement }
 * @return { Number }
 */
export function getStarCount(canvas: HTMLCanvasElement) {
    const currentCanvasDimensions = getCanvasDimensions(canvas);
    return Math.round(currentCanvasDimensions.width * 0.62);
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 * @param canvas { HTMLCanvasElement }
 */
export function clearCanvas(canvas: HTMLCanvasElement) {
    const canvasDimensions = getCanvasDimensions(canvas);
    const context = getContext(canvas);
    context?.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
}
