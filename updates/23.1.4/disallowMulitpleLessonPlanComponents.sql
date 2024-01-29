# Disallow multiple lesson plan components on both dashboard and homepage.
UPDATE component_definition SET multiple='false' WHERE name='Lesson Plans';
