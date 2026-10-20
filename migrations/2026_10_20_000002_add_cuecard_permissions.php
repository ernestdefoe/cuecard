<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'ernestdefoe-cuecard.use' => Group::MODERATOR_ID,
    'ernestdefoe-cuecard.savePersonal' => Group::MODERATOR_ID,
]);
