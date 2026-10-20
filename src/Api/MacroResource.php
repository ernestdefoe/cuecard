<?php

namespace Ernestdefoe\Cuecard\Api;

use Ernestdefoe\Cuecard\Macro;
use Ernestdefoe\Cuecard\Permissions;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Group\Group;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Saved replies. Listing returns every macro the actor may insert, in one
 * request, when the macro picker first opens: nothing about macros rides on
 * the page payload.
 *
 * @extends AbstractDatabaseResource<Macro>
 */
class MacroResource extends AbstractDatabaseResource
{
    public function __construct(private TranslatorInterface $translator, private ExtensionManager $extensions)
    {
    }

    public function type(): string
    {
        return 'cuecard-macros';
    }

    public function model(): string
    {
        return Macro::class;
    }

    /**
     * Every endpoint reads through this, so a macro the actor may not see is
     * a 404 to update or delete, not a 403 that confirms it exists.
     */
    public function scope(Builder $query, OriginalContext $context): void
    {
        if ($context instanceof Context) {
            /** @var Builder<Macro> $query */
            Macro::scopeVisibleTo($query, $context->getActor());
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->defaultSort('title')
                ->eagerLoadWhenIncluded(['groups' => ['groups']]),
            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['groups']),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit')
                ->defaultInclude(['groups']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('title'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Macro $model */
        /** @var Context $context */
        $actor = $context->getActor();

        if ($model->user_id === null) {
            if (! $actor->hasPermission(Permissions::MANAGE_GLOBAL)) {
                throw new PermissionDeniedException();
            }
        } else {
            if (! $actor->hasPermission(Permissions::SAVE_PERSONAL)) {
                throw new PermissionDeniedException();
            }

            if (Macro::query()->where('user_id', $actor->id)->count() >= Macro::MAX_PERSONAL) {
                throw new ValidationException([
                    'title' => $this->translator->trans('ernestdefoe-cuecard.api.too_many_macros', ['count' => Macro::MAX_PERSONAL]),
                ]);
            }
        }

        if ($model->user_id === null) {
            $this->assertGroupsGiven($context, true);
        }

        // Flarum's models do not take the column defaults, so a new row states its own.
        $model->lock ??= false;
        $model->replace_tags ??= false;
        $model->best_answer ??= false;

        return $model;
    }

    public function updating(object $model, OriginalContext $context): ?object
    {
        /** @var Macro $model */
        /** @var Context $context */
        if ($model->isGlobal()) {
            $this->assertGroupsGiven($context, false);
        }

        return $model;
    }

    /** Its group links go with it, also where the database enforces no foreign keys. */
    public function deleting(object $model, OriginalContext $context): void
    {
        /** @var Macro $model */
        $model->groups()->detach();
    }

    /**
     * A global macro is offered to at least one group, so "nobody ticked"
     * never quietly means "everybody". Members is the everyone choice.
     */
    private function assertGroupsGiven(Context $context, bool $required): void
    {
        $body = $context->body();
        $given = is_array($body['data']['relationships']['groups']['data'] ?? null);
        $ids = $given ? array_filter(
            array_map(fn ($identifier) => (int) ($identifier['id'] ?? 0), $body['data']['relationships']['groups']['data']),
            fn (int $id) => $id > 0 && $id !== Group::GUEST_ID
        ) : [];

        if (($required || $given) && ! $ids) {
            throw new ValidationException([
                'groups' => $this->translator->trans('ernestdefoe-cuecard.api.groups_required'),
            ]);
        }
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(100)
                ->rule('regex:/\S/')
                ->set(fn (Macro $macro, string $value) => $macro->title = trim($value)),
            // Stored in whatever the forum's editor writes (Markdown, BBCode, or
            // Scribe's HTML) and inserted into that same editor. Never rendered
            // as HTML by this extension: the picker shows it as text.
            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(20000)
                ->rule('regex:/\S/'),
            // Chosen once, when the macro is made: personal (yours) or global.
            Schema\Boolean::make('isGlobal')
                ->writableOnCreate()
                ->default(fn () => false)
                ->get(fn (Macro $macro) => $macro->isGlobal())
                ->set(function (Macro $macro, bool $value, Context $context) {
                    $macro->user_id = $value ? null : $context->getActor()->id;
                }),
            Schema\Boolean::make('lock')
                ->writable(),
            Schema\Arr::make('tagIds')
                ->writable()
                ->nullable()
                ->rule('max:10')
                ->property('tag_ids')
                ->get(fn (Macro $macro) => $macro->tag_ids ?: [])
                ->set(fn (Macro $macro, ?array $value, Context $context) => $macro->tag_ids = $this->tagIds($value, $context->getActor())),
            Schema\Boolean::make('replaceTags')
                ->writable()
                ->property('replace_tags'),
            Schema\Boolean::make('bestAnswer')
                ->writable()
                ->property('best_answer'),
            Schema\Boolean::make('canEdit')
                ->get(fn (Macro $macro, Context $context) => $context->getActor()->can('edit', $macro)),

            // Who a global macro is offered to. Only managers see or set it.
            Schema\Relationship\ToMany::make('groups')
                ->type('groups')
                ->includable()
                ->visible(fn (Macro $macro, Context $context) => $macro->isGlobal() && $context->getActor()->hasPermission(Permissions::MANAGE_GLOBAL))
                ->writable(fn (Macro $macro, Context $context) => $context->getActor()->hasPermission(Permissions::MANAGE_GLOBAL))
                ->save(function (Macro $macro, array $groups) {
                    // Guests can never use macros, so offering one to them means nothing.
                    $ids = array_values(array_filter(
                        array_map(fn (Group $group) => (int) $group->id, $groups),
                        fn (int $id) => $id !== Group::GUEST_ID
                    ));

                    $macro->groups()->sync($macro->isGlobal() ? $ids : []);
                    $macro->unsetRelation('groups');
                }),
        ];
    }

    /**
     * Whole, distinct ids of tags the actor can see; none when Tags is off.
     * Ids of tags hidden from the actor are dropped like missing ones, so the
     * field cannot be used to learn which hidden tags exist.
     *
     * @param array<mixed>|null $value
     * @return list<int>|null
     */
    private function tagIds(?array $value, User $actor): ?array
    {
        if (! $value || ! $this->extensions->isEnabled('flarum-tags')) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            fn ($id) => is_numeric($id) ? (int) $id : 0,
            $value
        ), fn (int $id) => $id > 0)));

        if (! $ids) {
            return null;
        }

        /** @var list<int> $existing */
        $existing = \Flarum\Tags\Tag::query()->whereVisibleTo($actor)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($ids, $existing)) ?: null;
    }
}
