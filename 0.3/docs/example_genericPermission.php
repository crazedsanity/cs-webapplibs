<?php

//instantiate the permission object with a database & the proper UID.
$permObj = new cs_genericUserPermission($page->db, $_SESSION['uid']);	


//Creating Permissions/Groups
{
	//create a group.
	$permObj->create_group("blogs");
	
	//define default permissions for the group.
	//NOTE::: the "false" entries can be technically excluded, as the default is false.
	$perms = array(
		'view'				=> true,
		'create'			=> true,
		'edit'				=> true,
		'set_timestamp'		=> true,
		'update_timestamp'	=> false,	
		'delete_entry'		=> false,
		'set_draft'			=> true,
		'update_to_draft'	=> false
	);
	$permObj->set_group_perms("blogs", $perms);
	
	//set specific permissions for a user.
	//NOTE::: if the group or permission doesn't exist, this will throw an exception.
	$permObj->set_user_perm("blogs", "update_timestamp");
}


//Checking Permissions/Groups
{
	//get a list of permissions...
	$perms = $permObj->get_user_permissions();
	
	//check if the user is part of a group... in this case, the "blogs" group.
	$isGroupMember = $permObj->check_group_membership("blogs");
	
	//Pull all available permissions for a group... again, the "blogs" group.
	$allBlogsPerms = $permObj->get_group_perms("blogs");
	
	//check permissions for a specific "group" (or "object")... in this case, "can the user create a blog?"
	$hasPermission = $permObj->check_permission("blogs", "create");
	
	
	//a more advanced check, involving membership in multiple cascading groups (unimplemented)... "can the user administratively view blogs?"
	//NOTE::: the code in this method would have to allow an unlimited number of arguments (minimum 2), where the last one is the permission name.
	#$permObj->check_cascading_permission("admin", "blogs", "view");
}


?>
