<?php

namespace Ernestdefoe\Cuecard;

final class Permissions
{
    /** Insert the global macros offered to your groups. */
    public const USE = 'ernestdefoe-cuecard.use';

    /** Save, edit and delete macros of your own. */
    public const SAVE_PERSONAL = 'ernestdefoe-cuecard.savePersonal';

    /** Create, edit and delete global macros. Admins only unless granted. */
    public const MANAGE_GLOBAL = 'ernestdefoe-cuecard.manageGlobal';
}
