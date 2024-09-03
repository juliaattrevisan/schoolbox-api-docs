/**
 * Just to play around with some basic concepts of Typescript
 *
 */

import type Developer from "./types/Developer"; // we add the 'type' key after import to inform that it's an interface
import Feels from "./types/Feels"; // we add the 'type' key after import to inform that it's an interface

const myself: Developer = {
    firstName: "Michael",
    lastName: "Heydon",
    age: 36,
    // languages: 'TypeScript', // this would error and not build, as it expects an array - it should also highlight in your IDE?
    languages: ["TypeScript"],
    // feeling: 'COOL', // this would error as it expects something from the Feels enum
    feeling: Feels.COOL,
};

/**
 * Say hello
 * @param {Developer} person - person to say hello to
 * @returns {string} say hello
 */
function hello(person: Developer): string {
    return `Hi there ${person.firstName.toUpperCase()}!`; // the IDE will allow better autocomplete on firstName as it knows it's a string
}

hello(myself);

/**
 * Log name or age
 * @param { number | string } nameOrAge - log either name or age
 * @returns { string } - to return name or age
 */
function printNameOrAge(nameOrAge: number | string): string {
    // union types can be used to declare multiple allowed property types
    return `Your name or age is: ${nameOrAge}`;
}

printNameOrAge(myself.lastName);
printNameOrAge(myself.age);

/**
 * We can use optional parameters
 * @param { string } name - name to greet
 * @returns { string } - say hello
 */
function greet(name?: string): string {
    if (name) {
        return `Hello ${name}`;
    }
    return "Hello Stranger";
}

greet();

// type assertions to give Typescript hints on the type
// for example Typescript returns this as HTMLElement
// const htmlElement = document.getElementById('add-to-me');
// commenting this out should provide a warning that htmlElement is not being used

// but we know that it's actually going to be a HTMLParagraphElement
// which will give us more confidence on the properties we can access/change
const htmlParagraphElement = document.getElementById("add-to-me") as HTMLParagraphElement;
// htmlParagraphElement.inerText += ' Yep, check the console!' //like this would error and highlights as such in the IDE
htmlParagraphElement.innerText += " Yep, check the console!";
