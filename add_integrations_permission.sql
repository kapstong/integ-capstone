-- Add 'settings.edit' permission for accessing integrations page
-- Run this SQL script to grant yourself access to /admin/integrations.php

-- Step 1: Check if 'settings.edit' permission exists, if not create it
INSERT IGNORE INTO permissions (name, description, module)
VALUES ('settings.edit', 'Edit system settings and manage integrations', 'settings');

-- Step 2: Get the permission ID
SET @permission_id = (SELECT id FROM permissions WHERE name = 'settings.edit');

-- Step 3: Get your user's role ID (assuming you're user ID 1 - admin)
-- If you're not user ID 1, change the number below to your user ID
SET @user_id = 1; -- CHANGE THIS TO YOUR USER ID IF DIFFERENT
SET @role_id = (SELECT role_id FROM user_roles WHERE user_id = @user_id LIMIT 1);

-- Step 4: Grant the permission to your role
INSERT IGNORE INTO role_permissions (role_id, permission_id, assigned_by)
SELECT @role_id, @permission_id, @user_id
WHERE @role_id IS NOT NULL AND @permission_id IS NOT NULL;

-- Step 5: Verify the permission was added
SELECT
    u.id as user_id,
    u.username,
    r.name as role_name,
    p.name as permission_name,
    p.description
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
JOIN role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE u.id = @user_id AND p.name = 'settings.edit';

-- If you see a result above, the permission was added successfully!
-- If you don't see a result, you may need to check your user_id or role assignment.
