/**
 * Unit Tests for Business Logic classes
 *
 * Usage: 'new TestSuite().run();'
 *
 * @constructor
 */
function TestSuite() {
    // Unit Tests go here.

    // =========== Segment Tests ============== //

    // Test that constructor throws if we don't call with 'new' keyword
    this.test_throws_no_new_segment = function () {
        Segment("folder", "3", "self", null);
    };

    // Test invalid entity type is detected
    this.test_throws_new_segment_invalid_type = function () {
        new Segment(null, "3", "self", null);
    };

    // Test invalid entity value is detected
    this.test_throws_new_segment_invalid_value = function () {
        new Segment("folder", 3, "self", null);
    };

    // Test invalid entity modifier is detected
    this.test_throws_new_segment_invalid_modifier = function () {
        new Segment("folder", "3", "invalid_mod", null);
    };

    // Test invalid children is detected
    this.test_throws_new_segment_invalid_children = function () {
        new Segment("folder", "3", "self", []);
    };
    // Test invalid value is detected
    this.test_throws_new_composite_segment_invalid_value = function () {
        new Segment("composite", "3", "self", []);
    };
    // Test invalid children is detected
    this.test_throws_new_composite_segment_invalid_children_type = function () {
        new Segment("composite", null, "self", null);
    };
    this.test_throws_new_composite_segment_invalid_children_count = function () {
        new Segment("composite", null, "self", [new Segment("folder", "3", "self", null)]);
    };
    this.test_throws_new_composite_segment_invalid_children_count = function () {
        var ss1 = new Segment("folder", "3", "self", null);
        new Segment("composite", null, "self", [ss1, ss1]);
    };
    this.test_throws_new_composite_segment_nested_composite = function () {
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]);

        new Segment("composite", null, "self", [ss1, cs1]);
    };

    // Test equality of simple segments
    this.test_segment_simple_equality = function () {
        var ss1 = new Segment("folder", "3", "self", null);
        var ss2 = new Segment("folder", "3", "self", null);
        var ss3 = new Segment("folder", "3", "self", null);
        var ss4 = new Segment("role", "3", "self", null);
        var ss5 = new Segment("folder", "4", "self", null);
        var ss6 = new Segment("folder", "3", "parents_of", null);

        assertEquals(true, ss1.equals(ss2));
        assertEquals(true, ss1.equals(ss3));
        assertEquals(false, ss1.equals(ss4));
        assertEquals(false, ss1.equals(ss5));
        assertEquals(false, ss1.equals(ss6));
    };

    // Test equality of composite segments
    this.test_segment_composite_equality = function () {
        var ss1 = new Segment("folder", "X", "self", null);
        var ss2 = new Segment("folder", "Y", "self", null);
        // var ss3 = new Segment('folder', '3', 'self', null);
        // var ss4 = new Segment('role', '3', 'self', null);
        // var ss5 = new Segment('folder', '4', 'self', null);
        // var ss6 = new Segment('folder', '3', 'parents_of', null);

        // Simple does not equal composite
        var cs1 = new Segment("composite", null, "self", [ss1, ss2]);
        assertEquals(false, ss1.equals(cs1));

        // Composites do not care about child permutation
        var cs2 = new Segment("composite", null, "self", [ss2, ss1]);
        assertEquals(true, cs2.equals(cs1));
    };

    // =========== Codec Tests ============== //

    // Test that constructor throws if we don't call with 'new' keyword
    this.test_throws_no_new_codec = function () {
        Codec();
    };

    // Test we can decode a valid simple segment
    this.test_decode_simple_self = function () {
        var segment = new Codec().decode("folder:ef:self");
        assertEquals("folder", segment.getEntityType());
        assertEquals("ef", segment.getEntityValue());
        assertEquals("self", segment.getModifier());
        assertEquals(null, segment.getChildren());
    };

    // Test we can decode a valid simple segment with modifier
    this.test_decode_simple_modifier = function () {
        var segment = new Codec().decode("folder:ef:parents_of");
        assertEquals("parents_of", segment.getModifier());
    };

    // Test we can decode a valid composite segment
    this.test_decode_composite = function () {
        var segment = new Codec().decode("composite;parents_of;folder:ef:self.role:bc:self");
        assertEquals("composite", segment.getEntityType());
        assertEquals(null, segment.getEntityValue());
        assertEquals("parents_of", segment.getModifier());

        var child1 = segment.getChildren()[0];
        assertEquals("folder", child1.getEntityType());
        assertEquals("ef", child1.getEntityValue());
        assertEquals("self", child1.getModifier());
        assertEquals(null, child1.getChildren());

        var child2 = segment.getChildren()[1];
        assertEquals("role", child2.getEntityType());
        assertEquals("bc", child2.getEntityValue());
        assertEquals("self", child2.getModifier());
        assertEquals(null, child2.getChildren());
    };

    // Test we can decode a valid composite segment
    this.test_decode_composite = function () {
        var segment = new Codec().decode("composite;self;folder:ef:parents_of.role:bc:self");
        assertEquals("self", segment.getModifier());
        assertEquals("parents_of", segment.getChildren()[0].getModifier());
    };

    // Test that we cannot construct a composite segment with nested non-self modifiers
    this.test_throws_nested_non_self_modifiers = function () {
        new Codec().decode("composite;parents_of;folder:ef:parents_of.role:bc:self");
    };

    // Test on the fly simplification of encoded composites that are logically a simple segment
    this.test_simplify_encoded_overlapping_composite = function () {
        var codec = new Codec();

        // Should simplify
        assertEquals(false, codec.decode("composite;self;folder:X:self.folder:X:self").isComposite());

        // Should not simplify. Different type, value, modifier respectively
        assertEquals(true, codec.decode("composite;self;folder:X:self.role:X:self").isComposite());
        assertEquals(true, codec.decode("composite;self;folder:X:self.folder:Y:self").isComposite());
        assertEquals(true, codec.decode("composite;self;folder:X:self.folder:X:teachers_of").isComposite());
    };

    // Test that encoding of composite with different child permutations generate same identifier/key
    this.test_unique_segment_key = function () {
        var codec = new Codec();

        var ss1 = new Segment("folder", "3", "self", null);
        var ss2 = new Segment("role", "3", "self", null);
        var ss3 = new Segment("folder", "4", "self", null);
        var ss4 = new Segment("folder", "4", "parents_of", null);

        // [X:1 ∩ Y:2] and [Y:2 ∩ X:1] - ie entityType ordering
        var cs1 = new Segment("composite", null, "self", [ss1, ss2]);
        var cs2 = new Segment("composite", null, "self", [ss2, ss1]);
        assertEquals(codec.encode(cs1), codec.encode(cs2));

        // [X:1 ∩ X:2] and [X:2 ∩ X:1] - ie entityValue ordering
        var cs3 = new Segment("composite", null, "self", [ss1, ss3]);
        var cs4 = new Segment("composite", null, "self", [ss3, ss1]);
        assertEquals(codec.encode(cs3), codec.encode(cs4));

        // [X:1^S ∩ X:1^P] and [X:1^P ∩ X:1^S] - ie entityModifier ordering
        var cs5 = new Segment("composite", null, "self", [ss3, ss4]);
        var cs6 = new Segment("composite", null, "self", [ss4, ss3]);
        assertEquals(codec.encode(cs5), codec.encode(cs6));
    };

    // Test we can encode a simple segment
    this.test_encode_simple = function () {
        var segment = new Segment("folder", "ef", "parents_of", null);
        assertEquals("folder", segment.getEntityType());
        assertEquals("ef", segment.getEntityValue());
        assertEquals("parents_of", segment.getModifier());
        assertEquals(null, segment.getChildren());
    };

    // =========== Dimension Tests ============== //
    this.test_dimension_dof = function () {
        var d1 = new Dimension("folder", "X", "self"),
            d2 = new Dimension("folder", "X", "self"),
            d3 = new Dimension("folder", "X", "parents_of"),
            d4 = new Dimension("folder", "Y", "self"),
            d5 = new Dimension("folder", "Y", "parents_of"),
            d6 = new Dimension("campus", "X", "self"),
            d7 = new Dimension("campus", "X", "parents_of"),
            d8 = new Dimension("campus", "Y", "self"),
            d9 = new Dimension("campus", "Y", "parents_of");

        assertEquals(0, d1.computeDOF(d1));
        assertEquals(0, d1.computeDOF(d2));
        assertEquals(2, d1.computeDOF(d3));
        assertEquals(1, d1.computeDOF(d4));
        assertEquals(2, d1.computeDOF(d5));
        assertEquals(2, d1.computeDOF(d6));
        assertEquals(2, d1.computeDOF(d7));
        assertEquals(2, d1.computeDOF(d8));
        assertEquals(2, d1.computeDOF(d9));
    };

    // =========== Brick Tests ============== //

    this.test_brick_simple_contains_segment_composite = function () {
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "X", "parents_of", null),
            ss3 = new Segment("folder", "X", "teachers_of", null),
            ss4 = new Segment("campus", "X", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]), // [(F:X)^S ∩ (F:X)^P]^S
            cs2 = new Segment("composite", null, "self", [ss1, ss3]), // [(F:X)^S ∩ (F:X)^P]^S
            cs3 = new Segment("composite", null, "self", [ss1, ss4]), // [(F:X)^S ∩ (C:X)^P]^S
            cs4 = new Segment("composite", null, "self", [ss2, ss3]), // [(F:X)^P ∩ (F:X)^T]^S
            cs5 = new Segment("composite", null, "self", [ss2, ss4]), // [(F:X)^P ∩ (C:X)^P]^S
            cs6 = new Segment("composite", null, "self", [ss3, ss4]), // [(F:X)^T ∩ (C:X)^P]^S
            sBrick1 = new Brick(ss1, {}, {});

        assertEquals(true, sBrick1.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, sBrick1.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(true, sBrick1.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(false, sBrick1.containsBrick(new Brick(cs4, {}, {})));
        assertEquals(false, sBrick1.containsBrick(new Brick(cs5, {}, {})));
        assertEquals(false, sBrick1.containsBrick(new Brick(cs6, {}, {})));
    };

    this.test_brick_composites_contains_segment_composite = function () {
        var ss1 = new Segment("folder", "A", "self", null),
            ss2 = new Segment("folder", "B", "self", null),
            ss3 = new Segment("folder", "X", "self", null),
            ss4 = new Segment("folder", "Y", "self", null),
            ss5 = new Segment("folder", "Z", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss3]),
            cs2 = new Segment("composite", null, "self", [ss1, ss4]),
            cs3 = new Segment("composite", null, "self", [ss2, ss3]),
            cs4 = new Segment("composite", null, "self", [ss2, ss4]),
            brick1 = new Brick(cs1, {}, {}),
            brick2 = new Brick(cs3, {}, {});
        assertEquals(true, brick1.absorbBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick2.absorbBrick(new Brick(cs4, {}, {})));
        assertEquals(true, brick2.absorbBrick(brick1));

        var cs5 = new Segment("composite", null, "self", [ss1, ss2, ss5]),
            brick3 = new Brick(cs5, {}, {});

        assertEquals(false, brick2.containsBrick(brick3));
    };

    // Tests whether a simple brick logically contains a segment
    this.test_brick_simple_contains_segment = function () {
        var segment = new Segment("folder", "X", "parents_of", null),
            brick = new Brick(segment, {}, {});
        assertEquals(true, brick.containsBrick(brick));

        // Similar segments
        assertEquals(false, brick.containsBrick(new Brick(new Segment("role", "X", "parents_of", null), {}, {})));
        assertEquals(false, brick.containsBrick(new Brick(new Segment("folder", "Y", "parents_of", null), {}, {})));
        assertEquals(false, brick.containsBrick(new Brick(new Segment("folder", "X", "teachers_of", null), {}, {})));
        assertEquals(false, brick.containsBrick(new Brick(new Segment("folder", "X", "self", null), {}, {})));
    };

    // Test we can add a segment to a simple brick
    this.test_brick_simple_canAddSegment = function () {
        var segment = new Segment("folder", "ef", "parents_of", null),
            brick = new Brick(segment, {}, {});
        assertEquals(false, brick.canAbsorbBrick(brick)); // Already contains this segment

        assertEquals(false, brick.canAbsorbBrick(brick));

        // 1 DOF difference
        assertEquals(true, brick.canAbsorbBrick(new Brick(new Segment("folder", "ef", "self", null), {}, {})));
        assertEquals(true, brick.canAbsorbBrick(new Brick(new Segment("folder", "ef", "self", null), {}, {})));
        assertEquals(true, brick.canAbsorbBrick(new Brick(new Segment("folder", "ef", "teachers_of", null), {}, {})));

        // Different entity type
        assertEquals(false, brick.canAbsorbBrick(new Brick(new Segment("campus", "ef", "parents_of", null), {}, {})));

        // Different entity value
        assertEquals(false, brick.canAbsorbBrick(new Brick(new Segment("folder", "X", "parents_of", null), {}, {})));

        // Different modifier
        assertEquals(true, brick.canAbsorbBrick(new Brick(new Segment("folder", "ef", "self", null), {}, {})));
    };

    // Tests whether a composite brick logically contains a composite segment
    this.test_brick_composite_contains_segment = function () {
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "Y", "self", null),
            cs1 = new Segment("composite", null, "parents_of", [ss1, ss2]),
            cs2 = new Segment("composite", null, "parents_of", [ss2, ss1]),
            brick = new Brick(cs1, {}, {});
        assertEquals(true, brick.containsBrick(new Brick(cs2, {}, {})));
    };

    // Tests combining composite segments that differ by brick modifier dimension
    this.test_brick_composite_build_brick_modifier = function () {
        // Create Brick from a composite segment
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]),
            brick = new Brick(cs1, {}, {});

        // Add a composite segment with different modifier
        var cs2 = new Segment("composite", null, "parents_of", [ss1, ss2]);
        assertEquals(true, brick.canAbsorbBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick.absorbBrick(new Brick(cs2, {}, {})));

        // Check the brick contains both segments
        assertEquals(true, brick.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, brick.containsBrick(new Brick(cs2, {}, {})));
    };

    // Tests combining composite segments that differ by a single dimension value
    this.test_brick_composite_build_dimension_value = function () {
        // Create Brick from a composite segment
        var ss1 = new Segment("folder", "A", "self", null),
            ss2 = new Segment("folder", "B", "self", null),
            ss3 = new Segment("folder", "C", "self", null),
            ss4 = new Segment("folder", "X", "self", null),
            ss5 = new Segment("folder", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2, ss3]), // F:A ∩ F:B ∩ F:C
            cs2 = new Segment("composite", null, "self", [ss1, ss2, ss4]), // F:A ∩ F:B ∩ F:X
            cs3 = new Segment("composite", null, "self", [ss1, ss2, ss5]),
            brick = new Brick(cs1, {}, {});

        // Add a composite segment with different modifier
        assertEquals(true, brick.canAbsorbBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick.absorbBrick(new Brick(cs2, {}, {}))); // F:A ∩ F:B ∩ F:C,X

        var d1 = brick.getDimensions()[0],
            d2 = brick.getDimensions()[1],
            d3 = brick.getDimensions()[2];

        assertEquals(0, d1.getEntityValues().indexOf("A"));
        assertEquals(0, d2.getEntityValues().indexOf("B"));
        assertEquals(0, d3.getEntityValues().indexOf("C"));
        assertEquals(1, d3.getEntityValues().indexOf("X"));

        // Check the brick contains both added segments, but not another random one
        assertEquals(true, brick.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, brick.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(false, brick.containsBrick(new Brick(cs3, {}, {})));
    };

    // Tests combining composite and simple brick
    this.test_brick_absorb_different_brick_type = function () {
        // Create a simple and composite brick
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]),
            sBrick1 = new Brick(ss1, {}, {}),
            cBrick2 = new Brick(cs1, {}, {});

        // composite is more specific form of simple
        assertEquals(true, sBrick1.containsBrick(cBrick2));
        assertEquals(false, cBrick2.containsBrick(sBrick1));
    };

    // Test combining simple bricks
    this.test_brick_simple_absorb = function () {
        var brick1 = new Brick(new Segment("folder", "A", "parents_of", null), {}, {}),
            brick2 = new Brick(new Segment("folder", "A", "parents_of", null), {}, {}),
            brick3 = new Brick(new Segment("campus", "A", "parents_of", null), {}, {}),
            brick4 = new Brick(new Segment("folder", "B", "parents_of", null), {}, {}),
            brick5 = new Brick(new Segment("folder", "A", "teachers_of", null), {}, {});

        // ContainsBrick
        assertEquals(true, brick1.containsBrick(brick1));
        assertEquals(true, brick1.containsBrick(brick2));
        assertEquals(false, brick1.containsBrick(brick3));
        assertEquals(false, brick1.containsBrick(brick4));
        assertEquals(false, brick1.containsBrick(brick5));

        // CanAbsorbBrick
        assertEquals(false, brick1.canAbsorbBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick2));
        assertEquals(false, brick1.canAbsorbBrick(brick3));
        assertEquals(false, brick1.canAbsorbBrick(brick4));
        assertEquals(true, brick1.canAbsorbBrick(brick5));

        // absorbBrick
        assertEquals(false, brick1.absorbBrick(brick1));
        assertEquals(false, brick1.absorbBrick(brick2));
        assertEquals(false, brick1.absorbBrick(brick3));
        assertEquals(false, brick1.absorbBrick(brick4));
        assertEquals(true, brick1.absorbBrick(brick5));

        // Contains the original and absorbed Brick
        assertEquals(true, brick1.containsBrick(brick1));
        assertEquals(true, brick1.containsBrick(brick2));
        assertEquals(false, brick1.containsBrick(brick3));
        assertEquals(false, brick1.containsBrick(brick4));
        assertEquals(true, brick1.containsBrick(brick5));
    };

    // Test the case when a single brick dimension is a superset of both a composite segment's children
    this.test_brick_composite_absorb_similar_segments = function () {
        var ss1 = new Segment("folder", "X", "self", null),
            ss2 = new Segment("folder", "Y", "self", null),
            ss3 = new Segment("folder", "Z", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss3]), // [F:X ∩ F:Z]
            cs2 = new Segment("composite", null, "self", [ss2, ss3]), // [F:Y ∩ F:Z]
            brick = new Brick(cs1, {}, {});
        brick.absorbBrick(new Brick(cs2, {}, {})); // [F:X,Y ∩ F:Z]

        var cs3 = new Segment("composite", null, "self", [ss1, ss2]); // [F:X ∩ F:Y]

        // both segment dimensions are a subset of the first brick dimension, but 1 + 1 = 2 DOF
        assertEquals(false, brick.canAbsorbBrick(new Brick(cs3, {}, {})));
    };

    // Test the case when a composite brick of different entity types, and segment with repeated entity types
    this.test_brick_composite_absorb_self_intersecting_segment = function () {
        var ss1 = new Segment("campus", "X", "self", null),
            ss2 = new Segment("role", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]),
            brick1 = new Brick(cs1, {}, {}),
            cs2 = new Segment("composite", null, "self", [ss2, ss1]),
            brick2 = new Brick(cs2, {}, {}),
            ss3 = new Segment("campus", "Y", "self", null),
            cs3 = new Segment("composite", null, "self", [ss3, ss1]),
            brick3 = new Brick(cs3, {}, {}),
            cs4 = new Segment("composite", null, "self", [ss1, ss3]),
            brick4 = new Brick(cs4, {}, {});

        // brick contains simple segment
        assertEquals(false, brick1.containsBrick(new Brick(ss1, {}, {})));
        assertEquals(false, brick1.containsBrick(new Brick(ss2, {}, {})));
        assertEquals(false, brick1.containsBrick(new Brick(ss3, {}, {})));

        // brick contain composite segment
        assertEquals(true, brick1.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, brick1.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(false, brick1.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(false, brick1.containsBrick(new Brick(cs4, {}, {})));
        assertEquals(true, brick2.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, brick2.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(false, brick2.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(false, brick2.containsBrick(new Brick(cs4, {}, {})));
        assertEquals(false, brick3.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(false, brick3.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick3.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(true, brick3.containsBrick(new Brick(cs4, {}, {})));
        assertEquals(false, brick4.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(false, brick4.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick4.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(true, brick4.containsBrick(new Brick(cs4, {}, {})));

        // brick can absorb brick
        assertEquals(false, brick1.canAbsorbBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick2));
        assertEquals(false, brick1.canAbsorbBrick(brick3));
        assertEquals(false, brick1.canAbsorbBrick(brick4));
        assertEquals(false, brick2.canAbsorbBrick(brick1));
        assertEquals(false, brick2.canAbsorbBrick(brick2));
        assertEquals(false, brick2.canAbsorbBrick(brick3));
        assertEquals(false, brick2.canAbsorbBrick(brick4));
        assertEquals(false, brick3.canAbsorbBrick(brick1)); // here
        assertEquals(false, brick3.canAbsorbBrick(brick2));
        assertEquals(false, brick3.canAbsorbBrick(brick3));
        assertEquals(false, brick3.canAbsorbBrick(brick4));
        assertEquals(false, brick1.canAbsorbBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick2));
        assertEquals(false, brick1.canAbsorbBrick(brick3));
        assertEquals(false, brick1.canAbsorbBrick(brick4));
    };

    // Test a composite brick can absorb another composite brick
    this.test_brick_composite_absorb = function () {
        var ss1 = new Segment("folder", "A", "self", null),
            ss2 = new Segment("folder", "B", "self", null),
            ss3 = new Segment("folder", "C", "self", null),
            ss4 = new Segment("folder", "X", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2, ss3]),
            cs2 = new Segment("composite", null, "self", [ss1, ss2, ss4]),
            brick1 = new Brick(cs1, {}, {}),
            brick2 = new Brick(cs2, {}, {});

        // ContainsBrick
        assertEquals(true, brick1.containsBrick(brick1));
        assertEquals(false, brick1.containsBrick(brick2));

        // CanAbsorbBrick
        assertEquals(false, brick1.canAbsorbBrick(brick1));
        assertEquals(true, brick1.canAbsorbBrick(brick2));

        // absorbBrick
        assertEquals(false, brick1.absorbBrick(brick1));
        assertEquals(true, brick1.absorbBrick(brick2));

        // Contains the original and absorbed Brick
        assertEquals(true, brick1.containsBrick(brick1));
        assertEquals(true, brick1.containsBrick(brick2));
    };

    this.test_brick_composite_mutations = function () {
        // Build the main brick we will compare other brick variants against
        var ss1 = new Segment("folder", "A", "self", null),
            ss2 = new Segment("folder", "X", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2]),
            brick1 = new Brick(cs1, {}, {}); // [(F:A)^S ∩ (F:X)^S]^S

        // Other brick is logically equivalent
        var cs2 = new Segment("composite", null, "self", [ss2, ss1]),
            brick2 = new Brick(cs2, {}, {});
        assertEquals(true, brick1.containsBrick(brick2));
        assertEquals(true, brick2.containsBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick2));
        assertEquals(false, brick2.canAbsorbBrick(brick1));

        // Other brick has different modifier
        var cs3 = new Segment("composite", null, "parents_of", [ss2, ss1]),
            brick3 = new Brick(cs3, {}, {});
        assertEquals(false, brick1.containsBrick(brick3));
        assertEquals(false, brick3.containsBrick(brick1));
        assertEquals(true, brick1.canAbsorbBrick(brick3));
        assertEquals(true, brick3.canAbsorbBrick(brick1));

        // Other brick has a dimension with a different modifier
        var ss3 = new Segment("folder", "X", "parents_of", null),
            cs4 = new Segment("composite", null, "self", [ss1, ss3]),
            brick4 = new Brick(cs4, {}, {}); // [(F:A)^S ∩ (F:X)^P]^S
        assertEquals(false, brick1.containsBrick(brick4));
        assertEquals(false, brick4.containsBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick4));
        assertEquals(false, brick4.canAbsorbBrick(brick1));

        // Other brick has different brick modifier, and one dimension has different value
        var ss4 = new Segment("folder", "Y", "self", null),
            cs5 = new Segment("composite", null, "parents_of", [ss1, ss4]),
            brick5 = new Brick(cs5, {}, {}); // [(F:A)^S ∩ (F:Y)^S]^P
        assertEquals(false, brick1.containsBrick(brick5));
        assertEquals(false, brick5.containsBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick5));
        assertEquals(false, brick5.canAbsorbBrick(brick1));

        // Other brick has multiple dimensions with different values
        var ss5 = new Segment("folder", "B", "self", null),
            cs6 = new Segment("composite", null, "self", [ss5, ss4]),
            brick6 = new Brick(cs6, {}, {});
        assertEquals(false, brick1.containsBrick(brick6));
        assertEquals(false, brick6.containsBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick6));
        assertEquals(false, brick6.canAbsorbBrick(brick1));

        // Other brick has dimensions with different entityType
        var ss6 = new Segment("campus", "X", "self", null),
            cs7 = new Segment("composite", null, "self", [ss1, ss6]),
            brick7 = new Brick(cs7, {}, {}); // [(F:A)^S ∩ (C:X)^S]^S
        assertEquals(false, brick1.containsBrick(brick7));
        assertEquals(false, brick7.containsBrick(brick1));
        assertEquals(false, brick1.canAbsorbBrick(brick7));
        assertEquals(false, brick7.canAbsorbBrick(brick1)); /// here

        // Other brick has dimension with different value
        var cs8 = new Segment("composite", null, "self", [ss1, ss4]),
            brick8 = new Brick(cs8, {}, {});
        assertEquals(false, brick1.containsBrick(brick8));
        assertEquals(false, brick8.containsBrick(brick1));
        assertEquals(true, brick1.canAbsorbBrick(brick8));
        assertEquals(true, brick8.canAbsorbBrick(brick1));

        // Other brick has dimension with extra values.
        var brick9 = new Brick(cs1, {}, {}); // [(F:A)^S ∩ (F:X)^S]^S
        brick9.absorbBrick(new Brick(cs8, {}, {})); // [(F:A)^S ∩ (F:X,Y)^S]^S
        assertEquals(false, brick1.containsBrick(brick9));
        assertEquals(true, brick9.containsBrick(brick1));
        assertEquals(true, brick1.canAbsorbBrick(brick9));
        assertEquals(false, brick9.canAbsorbBrick(brick1));

        // Other brick has dimension with different values.
        var ss7 = new Segment("folder", "Z", "self", null),
            cs9 = new Segment("composite", null, "self", [ss1, ss4]),
            cs10 = new Segment("composite", null, "self", [ss1, ss7]),
            brick10 = new Brick(cs9, {}, {});
        brick10.absorbBrick(new Brick(cs10, {}, {}));
        assertEquals(false, brick1.containsBrick(brick10));
        assertEquals(false, brick10.containsBrick(brick1));
        assertEquals(true, brick1.canAbsorbBrick(brick10));
        assertEquals(true, brick10.canAbsorbBrick(brick1));
    };

    // Test our common legacy data example
    this.test_brick_composite_absorb_common_legacy_data = function () {
        var ss1 = new Segment("campus", "A", "self", null),
            ss2 = new Segment("campus", "B", "self", null),
            ss3 = new Segment("role", "X", "self", null),
            ss4 = new Segment("role", "Y", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss3]), // [C:A ∩ R:X]
            cs2 = new Segment("composite", null, "self", [ss1, ss4]), // [C:A ∩ R:Y]
            cs3 = new Segment("composite", null, "self", [ss2, ss3]), // [C:B ∩ R:X]
            cs4 = new Segment("composite", null, "self", [ss2, ss4]); // [C:B ∩ R:Y]

        // first cs requires new brick
        var brick1 = new Brick(cs1, {}, {}); // [C:A ∩ R:X]

        // contains
        // assertEquals(true, brick1.containsBrick(new Brick(cs1, {}, {})));
        // assertEquals(false, brick1.containsBrick(new Brick(cs2, {}, {})));
        // assertEquals(false, brick1.containsBrick(new Brick(cs3, {}, {})));
        // assertEquals(false, brick1.containsBrick(new Brick(cs4, {}, {})));

        // can absorb?
        // assertEquals(false, brick1.canAbsorbBrick(new Brick(cs1, {}, {})));
        // assertEquals(true, brick1.canAbsorbBrick(new Brick(cs2, {}, {})));
        // assertEquals(true, brick1.canAbsorbBrick(new Brick(cs3, {}, {})));
        // assertEquals(false, brick1.canAbsorbBrick(new Brick(cs4, {}, {})));

        // Absorb
        assertEquals(true, brick1.absorbBrick(new Brick(cs2, {}, {}))); // [C:A ∩ R:X,Y]

        // contains
        // assertEquals(true, brick1.containsBrick(new Brick(cs1, {}, {})));
        // assertEquals(true, brick1.containsBrick(new Brick(cs2, {}, {})));
        // assertEquals(false, brick1.containsBrick(new Brick(cs3, {}, {})));
        // assertEquals(false, brick1.containsBrick(new Brick(cs4, {}, {})));

        // can absorb?
        assertEquals(false, brick1.canAbsorbBrick(new Brick(cs3, {}, {})));
        assertEquals(false, brick1.canAbsorbBrick(new Brick(cs4, {}, {})));
    };

    this.test_brick_explosion = function () {
        var ss1 = new Segment("folder", "A", "self", null),
            ss2 = new Segment("folder", "B", "self", null),
            ss3 = new Segment("campus", "A", "self", null),
            ss4 = new Segment("folder", "C", "self", null),
            cs1 = new Segment("composite", null, "self", [ss1, ss2, ss3]), // [(F:A)^S ∩ (F:B)^S ∩ (C:A)^S]^S
            cs2 = new Segment("composite", null, "self", [ss1, ss4, ss3]), // [(F:A)^S ∩ (F:C)^P ∩ (C:A)^S]^S
            cs3 = new Segment("composite", null, "parents_of", [ss1, ss2, ss3]), // [(F:A)^S ∩ (F:B)^S ∩ (C:A)^S]^P
            cs4 = new Segment("composite", null, "parents_of", [ss1, ss4, ss3]), // [(F:A)^S ∩ (F:C)^P ∩ (C:A)^S]^P
            brick1 = new Brick(cs1, {}, {}),
            brick2 = new Brick(cs2, {}, {}),
            brick3 = new Brick(cs3, {}, {});

        assertEquals(true, brick1.absorbBrick(brick2)); // [(F:A)^S ∩ (F:B,C)^S ∩ (C:A)^S]^S
        assertEquals(true, brick3.canAbsorbBrick(new Brick(cs4, {}, {}))); // [(F:A)^S ∩ (F:B,C)^S ∩ (C:A)^S]^P
        assertEquals(true, brick3.absorbBrick(new Brick(cs4, {}, {}))); // [(F:A)^S ∩ (F:B,C)^S ∩ (C:A)^S]^P
        assertEquals(true, brick1.absorbBrick(brick3)); // [(F:A)^S ∩ (F:B,C)^S ∩ (C:A)^S]^S,P

        assertEquals(true, brick1.containsBrick(new Brick(cs1, {}, {})));
        assertEquals(true, brick1.containsBrick(new Brick(cs2, {}, {})));
        assertEquals(true, brick1.containsBrick(new Brick(cs3, {}, {})));
        assertEquals(true, brick1.containsBrick(new Brick(cs4, {}, {})));

        var exploded = brick1.explode();
        assertEquals(4, exploded.length);
    };

    // Test that we can concoct an aggregated simple brick
    this.test_combo_simple_can_remove_composite = function () {
        var sS1 = new Segment("campus", "A", "self", null),
            sS2 = new Segment("campus", "B", "self", null),
            sS3 = new Segment("role", "X", "self", null),
            cS1 = new Segment("composite", null, "self", [sS1, sS3]),
            cS2 = new Segment("composite", null, "self", [sS2, sS3]),
            sBrick1 = new Brick(sS1, {}, {}), // [C:A]
            cBrick = new Brick(cS1, {}, {});
        cBrick.absorbBrick(new Brick(cS2, {}, {})); // [(C:A,B) (R:X)]

        // currently not valid via brick public methods
        sBrick1.dimensions[0].entityValues = ["A", "B"];

        assertEquals(true, sBrick1.containsBrick(cBrick));
    };

    /**
     * Public invocation
     */
    this.run = function () {
        var start = new Date().getTime();
        var countTests = 0,
            countFails = 0;
        for (var i in this) {
            if ("run" !== i && this.hasOwnProperty(i)) {
                if (0 === i.indexOf("test_throws")) {
                    countTests++;
                    // invalid test
                    var errorThrown = false;
                    try {
                        this[i]();
                    } catch (e) {
                        errorThrown = true;
                    }
                    if (!errorThrown) {
                        countFails++;
                        console.log("Error: Expected exception but none thrown:\n\tat " + i);
                    }
                } else if (0 === i.indexOf("test")) {
                    countTests++;
                    // normal test
                    try {
                        this[i]();
                    } catch (e) {
                        countFails++;
                        console.log(e);
                    }
                }
            }
        }
        var end = new Date().getTime();
        console.log("Count tests: " + countTests + ".");
        console.log("Count fails: " + countFails + ".");
        console.log("Duration: " + (end - start) + " ms");
    };

    /**
     * Helper to assert a variable's value
     * @param expected
     * @param actual
     */
    var assertEquals = function (expected, actual) {
        if (expected === actual) {
            return;
        }
        throw new Error(
            "Expected: '" + expected + "' (" + typeof expected + "), Actual: '" + actual + "' (" + typeof actual + ")",
        );
    };
}

$(function () {
    new TestSuite().run();
});
