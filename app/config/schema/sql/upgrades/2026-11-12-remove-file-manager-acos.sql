-- Run on a database from before commit "fix: remove the File Manager".
-- A fresh install doesn't need it. Running it a second time changes nothing.
--
-- The File Manager's code is gone, but its access control records (ACOs)
-- stay in the database: the permissions screen would keep listing a
-- "Filemanager" controller with ten actions, and Croogo's "Generate" only
-- ever adds records. This deletes the Filemanager node under "controllers",
-- its actions, and any permissions a role was given on them.
--
-- acos is a nested set: each node covers the numbers lft..rght, and its
-- children sit inside that range. Deleting a node with its children leaves
-- a gap, so every number after it moves down by the width of the gap.

SET @lft = NULL, @rght = NULL;

SELECT node.lft, node.rght INTO @lft, @rght
FROM acos node
JOIN acos root ON root.id = node.parent_id
WHERE node.alias = 'Filemanager' AND root.alias = 'controllers' AND root.parent_id IS NULL;

SET @width = @rght - @lft + 1;

DELETE permission FROM aros_acos permission
JOIN acos node ON node.id = permission.aco_id
WHERE node.lft BETWEEN @lft AND @rght;

DELETE FROM acos WHERE lft BETWEEN @lft AND @rght;

UPDATE acos SET lft = lft - @width WHERE lft > @rght;
UPDATE acos SET rght = rght - @width WHERE rght > @rght;
