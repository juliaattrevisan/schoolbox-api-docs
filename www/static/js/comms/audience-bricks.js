/**
 * JS Object encapsulation considerations:
 * Ideally we would have public prototype methods, on private properties to enforce encapsulation.
 * But logging of objects to console does not show private property values, which is difficult to debug.
 * We would desire public accessor methods for type-hinting.
 * Trade-offs:
 * * use this.x style for variables so we can see their values when logging to console
 * * use privileged getter methods to type-hint instance properties
 * * use private methods for helper logic
 * * no point using public methods as they need to proxy through privileged to hit private.
 */

/**
 * Codec (Encoder + Decoder)
 *
 * Objects of this class are responsible for converting an audience segment into a string to be used as a unique selectize option key.
 * An audience segment may be simple, or composite, where the composite is the intersect of 2:N simple sub-segments.
 * Note that the rendered audience 'bricks' and vue-model data structures are N:1 to an audience segment.
 *
 * Design Considerations:
 * * Must not be part of the base64 charset to ensure we don't split on the encoded entityValue
 * * Must ensure simple segment delimiter in both simple and composite segments to be the same to enable code reuse
 * * place entityType first so we can easily determine outer segment simple/composite nature
 * * Must match server-side codec algorithm.
 *
 * Examples:
 *      "folder:ef:self"                                     [folder:ef=]^self
 *      "composite;parents_of;folder:ef:self,role:bc:self"   [folder:ef= ∩ role:bc]^parents_of
 *      "composite;self;folder:ef:self,folder:bc:self"       [folder:ef= ∩ folder:bc]^self
 *
 * Note how the first simple string is present inside the first composite string
 *
 * @constructor
 * @see Audience\Segment\Codec
 */
function Codec() {
    "use strict";
    if (!(this instanceof Codec)) {
        throw new Error("Codec needs to be called with the new keyword");
    }

    // Private vars. Note that selectize uses a ',' so we can't use that.
    var delimiterComposite  = ';',
        delimiterSimple     = ':',
        delimiterChildren   = '.';

    /**
     * Returns a encodedString that uniquely identifies a Segment
     *
     * @param {Segment} segment
     * @return {string}
     */
    this.encode = function (segment) {
        if(segment.isComposite()){
            return encodeComposite(segment);
        }
        return encodeSimple(segment);
    };

    /**
     * @param {string} entityType
     * @param {string} entityValue
     * @param {string} modifier
     */
    this.encodeSimpleAttributes = function (entityType, entityValue, modifier) {
        return encodeSimpleParts(entityType, entityValue, modifier);
    };

    /**
     * Returns a Segment from an encodedString
     *
     * @param {string} encodedString
     * @return {Segment}
     */
    this.decode = function (encodedString) {
        if(0 === encodedString.indexOf('composite')){
            return decodeComposite(encodedString);
        }
        return decodeSimple(encodedString);
    };

    /**
     * Decodes an encoded value. Must match the server-side Codec implementation
     *
     * @param {string} encodedValue
     * @returns {null|string}
     */
    this.decodeEncodedValue = function(encodedValue) {
        return JSON.parse(atob(encodedValue)) ;
    };

    /** @param {Segment} segment */
    var encodeSimple = function (segment) {
        return encodeSimpleParts(segment.getEntityType(), segment.getEntityValue(), segment.getModifier());
    };

    /**
     * @param {string} entityType
     * @param {string} entityValue
     * @param {string} modifier
     */
    var encodeSimpleParts = function (entityType, entityValue, modifier) {
        var parts = [entityType, entityValue, modifier];
        return parts.join(delimiterSimple);
    };

    /**
     * @param {string} encodedString
     * @return Segment
     */
    var decodeSimple = function (encodedString) {
        var parts = encodedString.split(delimiterSimple);
        var entityType  = parts[0],
            entityValue = parts[1],
            modifier    = parts[2];
        return new Segment(entityType, entityValue, modifier, null);
    };

    /** @param {Segment} segment */
    var encodeComposite = function (segment) {
        var childParts = [],
            childSegments = segment.getChildren().sort(sortSegments);
        // Need to sort children so that the following are always recognised as being the same by encoding to a unique key
        // [X:1^S ∩ Y:2^S] and [Y:2^S ∩ X:1^S] - ie entityType ordering
        // [X:1^S ∩ X:2^S] and [X:2^S ∩ X:1^S] - ie entityValue ordering
        // [X:1^S ∩ X:1^P] and [X:1^P ∩ X:1^S] - ie entityModifier ordering
        for (var i = 0; i < childSegments.length; ++i) {
            childParts.push(encodeSimple(childSegments[i]));
        }
        var encodedChildren = childParts.join(delimiterChildren);
        var parts = [segment.getEntityType(), segment.getModifier(), encodedChildren];
        return parts.join(delimiterComposite);
    };

    /**
     * @param {string} encodedString
     * @return Segment
     */
    var decodeComposite = function (encodedString) {
        var parts = encodedString.split(delimiterComposite);
        var entityType      = parts[0],
            modifier        = parts[1],
            encodedChildren = parts[2];

        var children = [];
        var encodedChildrenArray = encodedChildren.split(delimiterChildren);
        var childSegment;
        for (var i = 0; i < encodedChildrenArray.length; ++i) {
            childSegment = decodeSimple(encodedChildrenArray[i]);
            // Cannot have nested non-self modifiers
            if('self' !== modifier && 'self' !== childSegment.getModifier()){
                throw new Error("Composite segment cannot nest non-self modifiers. Found composite:'" + modifier + "', child:'" + childSegment.getModifier() + "'");
            }
            children.push(childSegment);
        }

        // If for some reason we have a composite, but all children are identical, we can reduce to simple form.
        // ie [X ∩ X]^P => X^P, [X^P ∩^P X]^S => X^P
        /** @var {null|Segment} firstSegment */
        var firstSegment = null;
        var inEqualChildFound = false;
        for(var j = 0; j < children.length; ++j){
            if(!firstSegment){
                firstSegment = children[j];
            } else {
                if(!firstSegment.equals(children[j])){
                    inEqualChildFound = true;
                    break;
                }
            }
        }

        // Are all children the same?
        if(!inEqualChildFound){
            // Return the equivalent simple segment.
            var simpleModifier = 'self' !== modifier ? modifier : firstSegment.getModifier();
            return new Segment(firstSegment.getEntityType(), firstSegment.getEntityValue(), simpleModifier, null);
        }

        // Return a composite segment
        return new Segment(entityType, null, modifier, children);
    };

    /**
     * Returns whether a Segment is less or greater than another Segment
     *
     * @param {Segment} left
     * @param {Segment} right
     * @throws If unable to differentiate
     */
    var sortSegments = function(left, right){
        // first sort by entityType
        if(left.getEntityType() < right.getEntityType()){
            return -1;
        }
        if(left.getEntityType() > right.getEntityType()){
            return +1;
        }
        // Yes, it's possible to target the intersection of two entities of the same type, eg pastoral groups which are 1:N with users.
        if(left.getEntityValue() < right.getEntityValue()){
            return -1;
        }
        if(left.getEntityValue() > right.getEntityValue()){
            return +1;
        }
        // Yes, it's possible to target the intersection of two entities of the same type, and value.
        // Eg must be a member of House:X, and a parent of a student who is a member of House:X
        if(left.getModifier() < right.getModifier()){
            return -1;
        }
        if(left.getModifier() > right.getModifier()){
            return +1;
        }
        // If we get here, something has gone wrong.
        throw new Error('Unable to distinguish composite segment children for sort. EntityType: ' + left.getEntityType() + ', EntityValue: ' + left.getEntityValue());
    }
}

/**
 * An Audience Segment
 *
 * @constructor
 * @param {string} entityType
 * @param {null|string} entityValue
 * @param {string} modifier
 * @param {null|Segment[]} children
 */
function Segment(entityType, entityValue, modifier, children)
{
    "use strict";
    if (!(this instanceof Segment)) {
        throw new Error("Segment needs to be called with the new keyword");
    }

    var entityTypeComposite = 'composite';

    // Validate
    var errors = [];
    if(typeof entityType !== 'string'){
        errors.push("'entityType' must be a string. Found '" + entityType + "' (" + (typeof entityType) + ")");
    }
    if(typeof entityValue !== 'string' && entityValue !== null){
        errors.push("'entityValue' must be a string or null. Found '" + entityValue + "' (" + (typeof entityValue) + ")");
    }
    if(typeof modifier !== 'string'){
        errors.push("'modifier' must be a string. Found '" + modifier + "' (" + (typeof modifier) + ")");
    } else if(-1 === ['self', 'parents_of', 'teachers_of'].indexOf(modifier)){
        errors.push("Invalid modifier. Found '" + modifier + "'");
    }
    if(!(children instanceof Array) && children !== null){
        errors.push("'children' must be an array or null. Found '" + children + "' (" + (typeof children) + ")");
    }
    if((entityTypeComposite === entityType) !== (children instanceof Array)){
        errors.push("Composite segments must have a child array. Simple segments must have NULL children");
    }
    if(entityTypeComposite === entityType && children.length < 2){
        errors.push("Composite segments must have 2 or more child segments. Found: " + children.length);
    }
    if(entityTypeComposite === entityType){
        for(var i = 0; i < children.length; i++){
            if(entityTypeComposite === children[i].getEntityType()){
                errors.push("Nested composite segment detected at index: " + i + ". " + children[i].toString() + "'");
            }
            for(var j = i + 1; j < children.length; j++){
                if(children[i].equals(children[j])){
                    errors.push("Duplicate children detected at indexes [" + i + "," + j + "] '" + children[i].toString() + "'");
                }
            }
        }
    }
    if(errors.length){
        throw new Error('Invalid construction args.' + "\n" + errors.join("\n"));
    }

    this.entityType  = entityType;
    this.entityValue = entityValue;      // NULL for composites, always encoded for simple segments
    this.modifier    = modifier;
    this.children    = children;         // NULL for simple segments

    /** @return string */
    this.getEntityType = function () {
        return this.entityType;
    };

    /** @return null|string */
    this.getEntityValue = function () {
        return this.entityValue;
    };

    /** @return string */
    this.getModifier = function () {
        return this.modifier;
    };

    /** @return Segment[]|null */
    this.getChildren = function () {
        return this.children;
    };

    /**
     * Returns a string form of this Segment. Used in error messaged.
     * @return string
     */
    this.toString = function(){
        var parts = ['type: ' + this.entityType];

        if(this.isSimple()){
            // Simple Case
            parts.push('value: ' + this.entityValue);
        } else {
            // Composite case
            var childStrings = [];
            for(var i = 0; i < this.children.length; ++i){
                childStrings.push(this.children[i].toString());
            }
            parts.push('value: [' + childStrings.join(';') + ']');
        }
        parts.push('modifier: ' + this.modifier);
        return parts.join(', ');
    };

    /**
     * Returns whether this segment is equal to some other segment.
     * For composites, only considers combination, rather than permutation.
     * @param {Segment} other
     * @return boolean
     */
    this.equals = function (other) {
        if(other.isComposite() !== this.isComposite()){
            return false;
        }

        if(other.getEntityType() !== this.entityType || other.getModifier() !== this.modifier){
            return false;
        }

        // Simple case
        if(other.isSimple()){
            return other.getEntityValue() === this.entityValue;
        }

        // Composite case
        if(other.getChildren().length !== this.children.length){
            return false;
        }

        // Ensure each child of other segment is a child of this segment. Ignores permutation.
        for(var i = 0; i < this.children.length; ++i){
            var matchingChildFound = false;
            for(var j = 0; j < this.children.length; ++j){
                if(this.children[i].equals(other.getChildren()[j])){
                    matchingChildFound = true;
                    break;
                }
            }
            if(!matchingChildFound){
                return false;
            }
        }

        return true;
    };

    /** @return boolean */
    this.isSimple = function() {
        return entityTypeComposite !== this.entityType;
    };

    /** @return boolean */
    this.isComposite = function() {
        return entityTypeComposite === this.entityType;
    };
}

/**
 * Encapsulates an entityType, set of entityValues, and modifier
 *
 * When used in a simple Brick, the dimension modifier should always be 'self', with the segment's modifier applied to the Brick
 * @todo. I now think this is wrong. We should simply make the definition of a simple brick as being 1D. The rendering of a 1D can handle this case,
 * but it will simplify the implementation. I think...
 *
 * @constructor
 * @param {string} entityType
 * @param {string} entityValue
 * @param {string} modifier
 */
function Dimension(entityType, entityValue, modifier) {

    "use strict";
    if (!(this instanceof Dimension)) {
        throw new Error("Dimension needs to be called with the new keyword");
    }

    this.entityType   = entityType;
    this.entityValues = [entityValue];
    this.modifier     = modifier;

    /** @return string */
    this.getEntityType = function () {
        return this.entityType;
    };

    /** @return string[] */
    this.getEntityValues = function () {
        return this.entityValues;
    };

    /** @return string */
    this.getModifier = function () {
        return this.modifier;
    };

    /**
     * Returns the DOF between this Dimension and another Dimensions
     * 0 => no difference
     * 1 => only differ by value
     * 2 => differs by type, or modifier
     * @param {Dimension} other
     * @return int
     */
    this.computeDOF = function(other){
        if(this.entityType !== other.entityType){
            return 2;
        }
        if(this.modifier !== other.modifier){
            return 2;
        }
        var otherValues = other.entityValues;
        if(this.entityValues.length !== otherValues.length){
            return 1;
        } else {
            for(var i = 0; i < otherValues.length; ++i){
                if(!otherValues.hasOwnProperty(i)){
                    continue;
                }
                if(-1 === this.entityValues.indexOf(otherValues[i])){
                    return 1;
                }
            }
        }
        return 0;
    };

    /**
     * Returns whether another Dimensions is logically equivalent to this one
     *
     * @param {Dimension} other
     * @return {boolean}
     */
    this.equals = function(other){
        return 0 === this.computeDOF(other);
    };

    /**
     * Attempts to another dimension's entityValues to this dimension.
     * Returns whether any values were added
     * @param {Dimension} other
     * @throws If the other Dimension has an incompatible entityType or modifier
     */
    this.absorbDimension = function (other) {
        if(other.getEntityType() !== this.entityType){
            throw new Error("Invalid entityType. '" + other.getEntityType() + "' is different to this Dimension's entityType '" + this.entityType + "'");
        }
        if(other.getModifier() !== this.modifier){
            throw new Error("Invalid modifier. '" + other.getModifier() + "' is different to this Dimension's modifier '" + this.modifier + "'");
        }
        var valuesAdded = false,
            otherValues = other.getEntityValues();
        for(var i = 0; i < otherValues.length; ++i){
            var otherValue = otherValues[i];
            if (-1 === this.entityValues.indexOf(otherValue)){
                this.entityValues.push(otherValue);
                valuesAdded = true;
            }
        }
        return valuesAdded;
    }
}

/**
 * A Brick is composed of 1:N Segments. It also contains its own data structures for efficient DOF analysis
 *
 * It may contain either multiple:
 * * simple segments with distinct(entityType, entityValue) eg [Campus:X]^S + [Campus:X]^P
 * * composite segments with distinct(entityTypes)          eg [Campus:X ∩ Folder:F ∩ Folder:X]^S + [Campus:X ∩ Folder:F ∩ Folder:X]^P
 *
 * A simple Brick has the segment modifiers applied to the Brick, and the Dimension modifiers are hardcoded to 'self'.
 * A complex brick can have non-self modifiers at either the Brick or Dimension level (but not both).
 *
 * @constructor
 * @param {Segment} segment
 * @param {Object|string[]} typeLabelMap    - Maps entityTypes to HR form.          Indexed by [entityType]
 * @param {Object|string[][]} valueLabelMap - Translated names used by the segment. Indexed by [entityType][encodedEntityValue]
 */
function Brick(segment, typeLabelMap, valueLabelMap)
{
    "use strict";
    if (!(this instanceof Brick)) {
        throw new Error("Brick needs to be called with the new keyword");
    }

    // Validate constructor args
    var errors = [];
    if(!(segment instanceof Segment)){
        errors.push("'segment' must be an instance of Segment");
    }
    if(!(typeLabelMap instanceof Object)){
        errors.push("'typeLabelMap' must be an instance of Object");
    }
    if(!(valueLabelMap instanceof Object)){
        errors.push("'valueLabelMap' must be an instance of Object");
    }
    if(errors.length){
        throw new Error('Invalid Brick constructor args: ' + "\n" + errors.join("\n"));
    }

    var _isComposite   = segment.isComposite();
    var self           = this;                    // So private methods can access public properties
    this.segments      = [segment];               // Required to be stored so we can remove from selectize if we remove this brick
    this.dimensions    = [];                      // eg [{folder, [A,B]}, {folder, [Y]}, {role, [Z]}]
    this.modifiers     = [segment.getModifier()]; // eg ['self', 'parents_of']
    this.typeLabelMap  = typeLabelMap;            // Need to store a reference so we can inject to new bricks during explosion.
    this.valueLabelMap = valueLabelMap;           // All translated names used by segments in brick. Indexed by [entityType][encodedEntityValue]
                                                  // If an entity's name is not found, fall back to rendering untranslated form, but log to console.

    // Special case for adding initial segment (no DOF constraints)
    if(segment.isSimple()){
        this.dimensions.push(new Dimension(segment.getEntityType(), segment.getEntityValue(), 'self'));
    } else {
        var childSegment, childSegments = segment.getChildren();
        for(var i = 0; i < childSegments.length; ++i){
            childSegment = childSegments[i];
            this.dimensions.push(new Dimension(childSegment.getEntityType(), childSegment.getEntityValue(), childSegment.getModifier()));
        }
    }

    // Bound by Vue to DOM
    this.nameLabel     = '';
    this.typeLabel     = this.getTypeLabel(segment.getEntityType());
    this.modifierLabel = '';

    if(_isComposite){
        this.modalData = [];
        for(var j = 0; j < this.dimensions.length; ++j){
            var dimension = this.dimensions[j];
            this.modalData[j] = {};
            this.modalData[j].typeLabel = this.typeLabelMap[dimension.entityType];
            this.modalData[j].valueLabels = [];
        }
    }

    this.updateLabels();

    /**
     * Returns this Brick's segments
     * @return Segment[]
     */
    this.getSegments = function(){
        return this.segments;
    };

    /**
     * Returns this Brick's modifiers
     * @return string[]
     */
    this.getModifiers = function(){
        return this.modifiers;
    };

    /**
     * Returns this Brick's dimensions
     * @return Dimension[]
     */
    this.getDimensions = function(){
        return this.dimensions;
    };

    /**
     * Returns whether this Brick is composed of simple segments
     * @return boolean
     */
    this.isSimple = function(){
        return !_isComposite;
    };

    /**
     * Returns whether this Brick is composed of composite segments
     * @return boolean
     */
    this.isComposite = function(){
        return _isComposite;
    };

    /**
     * When absorbing another brick, we must also absorb its value label map so we can render it appropriately
     * @param {Object|string[][]} otherValueLabelMap - Translated names used by the segment. Indexed by [entityType][encodedEntityValue]
     */
    this.absorbValueLabelMap = function(otherValueLabelMap){
        for(var entityType in otherValueLabelMap){
            if(!otherValueLabelMap.hasOwnProperty(entityType)){
                continue;
            }
            if(!(entityType in this.valueLabelMap)){
                this.valueLabelMap[entityType] = {};
            }
            for(var encodedVal in otherValueLabelMap[entityType]){
                if(!otherValueLabelMap[entityType].hasOwnProperty(encodedVal)){
                    continue;
                }
                this.valueLabelMap[entityType][encodedVal] = otherValueLabelMap[entityType][encodedVal];
            }
        }
    };

    /**
     * Returns whether another brick is a logical subset of this brick
     * @return boolean
     */
    this.containsBrick = function(other){
        // Check brick modifiers
        var otherModifiers = other.getModifiers();
        for(var i = 0; i < otherModifiers.length; ++i){
            if(-1 === this.modifiers.indexOf(otherModifiers[i])){
                return false;
            }
        }

        // Composite Host, Simple Guest. 1-Dim (simple) can never be a subset of a N-Dim (composite)
        if(this.dimensions.length > other.dimensions.length){
            return false;
        }

        // Simple Host + Simple Guest. Must have same entity type and value
        if(this.isSimple() && other.isSimple()){
            // Note that simple bricks have the modifier applied at the brick level, which was checked above.
            return this.dimensions[0].entityType === other.dimensions[0].entityType && this.dimensions[0].entityValues[0] === other.dimensions[0].entityValues[0];
        }

        // Simple Host, Composite Guest.
        // Composite Host, Composite Guest.

        // The guest must be a more specific form of the host.
        // Each host dimension must contain a distinct guest dimension. Hence we must remove guest dimensions if they match a host to avoid double matches.
        var otherDimensions = other.dimensions.slice(0);

        for(var j = 0; j < this.dimensions.length; ++j){
            var thisDimension  = this.dimensions[j];
            var isSuperSet = false;

            for(var k = 0; k < otherDimensions.length; ++k){
                if(!otherDimensions.hasOwnProperty(k)){
                    continue;
                }
                var otherDimension = otherDimensions[k];
                if(thisDimension.entityType !== otherDimension.entityType || thisDimension.modifier !== otherDimension.modifier){
                    continue;
                }
                var isExtraValue = false;

                for(var l = 0; l < otherDimension.entityValues.length; ++l){
                    if(!otherDimension.entityValues.hasOwnProperty(l)) {
                        continue;
                    }
                    var otherDimensionValue = otherDimension.entityValues[l];
                    if(-1 === thisDimension.entityValues.indexOf(otherDimensionValue)){
                        isExtraValue = true;
                        break;
                    }
                }
                if(isExtraValue){
                    continue;
                }
                isSuperSet = true; // dimensions have same type, modifier, and no otherValues were not found in this dimension values
                otherDimensions.splice(k, 1);
                break;
            }
            if(!isSuperSet){
                return false;
            }
        }

        return true; // No dimension on this brick is not a superset of a dimension on the other brick
    };

    /**
     * Returns whether this brick can absorb another brick to become a larger set
     *
     * This can only happen when one of the following is true:
     * * Other brick has extra brick modifier
     * * Other brick has extra value/s in a single dimension
     *
     * @param {Brick} other
     * @return boolean
     */
    this.canAbsorbBrick = function(other){
        if(this.isSimple() !== other.isSimple()){
            return false;
        }
        if(this.dimensions.length !== other.dimensions.length){
            return false;
        }

        if(this.containsBrick(other)){
            return false;
        }

        // Would we need to increase the brick modifiers?
        var brickModifierDOF = 0;

        for(var i = 0; i < other.modifiers.length; ++i){
            if(!other.modifiers.hasOwnProperty(i)){
                continue;
            }
            if(-1 === this.modifiers.indexOf(other.modifiers[i])){
                brickModifierDOF = 1;
                break;
            }
        }

        var filteredDimensions = strip0dofDimensionPairs(this.dimensions, other.dimensions);
        var thisDimensions     = filteredDimensions[0];
        var otherDimensions    = filteredDimensions[1];

        // Need to find count of dimensions that can be merged (but not subset);
        var countCompatibleDim = 0;

        for(var j = 0; j < otherDimensions.length; ++j){
            var otherDimension = otherDimensions[j];

            var minDof = 2;
            for(var k = 0; k < thisDimensions.length; ++k){
                var thisDimension = thisDimensions[k];
                minDof = Math.min(minDof, thisDimension.computeDOF(otherDimension));
                if(0 === minDof){
                    break;
                }
            }
            if(2 === minDof){
                // Unable to merge the other dimension with any on this brick.
                return false;
            }
            if(1 === minDof){
                countCompatibleDim++;
            }
            if(countCompatibleDim >= 2){
                return false;
            }
        }
        // Simple bricks can only absorb if they differ just in brick modifier
        if(this.isSimple()){
            return 0 === countCompatibleDim && 1 === brickModifierDOF;
        }

        return  1 === countCompatibleDim + brickModifierDOF;

    };

    /**
     * In some cases, we need to traverse the dimensions without 0DOF pairs matching as 1DOF pairs.
     * To do so, we must remove them from both arrays.
     *
     * This assumes arrays are of the same length.
     *
     * @param {Dimension[]} thisDimensions
     * @param {Dimension[]} otherDimensions
     * @return Dimension[][] The dimensions with the 0Dof pairs removed
     */
    var strip0dofDimensionPairs = function(thisDimensions, otherDimensions){
        var zeroDofs = [];
        for(var i = 0; i < thisDimensions.length; ++i){
            for(var j = 0; j < otherDimensions.length; ++j){
                if(thisDimensions[i].equals(otherDimensions[j])){
                    zeroDofs.push(thisDimensions[i]);
                    break;
                }
            }
        }

        function filterODofs(dimension){
            for(var k = 0; k < zeroDofs.length; ++k){
                if(zeroDofs[k].equals(dimension)){
                    return false;
                }
            }
            return true;
        }

        var filteredThis = thisDimensions.filter(filterODofs),
            filteredThat = otherDimensions.filter(filterODofs);

        return [filteredThis, filteredThat];
    };

    /**
     * Attempts to absorb another brick. Returns whether this Brick was mutated to absorb the other Brick.
     * @param {Brick} other
     * @return boolean
     */
    this.absorbBrick = function(other){
        if(this.containsBrick(other) || !this.canAbsorbBrick(other)){
            return false;
        }
        absorbBrickSegments(other);
        this.absorbValueLabelMap(other.valueLabelMap);

        // Are we just mutating the brick modifier?
        if(computeBrickModifierDOF(other)){
            var otherModifiers = other.getModifiers();
            for(var i = 0; i < otherModifiers.length; ++i){
                if(-1 === this.modifiers.indexOf(otherModifiers[i])){
                    this.modifiers.push(otherModifiers[i]);
                    this.updateLabels();
                    return true;
                }
            }
            throw new Error('Unable to aggregate brick by modifier');
        }

        // Need to strip all 0-DOF dimensions as destination candidates
        var candidateDimensions = this.dimensions.slice(0),
            otherDimensions     = other.getDimensions(),
            sourceDimension     = null;

        // For each other dimension
        for(var k = 0; k < otherDimensions.length; ++k){
            var otherDimension = otherDimensions[k],
                is0DOF         = false;

            // Compare it to each remaining candidate on this brick
            for(var j = 0; j < candidateDimensions.length; j++) {
                var dimension = candidateDimensions[j];
                var dof = dimension.computeDOF(otherDimension);

                // Is this a 0-DOF pair?
                if(!dof) {
                    candidateDimensions.splice(j, 1);
                    is0DOF = true;
                    break;
                }
            }
            if(!is0DOF){
                // We can conclude this is the other dimension we will aggregate
                sourceDimension = otherDimension;
            }
        }

        if(1 !== candidateDimensions.length || null === sourceDimension) {
            throw new Error('Unable to find a dimension to aggregate');
        }

        if(!candidateDimensions[0].absorbDimension(sourceDimension)){
            throw new Error('Unable to absorb brick by aggregating dimension');
        }
        this.updateLabels();
        return true;
    };

    /**
     * Inserts all the other Brick's segments into this Brick
     *
     * @param {Brick} other
     */
    var absorbBrickSegments = function(other){
        var otherSegments = other.getSegments();
        for(var i = 0; i < otherSegments.length; ++i){
            self.segments.push(otherSegments[i]);
        }
    };

    /**
     * Returns DOF between this Brick and another Brick's modifiers
     *
     * @param {Brick} other
     * @return int
     */
    var computeBrickModifierDOF = function(other){
        var otherBrickModifiers = other.getModifiers();
        if(otherBrickModifiers.length !== self.modifiers.length){
            // Different length, then different.
            return 1;
        } else {
            // Same length, need to check each of the other modifiers are present in these modifiers.
            for(var i = 0; i < otherBrickModifiers.length; ++i){
                if(otherBrickModifiers.hasOwnProperty(i) && -1 === self.modifiers.indexOf(otherBrickModifiers[i])){
                    return 1;
                }
            }
        }
        return 0
    };

    /**
     * Explodes this Brick into N bricks, each containing 1 Segment only

     * @return Brick[]
     */
    this.explode = function() {
        var bricks = [];
        for(var i = 0; i < this.segments.length; ++i){
            if(this.segments.hasOwnProperty(i)){
                var segment     = this.segments[i],
                    entityNames = {};
                    copySegmentValueLabels(segment, entityNames);
                bricks.push(new Brick(segment, this.typeLabelMap, entityNames));
            }
        }
        return bricks;
    };

    /**
     * Attempt to recursively copy all names for this segment from this brick to the passed in entityNames object
     *
     * @param {Segment}           segment          A simple segment
     * @param {Object|string[][]} entityNames
     */
    var copySegmentValueLabels = function(segment, entityNames){

        // Base Case
        if(segment.isSimple()){
            var type  = segment.getEntityType(),
                value = segment.getEntityValue();
            if(!self.valueLabelMap.hasOwnProperty(type)){
                // Short-circuit if the brick doesn't have the translated name. (eg unit-tests)
                return false;
            }
            if(!entityNames.hasOwnProperty(type)){
                entityNames[type] = {};
            }
            if(entityNames[type].hasOwnProperty(value)){
                entityNames[type][value] =  self.valueLabelMap[type][value];
            }
            return;
        }

        // Recursive case
        var childSegments = segment.getChildren();
        for(var i = 0; i < childSegments.length; ++i){
            copySegmentValueLabels(childSegments[i], entityNames);
        }
    }
}

/**
 * Updates all labels on this brick
 * @todo No need to do this during brick build. Instead just set a dirty flag on brick update
 * @todo and when brick build algorithm is over, then update the labels on all dirty bricks.
 */
Brick.prototype.updateLabels = function(){
    this.nameLabel     = this.getNameLabel();
    this.modifierLabel = this.getModifierLabel(this.modifiers);

    if(this.modalData) {
        var label;
        for (var i = 0; i < this.dimensions.length; ++i) {
            var dimension  = this.dimensions[i],
                entityType = dimension.entityType;

            for(var j = 0; j < dimension.entityValues.length; ++j){
                if(dimension.entityValues.hasOwnProperty(j)){
                    var encodedValue = dimension.entityValues[j];

                    label = this.valueLabelMap.hasOwnProperty(entityType) && this.valueLabelMap[entityType].hasOwnProperty(encodedValue)
                        ? this.valueLabelMap[entityType][encodedValue]
                        : encodedValue;
                    if(-1 === this.modalData[i].valueLabels.indexOf(label)){
                        this.modalData[i].valueLabels.push(label);
                    }
                }
            }
            this.modalData[i].valueLabels.sort();
        }
    }
};

/**
 * Returns a human-friendly name for the bricks' entity values.
 * Falls back to raw (encoded) entity value if unable to find a translated form
 * @return string
 */
Brick.prototype.getNameLabel = function(){
    // Crude test for simple brick to enable calling before instance properties are instantiated.
    if(1 === this.dimensions.length){
        var soleDimension = this.dimensions[0],
            type   = soleDimension.getEntityType(),
            value = soleDimension.getEntityValues()[0];

        if(this.valueLabelMap.hasOwnProperty(type) && this.valueLabelMap[type].hasOwnProperty(value)){
            return this.valueLabelMap[type][value];
        }
        return value;
    }
    var sortedDimensions = this.dimensions.slice(0).sort(this.sortDimensions),
        parts            = [];

    for(var i = 0; i < sortedDimensions.length; ++i){
        var dimension = sortedDimensions[i];
        var name = dimension.getEntityValues().length + " " + this.getTypeLabel(dimension.getEntityType(), (dimension.getEntityValues().length > 1));
        parts.push(name);
    }
    return parts.join(' & ');
};

/**
 * A function to sort dimensions
 * @param {Dimension} left
 * @param {Dimension} right
 */
Brick.prototype.sortDimensions = function(left, right){
    // Compare entity types
    if(left.getEntityType() < right.getEntityType()){
        return -1;
    }
    if(left.getEntityType() > right.getEntityType()){
        return +1;
    }

    // Compare modifier
    var leftModIdx  = 'self' === left.getModifier() ? 0 : ('parents_of' === left.getModifier() ? 1 : 2),
        rightModIdx = 'self' === right.getModifier() ? 0 : ('parents_of' === right.getModifier() ? 1 : 2);
    if(leftModIdx < rightModIdx){
        return -1;
    }
    if(rightModIdx > leftModIdx){
        return +1;
    }

    // Compare values
    var leftValues  = left.getEntityValues().slice(0).sort().join(','),
        rightValues = right.getEntityValues().slice(0).sort().join(',');

    if(leftValues < rightValues){
        return -1;
    }
    if(leftValues > rightValues){
        return +1;
    }
    throw new Error('Unable to differentiate dimensions for sorting');
};

/**
 * Returns a entityType label for this brick by examining the brick's initial segment.
 *
 * We only have to do this once on brick creation, as this brick attribute is immutable.
 *
 * @param {string} entityType
 * @param {boolean} plural
 * @return {string}
 */
Brick.prototype.getTypeLabel = function (entityType, plural) {
    if (this.typeLabelMap.hasOwnProperty(entityType)) {
        if (plural) {
            return this.typeLabelMap[entityType].plural;
        } else {
            return this.typeLabelMap[entityType].singular;
        }
    }
    return entityType;
};

/**
 * Returns a human-friendly label that represents a set of modifiers
 *
 * @param {string[]} modifiers
 * @return string
 */
Brick.prototype.getModifierLabel = function(modifiers){
    var modifierLabels = {
        1: '',
        2: '(Parents of students only)',
        3: '(Inc. parents of students)',
        4: '(Teachers of students only)',
        5: '(Inc. teachers of students)',
        6: '(Teachers & parents of students only)',
        7: '(Inc. teachers & parents of students)'
    };
    return modifierLabels[this.getModifiersBitMap(modifiers)];
};

/**
 * Returns bitmap integer that represents a set of modifiers
 *
 * @param {string[]} modifiers
 * @return int
 */
Brick.prototype.getModifiersBitMap = function(modifiers){
    var isSelf     = -1 !== modifiers.indexOf('self') ? 1 : 0;
    var isParents  = -1 !== modifiers.indexOf('parents_of') ? 1 : 0;
    var isTeachers = -1 !== modifiers.indexOf('teachers_of') ? 1 : 0;
    return isSelf + 2 * isParents + 4 * isTeachers;
};

/**
 * A BrickManager knows how to build Bricks out of Segments and other Bricks.
 *
 * Bricks can absorb other Bricks if the other brick is 1-degree of freedom difference from this brick.
 *
 * Degree-of-Freedom Analysis (much easier on paper)
 *   Consider a brick composed of simple segments, [(Campus:X)^S]^SP
 *   A segment [Campus:X]^T can be absorbed by this brick because it differs only in modifier (ie 1-degree)
 *
 *   Consider a brick composed of composite segments, eg [(Campus:A,B)^S ∩ (House:F)^S ∩ (Role:X)^S]^S.
 *   The following can be absorbed:
 *   * [Campus:C,D ∩ House:F ∩ Role:X]^S - just Campus is different
 *   * [Campus:A,B ∩ House:G ∩ Role:X]^S - Just House is different
 *   * [Campus:A,B ∩ House:F ∩ Role:Y]^S - just Role is different
 *   * [Campus:A,B ∩ House:F ∩ Role:X]^P - just modifier is different
 *
 *   The following cannot be absorbed:
 *   * [Campus:C,D ∩ House:G ∩ Role:X]^S - both Campus and House are different
 *
 *   Note that in the complex case, it is possible that multiple Bricks are able to absorb a Brick, in
 *   this case we sort the candidate Bricks to ensure consistency when building bricks, regardless of whether loaded from DB or modal.
 *
 *   Whenever a Brick absorbs another Brick or Segment, it now becomes a candidate for absorption itself because it may now be 1-degree of freedom away from another Brick.
 *
 *   To remove a Segment from a Brick, we explode the Brick into Bricks that are 1:1 with constituent Segments, remove the Brick that represents
 *   the relevant Segment, then aggregate the remaining bricks according to the aforementioned algorithm.
 *
 * @param {Vue} vue
 * @param {Codec} codec
 * @param {Object|string[]} entityTypeLabelMap  Maps entity types to human-friendly labels
 * @constructor
 */
function BrickManager(codec, entityTypeLabelMap) {
    "use strict";

    // Validate construction
    var errors = [];
    if (!(this instanceof BrickManager)) {
        errors.push("BrickManager needs to be called with the new keyword");
    }
    if (!(codec instanceof Codec)) {
        errors.push("codec needs to be an instance of Codec");
    }
    if (!(entityTypeLabelMap instanceof Object)) {
        errors.push("entityTypeLabelMap needs to be an instance of Object");
    }
    if(errors.length){
        throw new Error("Invalid construction args: \n\t" + errors.join("\n\t"));
    }

    var self                = this;
    this.codec              = codec;
    this.entityTypeLabelMap = entityTypeLabelMap;

    /**
     * Add a segment to the Bricks

     * @param {Selectize}  selectize
     * @param {Brick[]}    bricks
     * @param {string}     key                - The audience segment identifier, eg 'folder:Mzgx:parents_of'
     * @param {string}     name               - The audience segment label, eg "Senior ∩ Junior Students", "English 34 (ENG34)"
     * @param {jQuery}     $item              - The jQuery wrapper of the DOM element that is displayed in default selectize
     * @param {string[][]} segmentValueLabels - Translated names. Indexed by [entityType][encodedEntityValue]
     * @return boolean
     */
    this.onAddSegment = function(selectize, bricks, key, name, $item, segmentValueLabels){
        var newBrick        = new Brick(codec.decode(key), this.entityTypeLabelMap, segmentValueLabels),
            brickCandidates = [];

        for(var i = 0; i < bricks.length; ++i){
            var brick = bricks[i];
            if(brick.containsBrick(newBrick)){
                selectize.removeItem(key);
                return false;
            }
            if(brick.canAbsorbBrick(newBrick)){
                brickCandidates.push(brick);
            }
        }

        // Did we find any suitable candidate bricks?
        if(brickCandidates.length){
            // Find the optimal brick and insert segment there
            var optimalBrick = computeOptimalHostBrick(brickCandidates, newBrick);
            optimalBrick.absorbBrick(newBrick);
            onBrickChanged(selectize, bricks, optimalBrick);
        } else {
            // Create a new brick with the segment
            bricks.push(newBrick);
            onBrickChanged(selectize, bricks, newBrick);
        }

        return true;
    };

    //
    // eg [F:X]^PT,[F:Y]^SPT would enable us to remove [F:X,Y .....]^PT
    /**
     * Did we just add a simple segment from the UI? If so, check if we can now remove a composite that is a subset
     * of a combination of bricks of this type. Need to consider brick modifier too.
     *
     * eg [F:X]^PT,[F:Y]^SPT would enable us to remove [F:X,Y .....]^PT
     *    [F:X]^SP,[F:Y]^SPT would enable us to remove [F:X,Y .....]^SP
     *    [F:X]^SP,[F:Y]^SPT would enable us to remove [(F:X,Y)^SP .....]^S
     *
     * No need to execute when building the bricks from DB values on page load as we can assume this was already
     * executed when the user created the audience, and no legacy data contains a combo of simple and composite
     * segments.
     *
     * @param {Selectize}  selectize
     * @param {Brick[]}    bricks
     * @param {string}     entityType
     */
    this.onAddSetSimpleSegmentsViaUi = function (selectize, bricks, entityType){
        // TODO examine combinations of simple bricks to see if we can remove any composite bricks
        // TODO Rather than build each time, probably get the BrickManager to maintain an internal set of logical 1-D (simple) bricks that can have multiple values per dimensions.
        // TODO need to mutate this set when we add/remove a simple brick.
        // TODO we can then O(N^2) this list against all bricks to see if one of the all bricks is
        // 1. a composite, and
        // 2. contained by the simple brick
        // If so, we can them remove the composite brick
        // self.removeBrick(selectize, bricks, bricks[i]);

        // TODO temp implementation for POC. This assumes that composite bricks have 'self' dim + brick modifiers
        var logical1dBrickWithMultiValues = null; // TODO in reality this needs to be a set, probably indexed by modifier bitmask
        for(var i = 0; i < bricks.length; ++i){
            var brick = bricks[i];
            if(brick.isSimple() && entityType === brick.dimensions[0].entityType && -1 !== brick.modifiers.indexOf('self')){
                // First one encountered? TODO needs to consider brick modifiers
                if(!logical1dBrickWithMultiValues){
                    logical1dBrickWithMultiValues = new Brick(brick.segments[0], {}, {});
                    for(var j = 0; j < brick.segments.length; ++j){
                        if(!brick.segments.hasOwnProperty(j)){
                            continue;
                        }
                        var nextSegment = brick.segments[j];
                        if(j === 0){
                            continue;
                        }
                        if(-1 === logical1dBrickWithMultiValues.modifiers.indexOf(nextSegment.modifier)){
                            logical1dBrickWithMultiValues.modifiers.push(nextSegment.modifier);
                        }
                    }
                } else {
                    // Add values to logical brick
                    var soleSegment   = brick.segments[0];
                    var soleDimension = logical1dBrickWithMultiValues.dimensions[0];
                    if(-1 === soleDimension.entityValues.indexOf(soleSegment.entityValue)){
                        soleDimension.entityValues.push(soleSegment.entityValue);
                    }
                }
            }
        }

        // Did we not find any simple bricks with 'self' modifier for this entity type?
        if(!logical1dBrickWithMultiValues){
            // Since for now all composite segments have a 'self' brick modifier, we can conclude that no combination
            // of simple bricks is a superset of a composite brick.
            return;
        }

        var bricksToRemove = [];
        // Now that we have built this logical 1D brick, see if it logically contains any composites
        for(var k = 0; k < bricks.length; ++k){
            brick = bricks[k];
            if(brick.isSimple()){
                continue;
            }
            if(logical1dBrickWithMultiValues.containsBrick(brick)){
                // Can't remove them now because that would mutate the set we are iterating (concurrent mod error)
                bricksToRemove.push(brick);
            }
        }

        for(var l = 0; l < bricksToRemove.length; ++l){
            this.removeBrick(selectize, bricks, bricksToRemove[l]);
        }
    };

    /**
     * Called when we remove an item from audienceSelectize. (either because we clicked X icon on audienceSelectize item, or programmatically)
     * @param {string} key
     * @param {jQuery} $item - Wrapper of audienceSelectize item
     */
    this.onRemoveSegment = function(key, $item){
        // TODO, find the brick that contains this segment, explode it, call brick changed on each brick
    };

    /**
     * Called when a brick changes so that we can check whether it can now be aggregated with another brick
     * @param {Selectize} selectize
     * @param {Brick[]} bricks
     * @param {Brick} changedBrick
     */
    var onBrickChanged = function (selectize, bricks, changedBrick) {
        for(var i = 0; i < bricks.length; ++i){
            if (JSON.stringify(changedBrick) === JSON.stringify(bricks[i])) {
                continue;
            }
            if(changedBrick.containsBrick(bricks[i])){
                // eg you have a brick [R:A ∩ C:X] then add new brick [R:A]. The former is a subset of the latter.
                self.removeBrick(selectize, bricks, bricks[i]);
            }
            else if(changedBrick.canAbsorbBrick(bricks[i])){
                changedBrick.absorbBrick(bricks[i]);
                bricks.splice(i, 1);
                onBrickChanged(selectize, bricks, changedBrick);
                break;
            }
            // TODO is this required? Do we need to check both ways?
            // if(bricks[i].canAbsorbBrick(changedBrick)){
            //     console.log('existing brick can absorb changed brick');
            //     // bricks[i].absorbBrick(changedBrick);
            //     // bricks.splice(i, 1);
            //     // onBrickChanged(bricks, bricks[i]);
            //     break;
            // }
        }
    };

    /**
     * Removes a Brick from Vue's data model, and the Brick's segments from Selectize
     * @param {Selectize} selectize
     * @param {Brick[]} bricks
     * @param {Brick} brick
     */
    this.removeBrick = function(selectize, bricks, brick){
        for(var j = 0; j < brick.segments.length; ++j){
            if(brick.segments.hasOwnProperty(j)){
                selectize.removeItem(codec.encode(brick.segments[j]));
            }
        }
        bricks.splice(bricks.indexOf(brick), 1);
    };

    /**
     * When it's possible to add a Brick to multiple Bricks, this method reliably returns the preferred one.
     * @param {Brick[]} candidateBricks
     * @param {Brick} guestBrick
     * @return Brick
     */
    var computeOptimalHostBrick = function(candidateBricks, guestBrick){
        //console.log('TODO implement optimal brick algorithm');
        // TODO this is wrong, just a hack for now
        return candidateBricks[0];
    }
    // var sortBricks = function(brickArray)
}
