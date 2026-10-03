<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

/*
 * Who may apply a staff-only prefix such as "Official". Moderators by default
 * (admins can do everything anyway); an owner can widen it in Permissions.
 */
return Migration::addPermissions([
    'rubric.useStaffPrefixes' => Group::MODERATOR_ID,
]);
