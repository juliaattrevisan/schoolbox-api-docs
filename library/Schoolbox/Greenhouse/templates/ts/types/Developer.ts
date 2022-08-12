/**
 * Interfaces can also be extended upon
 */

import type Person from './Person';
import type Feels from './Feels';

export default interface Developer extends Person {
    languages: string[]; //an array of strings
    feeling: Feels;
}