<?php

namespace Ernestdefoe\Cuecard;

use Flarum\Api\Client;
use Flarum\Api\Context;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\Post;
use Flarum\User\User;
use WeakMap;

/**
 * What a macro may do when the reply it was inserted into is posted: lock the
 * discussion, tag it, mark the new reply as the best answer.
 *
 * 🚨 Nothing here decides who may do what. Each action is an ordinary update
 * of the discussion through Flarum's own API, as the poster, so the same
 * permission checks and validation run as when they click Lock, Edit Tags or
 * Best Answer themselves — tag scopes, tag counts, best-answer rules and the
 * event posts included. An action the poster may not take fails there and is
 * reported back; the reply itself is already saved and stays.
 *
 * 🚨 Through the API client, not JsonApi::process(): process() never loads
 * the model for an update and skips the endpoint's own checks.
 */
class ReplyActions
{
    public const LOCK = 'lock';
    public const TAGS = 'tags';
    public const BEST_ANSWER = 'bestAnswer';

    /** @var WeakMap<Post, list<string>> */
    private WeakMap $failed;

    public function __construct(
        private Client $api,
        private ExtensionManager $extensions,
    ) {
        $this->failed = new WeakMap();
    }

    /**
     * @param array<mixed> $value {lock?: bool, tags?: int[], replaceTags?: bool, bestAnswer?: bool}
     */
    public function run(Post $post, array $value, Context $context): void
    {
        // A discussion's first post is not a reply: its tags come from the
        // composer and there is nothing yet to lock or answer.
        if ($context->internal('isFirstPost') || ! $post->exists || $post->number === 1) {
            return;
        }

        // A reply waiting for approval (flarum/approval) changes nothing yet.
        if ($post->getAttribute('is_approved') === false) {
            return;
        }

        $actor = $context->getActor();
        $failed = [];

        $updates = $this->updates($post, $value);

        // All at once first: one discussion update. Only when that is refused
        // are they tried one by one, so one refused action never takes the
        // others with it and the poster learns exactly which failed.
        if (count($updates) > 1 && $this->update($post->discussion_id, array_merge_recursive(...array_values($updates)), $actor, $context)) {
            $updates = [];
        }

        foreach ($updates as $action => $data) {
            if (! $this->update($post->discussion_id, $data, $actor, $context)) {
                $failed[] = $action;
            }
        }

        if ($failed) {
            $this->failed[$post] = $failed;
        }

        // The response includes the discussion; load it fresh so it shows the
        // lock, tags and best answer just set rather than the copy from before.
        $post->unsetRelation('discussion');
    }

    /** @return list<string>|null */
    public function failed(Post $post): ?array
    {
        return $this->failed[$post] ?? null;
    }

    /**
     * The discussion update for each action, keyed by action.
     *
     * @param array<mixed> $value
     * @return array<string, array<string, mixed>>
     */
    private function updates(Post $post, array $value): array
    {
        $updates = [];

        $tagIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => is_numeric($id) ? (int) $id : 0,
            is_array($value['tags'] ?? null) ? array_slice($value['tags'], 0, 10) : []
        ), fn (int $id) => $id > 0)));

        if ($tagIds && $this->extensions->isEnabled('flarum-tags')) {
            if (empty($value['replaceTags'])) {
                /** @var Discussion|null $discussion */
                $discussion = Discussion::query()->find($post->discussion_id);
                /** @phpstan-ignore-next-line tags() comes from flarum/tags */
                $current = $discussion ? $discussion->tags()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
                $tagIds = array_values(array_unique([...$current, ...$tagIds]));
            }

            $updates[self::TAGS] = ['relationships' => ['tags' => ['data' => array_map(
                fn (int $id) => ['type' => 'tags', 'id' => (string) $id],
                $tagIds
            )]]];
        }

        if (($value['lock'] ?? false) === true && $this->extensions->isEnabled('flarum-lock')) {
            $updates[self::LOCK] = ['attributes' => ['isLocked' => true]];
        }

        if (($value['bestAnswer'] ?? false) === true && $this->extensions->isEnabled('fof-best-answer')) {
            $updates[self::BEST_ANSWER] = ['relationships' => ['bestAnswerPost' => ['data' => ['type' => 'posts', 'id' => (string) $post->id]]]];
        }

        return $updates;
    }

    /**
     * A PATCH to /api/discussions/{id} through Flarum's internal API client:
     * the whole endpoint runs, visibility and field permissions included.
     * Errors come back as a response — a refusal is a 4xx; anything worse has
     * already been reported by Flarum's error handler.
     *
     * @param array<string, mixed> $data
     */
    private function update(int $discussionId, array $data, User $actor, Context $context): bool
    {
        $response = $this->api
            ->withParentRequest($context->request)
            ->withActor($actor)
            ->withBody(['data' => ['type' => 'discussions', 'id' => (string) $discussionId, ...$data]])
            ->patch('/discussions/'.$discussionId);

        return $response->getStatusCode() < 400;
    }
}
