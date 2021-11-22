
/**
 * The star used by the badge award animations
 * It's to be used within a <canvas></canvas> element 
 */


const colours = ['#ff7d00', '#ffd100', '#cce300', '#73c700', '#1c9e1c', '#009eb2', 
                 '#00e38f', '#4dc7eb', '#00aded', '#0080ff', '#0066b0', '#6900b0', 
                 '#c2009e', '#ff4a73', '#fa4a47', '#bf1f2e'];

export default class Star {

    /**
     * Store the properties for reference within draw
     * 
     * @param { x, y, radius }
     */
    constructor (init) {
        this.x = init.x;
        this.y = init.y;
        this.radius = init.radius;
        this.color = this.getRandomColour();
        this.rotation = 0;
    }

    /**
     * Get a random colour
     * 
     * @returns { String } 
     */
    getRandomColour () {
        return colours[Math.floor(Math.random()*colours.length)];
    }

    /**
     * Draw the star as specified by the current properties
     * 
     * @param { CanvasRenderingContext2D } https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
     */
    draw (context) {
        
        context.save(); //save the default state prior to rotation
  
        context.translate(this.x, this.y); //to rotate the canvas
        context.rotate(this.rotation * Math.PI / 180);
        context.translate(-this.x, -this.y);
        
        context.beginPath();
        //5 point star from: https://stackoverflow.com/questions/14580033/algorithm-for-drawing-a-5-point-star
        const alpha = (2 * Math.PI) / 10; //the angle of rotation to draw the line
        let rotation;
        let omega;
        for (var i = 11; i != 0; i--) { //there's 11 points to the star, so we draw 11 lines
            rotation = this.radius * (i % 2 + 1)/2;
            omega = alpha * i;
            context.lineTo((rotation * Math.sin(omega)) + this.x, (rotation * Math.cos(omega)) + this.y);
        }
        context.closePath();    

        context.fillStyle = this.color;
        context.fill(); //then fill it with the colour specified

        context.restore(); //restore to original top/left translation

    }

}