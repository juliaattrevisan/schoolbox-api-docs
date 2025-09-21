-- Update AC9M3N02 curriculum node title to use Unicode fractions
UPDATE curriculum_node
SET name = 'recognise and represent unit fractions including ½, ⅓, ¼, ⅕ and ⅒ and their multiples in different ways; combine fractions with the same denominator to complete the whole'
WHERE code = 'AC9M3N02' AND name 'recognise and represent unit fractions including , , , and and their multiples in different ways; combine fractions with the same denominator to complete the whole';
