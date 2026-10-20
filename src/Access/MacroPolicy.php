<?php

namespace Ernestdefoe\Cuecard\Access;

use Ernestdefoe\Cuecard\Macro;
use Ernestdefoe\Cuecard\Permissions;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class MacroPolicy extends AbstractPolicy
{
    /** Your own macro while you may save macros; a global one while you may manage them. */
    public function edit(User $actor, Macro $macro): string
    {
        if ($macro->isGlobal()) {
            return $actor->hasPermission(Permissions::MANAGE_GLOBAL) ? $this->allow() : $this->deny();
        }

        return $macro->user_id === $actor->id && $actor->hasPermission(Permissions::SAVE_PERSONAL)
            ? $this->allow()
            : $this->deny();
    }

    public function delete(User $actor, Macro $macro): string
    {
        return $this->edit($actor, $macro);
    }
}
