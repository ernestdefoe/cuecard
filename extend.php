<?php

use Ernestdefoe\Cuecard\Access\MacroPolicy;
use Ernestdefoe\Cuecard\Api\MacroResource;
use Ernestdefoe\Cuecard\Macro;
use Ernestdefoe\Cuecard\Permissions;
use Ernestdefoe\Cuecard\ReplyActions;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\User\Event\Deleted;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    new Extend\ApiResource(MacroResource::class),

    (new Extend\Policy())
        ->modelPolicy(Macro::class, MacroPolicy::class),

    // Three booleans, sent only when true: whether to show the composer's
    // macro button, and what it may offer. The macros themselves load when
    // the picker first opens.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('cuecardUse')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Permissions::USE))
                ->get(fn () => true),
            Schema\Boolean::make('cuecardSavePersonal')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Permissions::SAVE_PERSONAL))
                ->get(fn () => true),
            Schema\Boolean::make('cuecardManage')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Permissions::MANAGE_GLOBAL))
                ->get(fn () => true),
        ]),

    (new Extend\ApiResource(PostResource::class))
        ->fields(function () {
            $actions = resolve(ReplyActions::class);

            return [
                // The actions of the macros inserted into a reply, run once it
                // is saved (see ReplyActions). Write-only.
                Schema\Arr::make('cuecardActions')
                    ->writableOnCreate()
                    ->nullable()
                    ->visible(false)
                    ->save(function (Post $post, ?array $value, Context $context) use ($actions) {
                        if ($value) {
                            $actions->run($post, $value, $context);
                        }
                    }),
                // Which of those actions were refused, on the response to that one request.
                Schema\Arr::make('cuecardFailed')
                    ->visible(fn (Post $post) => $actions->failed($post) !== null)
                    ->get(fn (Post $post) => $actions->failed($post)),
            ];
        }),

    // A person's own macros go with them (also where the database enforces no foreign keys).
    (new Extend\Event())
        ->listen(Deleted::class, fn (Deleted $event) => Macro::query()->where('user_id', $event->user->id)->delete()),

    (new Extend\ServiceProvider())
        ->register(\Ernestdefoe\Cuecard\Provider::class),
];
