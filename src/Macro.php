<?php

namespace Ernestdefoe\Cuecard;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Group\Group;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A saved reply. Global when `user_id` is null: managed by staff and offered
 * to the groups it lists (Members for everyone). Personal otherwise, seen and
 * used by its author alone.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $content
 * @property bool $lock
 * @property list<int>|null $tag_ids
 * @property bool $replace_tags
 * @property bool $best_answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, Group> $groups
 */
class Macro extends AbstractModel
{
    /** A member's own macros are capped, so one account cannot fill the table. */
    public const MAX_PERSONAL = 100;

    protected $table = 'cuecard_macros';

    public $timestamps = true;

    protected $casts = [
        'user_id' => 'integer',
        'lock' => 'boolean',
        'tag_ids' => 'array',
        'replace_tags' => 'boolean',
        'best_answer' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Group, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'cuecard_macro_groups', 'macro_id', 'group_id');
    }

    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * The macros an actor may see and insert: their own personal ones while
     * they may save them, and the global ones offered to one of their groups
     * while they may use macros. Managers see every global macro.
     *
     * @param Builder<Macro> $query
     */
    public static function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->isGuest()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $personal = $actor->hasPermission(Permissions::SAVE_PERSONAL);
        $manage = $actor->hasPermission(Permissions::MANAGE_GLOBAL);
        $use = $manage || $actor->hasPermission(Permissions::USE);

        $query->where(function (Builder $query) use ($actor, $personal, $manage, $use) {
            if ($personal) {
                $query->orWhere('user_id', $actor->id);
            }

            if ($manage) {
                $query->orWhereNull('user_id');
            } elseif ($use) {
                // Read from the link table itself, not through a join on groups:
                // a macro whose groups are all gone is offered to nobody, never
                // to everyone.
                $query->orWhere(function (Builder $query) use ($actor) {
                    $query->whereNull('user_id')->whereIn('id', function ($query) use ($actor) {
                        $query->select('macro_id')
                            ->from('cuecard_macro_groups')
                            ->whereIn('group_id', $actor->permissionGroupIds());
                    });
                });
            }

            if (! $personal && ! $use) {
                $query->whereRaw('1 = 0');
            }
        });
    }
}
