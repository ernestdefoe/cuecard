<?php

namespace Ernestdefoe\Cuecard\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Cuecard\Macro;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class MacroTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const ADMIN = 1;
    private const MEMBER = 2;   // no macro permissions
    private const MOD = 3;      // moderator: use + save personal (the defaults)
    private const HELPER = 4;   // in group 5, which may only use macros
    private const MOD2 = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'flarum-lock', 'fof-best-answer', 'ernestdefoe-cuecard');

        $now = Carbon::now()->toDateTimeString();
        // Old enough that a reply is not flooding.
        $before = Carbon::now()->subHour()->toDateTimeString();

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'mod', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'helper', 'email' => 'helper@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'mod2', 'email' => 'mod2@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 5, 'name_singular' => 'Helper', 'name_plural' => 'Helpers', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
                ['user_id' => 4, 'group_id' => 5],
                ['user_id' => 5, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => 5, 'permission' => 'ernestdefoe-cuecard.use'],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.selectBestAnswerNotOwnDiscussion'],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'fof-best-answer.allow_select_own_post'],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.tag'],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.lock'],
            ],
            'tags' => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'position' => 0, 'is_qna' => 1],
                ['id' => 2, 'name' => 'Solved', 'slug' => 'solved', 'position' => null],
                ['id' => 3, 'name' => 'Staff', 'slug' => 'staff', 'position' => 2, 'is_restricted' => 1],
                ['id' => 4, 'name' => 'Off-topic', 'slug' => 'off-topic', 'position' => 3],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Help me', 'user_id' => 2, 'created_at' => $before, 'first_post_id' => 1, 'comment_count' => 1, 'last_post_number' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Help</p></t>', 'number' => 1, 'created_at' => $before],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
            ],
            'cuecard_macros' => [
                ['id' => 1, 'user_id' => null, 'title' => 'Everyone', 'content' => 'Hi {username}', 'created_at' => $now],
                ['id' => 2, 'user_id' => null, 'title' => 'Helpers only', 'content' => 'x', 'created_at' => $now],
                ['id' => 3, 'user_id' => null, 'title' => 'Admins only', 'content' => 'x', 'created_at' => $now],
                ['id' => 4, 'user_id' => 3, 'title' => 'Mod note', 'content' => 'x', 'created_at' => $now],
                ['id' => 5, 'user_id' => 5, 'title' => 'Mod2 note', 'content' => 'x', 'created_at' => $now],
            ],
            'cuecard_macro_groups' => [
                ['macro_id' => 1, 'group_id' => Group::MEMBER_ID],
                ['macro_id' => 2, 'group_id' => 5],
                ['macro_id' => 3, 'group_id' => Group::ADMINISTRATOR_ID],
            ],
        ]);
    }

    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    private function call(string $method, string $path, ?int $as, ?array $json = null, array $query = []): ResponseInterface
    {
        $options = [];
        if ($as) {
            $options['authenticatedAs'] = $as;
        }
        if ($json !== null) {
            $options['json'] = $json;
        }

        return $this->send($this->request($method, $path, $options)->withQueryParams($query));
    }

    /** @return list<string> */
    private function titles(?int $as): array
    {
        $response = $this->call('GET', '/api/cuecard-macros', $as);
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return array_map(fn ($row) => $row['attributes']['title'], $this->json($response)['data']);
    }

    private function create(int $as, array $attributes, array $relationships = []): ResponseInterface
    {
        return $this->call('POST', '/api/cuecard-macros', $as, ['data' => array_filter([
            'type' => 'cuecard-macros',
            'attributes' => $attributes,
            'relationships' => $relationships,
        ])]);
    }

    private function reply(int $as, array $actions, int $discussion = 1): ResponseInterface
    {
        return $this->call('POST', '/api/posts', $as, ['data' => [
            'type' => 'posts',
            'attributes' => ['content' => 'A reply from a macro.', 'cuecardActions' => $actions],
            'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussion]]],
        ]]);
    }

    // --- Who sees what -----------------------------------------------------

    #[Test]
    public function guests_cannot_list_macros(): void
    {
        $this->assertEquals(401, $this->call('GET', '/api/cuecard-macros', null)->getStatusCode());
    }

    #[Test]
    public function a_member_without_permission_sees_none(): void
    {
        $this->assertSame([], $this->titles(self::MEMBER));
    }

    #[Test]
    public function a_moderator_sees_open_globals_and_their_own_only(): void
    {
        $this->assertSame(['Everyone', 'Mod note'], $this->titles(self::MOD));
    }

    #[Test]
    public function a_group_sees_the_globals_offered_to_it_but_saves_none(): void
    {
        $this->assertSame(['Everyone', 'Helpers only'], $this->titles(self::HELPER));
        $this->assertEquals(403, $this->create(self::HELPER, ['title' => 'Mine', 'content' => 'x'])->getStatusCode());
    }

    #[Test]
    public function an_admin_sees_every_global_but_nobody_elses_personal_macros(): void
    {
        $this->assertSame(['Admins only', 'Everyone', 'Helpers only'], $this->titles(self::ADMIN));
    }

    #[Test]
    public function losing_the_permission_hides_your_own_macros(): void
    {
        $this->database()->table('group_permission')
            ->where('group_id', Group::MODERATOR_ID)->where('permission', 'ernestdefoe-cuecard.savePersonal')->delete();

        $this->assertSame(['Everyone'], $this->titles(self::MOD));
    }

    #[Test]
    public function group_links_are_shown_to_managers_only(): void
    {
        $member = $this->json($this->call('GET', '/api/cuecard-macros', self::HELPER, null, ['include' => 'groups']));
        $this->assertEmpty($member['included'] ?? []);
        foreach ($member['data'] as $row) {
            $this->assertArrayNotHasKey('groups', $row['relationships'] ?? []);
        }

        $admin = $this->json($this->call('GET', '/api/cuecard-macros', self::ADMIN, null, ['include' => 'groups']));
        $helpers = array_values(array_filter($admin['data'], fn ($row) => $row['id'] === '2'))[0];
        $this->assertSame([['type' => 'groups', 'id' => '5']], $helpers['relationships']['groups']['data']);
    }

    #[Test]
    public function forum_flags_are_sent_only_when_true(): void
    {
        $guest = $this->json($this->call('GET', '/api', null))['data']['attributes'];
        $this->assertArrayNotHasKey('cuecardUse', $guest);
        $this->assertArrayNotHasKey('cuecardSavePersonal', $guest);
        $this->assertArrayNotHasKey('cuecardManage', $guest);

        $mod = $this->json($this->call('GET', '/api', self::MOD))['data']['attributes'];
        $this->assertTrue($mod['cuecardUse']);
        $this->assertTrue($mod['cuecardSavePersonal']);
        $this->assertArrayNotHasKey('cuecardManage', $mod);
    }

    // --- Writing -----------------------------------------------------------

    #[Test]
    public function a_moderator_saves_a_personal_macro_but_not_a_global_one(): void
    {
        $response = $this->create(self::MOD, ['title' => '  Thanks  ', 'content' => 'Thanks, {username}!']);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $data = $this->json($response)['data']['attributes'];
        $this->assertSame('Thanks', $data['title']);
        $this->assertFalse($data['isGlobal']);
        $this->assertTrue($data['canEdit']);
        $this->assertSame(3, Macro::query()->where('title', 'Thanks')->value('user_id'));

        $this->assertEquals(403, $this->create(self::MOD, ['title' => 'G', 'content' => 'x', 'isGlobal' => true])->getStatusCode());
    }

    #[Test]
    public function an_admin_saves_a_global_macro_for_chosen_groups_never_guests(): void
    {
        $response = $this->create(self::ADMIN, ['title' => 'G', 'content' => 'x', 'isGlobal' => true], ['groups' => ['data' => [
            ['type' => 'groups', 'id' => '5'],
            ['type' => 'groups', 'id' => (string) Group::GUEST_ID],
        ]]]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $id = (int) $this->json($response)['data']['id'];
        $this->assertNull(Macro::query()->find($id)->user_id);
        $this->assertSame([5], $this->database()->table('cuecard_macro_groups')->where('macro_id', $id)->pluck('group_id')->map(fn ($id) => (int) $id)->all());
        $this->assertContains('G', $this->titles(self::HELPER));
        $this->assertNotContains('G', $this->titles(self::MOD));
    }

    #[Test]
    public function a_global_macro_names_at_least_one_real_group(): void
    {
        $this->assertEquals(422, $this->create(self::ADMIN, ['title' => 'G', 'content' => 'x', 'isGlobal' => true])->getStatusCode());
        $this->assertEquals(422, $this->create(self::ADMIN, ['title' => 'G', 'content' => 'x', 'isGlobal' => true], ['groups' => ['data' => [['type' => 'groups', 'id' => '2']]]])->getStatusCode());

        $patch = $this->call('PATCH', '/api/cuecard-macros/2', self::ADMIN, ['data' => ['type' => 'cuecard-macros', 'id' => '2', 'relationships' => ['groups' => ['data' => []]]]]);
        $this->assertEquals(422, $patch->getStatusCode());

        // Leaving the groups out of an edit keeps them.
        $patch = $this->call('PATCH', '/api/cuecard-macros/2', self::ADMIN, ['data' => ['type' => 'cuecard-macros', 'id' => '2', 'attributes' => ['title' => 'Helpers']]]);
        $this->assertEquals(200, $patch->getStatusCode(), (string) $patch->getBody());
        $this->assertContains('Helpers', $this->titles(self::HELPER));
    }

    #[Test]
    public function deleting_its_only_group_offers_a_macro_to_nobody_not_everybody(): void
    {
        $this->call('DELETE', '/api/groups/5', self::ADMIN);
        $this->database()->table('groups')->where('id', 5)->delete();

        $this->database()->table('group_user')->insert(['user_id' => 4, 'group_id' => Group::MODERATOR_ID]);
        $this->assertNotContains('Helpers only', $this->titles(self::HELPER));
        $this->assertNotContains('Helpers only', $this->titles(self::MOD));
    }

    #[Test]
    public function a_global_macro_can_be_delegated_with_the_manage_permission(): void
    {
        $this->database()->table('group_permission')->insert(['group_id' => Group::MODERATOR_ID, 'permission' => 'ernestdefoe-cuecard.manageGlobal']);

        $members = ['groups' => ['data' => [['type' => 'groups', 'id' => (string) Group::MEMBER_ID]]]];
        $this->assertEquals(201, $this->create(self::MOD, ['title' => 'G', 'content' => 'x', 'isGlobal' => true], $members)->getStatusCode());
        $this->assertSame(['Admins only', 'Everyone', 'G', 'Helpers only', 'Mod note'], $this->titles(self::MOD));
    }

    #[Test]
    public function members_without_permission_cannot_save_and_guests_are_turned_away(): void
    {
        $this->assertEquals(403, $this->create(self::MEMBER, ['title' => 'x', 'content' => 'x'])->getStatusCode());
        // Refused before it is read: the CSRF check (400) or the sign-in check (401).
        $this->assertContains($this->call('POST', '/api/cuecard-macros', null, ['data' => ['type' => 'cuecard-macros', 'attributes' => ['title' => 'x', 'content' => 'x']]])->getStatusCode(), [400, 401]);
        $this->assertSame(5, Macro::query()->count());
    }

    #[Test]
    public function blank_and_oversized_fields_are_refused(): void
    {
        $this->assertEquals(422, $this->create(self::MOD, ['title' => '   ', 'content' => 'x'])->getStatusCode());
        $this->assertEquals(422, $this->create(self::MOD, ['title' => 'x', 'content' => " \n "])->getStatusCode());
        $this->assertEquals(422, $this->create(self::MOD, ['title' => str_repeat('a', 101), 'content' => 'x'])->getStatusCode());
        $this->assertEquals(422, $this->create(self::MOD, ['title' => 'x', 'content' => str_repeat('a', 20001)])->getStatusCode());
        $this->assertEquals(422, $this->create(self::MOD, ['title' => 'x', 'content' => 'x', 'tagIds' => range(1, 11)])->getStatusCode());
    }

    #[Test]
    public function personal_macros_are_capped(): void
    {
        $now = Carbon::now()->toDateTimeString();
        $rows = [];
        for ($i = 1; $i < Macro::MAX_PERSONAL; $i++) {
            $rows[] = ['user_id' => 3, 'title' => "m$i", 'content' => 'x', 'created_at' => $now];
        }
        $this->database()->table('cuecard_macros')->insert($rows);

        $this->assertEquals(422, $this->create(self::MOD, ['title' => 'one too many', 'content' => 'x'])->getStatusCode());
        // Global macros are not counted against anyone.
        $this->assertEquals(201, $this->create(self::ADMIN, ['title' => 'g', 'content' => 'x', 'isGlobal' => true], ['groups' => ['data' => [['type' => 'groups', 'id' => '3']]]])->getStatusCode());
    }

    #[Test]
    public function tag_ids_keep_only_tags_the_author_can_see(): void
    {
        $response = $this->create(self::MOD, ['title' => 't', 'content' => 'x', 'tagIds' => [2, 2, '1', 3, 999, 'x', -1]]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([2, 1], $this->json($response)['data']['attributes']['tagIds']);
    }

    #[Test]
    public function nobody_edits_or_deletes_a_macro_that_is_not_theirs(): void
    {
        $patch = fn (int $id, int $as) => $this->call('PATCH', "/api/cuecard-macros/$id", $as, ['data' => ['type' => 'cuecard-macros', 'id' => (string) $id, 'attributes' => ['title' => 'hacked']]]);

        // Another moderator's personal macro does not exist for you.
        $this->assertEquals(404, $patch(5, self::MOD)->getStatusCode());
        $this->assertEquals(404, $this->call('DELETE', '/api/cuecard-macros/5', self::MOD)->getStatusCode());
        // A global macro you may use is not one you may change.
        $this->assertEquals(403, $patch(1, self::MOD)->getStatusCode());
        $this->assertEquals(403, $this->call('DELETE', '/api/cuecard-macros/1', self::MOD)->getStatusCode());
        // Nor may an admin edit someone's personal macro.
        $this->assertEquals(404, $patch(4, self::ADMIN)->getStatusCode());

        $this->assertSame(0, Macro::query()->where('title', 'hacked')->count());
        $this->assertSame(5, Macro::query()->count());
    }

    #[Test]
    public function owners_edit_and_delete_their_own_and_cannot_turn_it_global(): void
    {
        $response = $this->call('PATCH', '/api/cuecard-macros/4', self::MOD, ['data' => ['type' => 'cuecard-macros', 'id' => '4', 'attributes' => ['title' => 'Renamed', 'lock' => true]]]);
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue(Macro::query()->find(4)->lock);

        $response = $this->call('PATCH', '/api/cuecard-macros/4', self::MOD, ['data' => ['type' => 'cuecard-macros', 'id' => '4', 'attributes' => ['isGlobal' => true]]]);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertSame(3, Macro::query()->find(4)->user_id);

        $this->assertEquals(204, $this->call('DELETE', '/api/cuecard-macros/4', self::MOD)->getStatusCode());
        $this->assertNull(Macro::query()->find(4));
    }

    #[Test]
    public function deleting_a_shared_macro_removes_its_group_links(): void
    {
        $this->assertEquals(204, $this->call('DELETE', '/api/cuecard-macros/2', self::ADMIN)->getStatusCode());
        $this->assertSame(0, $this->database()->table('cuecard_macro_groups')->where('macro_id', 2)->count());
    }

    #[Test]
    public function deleting_a_user_deletes_their_macros(): void
    {
        $this->call('DELETE', '/api/users/5', self::ADMIN);

        $this->assertNull(Macro::query()->find(5));
        $this->assertNotNull(Macro::query()->find(1));
    }

    // --- Reply actions -----------------------------------------------------

    #[Test]
    public function a_moderator_reply_locks_tags_and_marks_itself_best(): void
    {
        $response = $this->reply(self::MOD, ['lock' => true, 'tags' => [2], 'bestAnswer' => true]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $body = $this->json($response);
        $this->assertArrayNotHasKey('cuecardFailed', $body['data']['attributes']);
        $this->assertArrayNotHasKey('cuecardActions', $body['data']['attributes']);

        $discussion = Discussion::query()->find(1);
        $this->assertTrue((bool) $discussion->is_locked);
        $this->assertSame((int) $body['data']['id'], (int) $discussion->best_answer_post_id);
        $this->assertSame([1, 2], $this->database()->table('discussion_tag')->where('discussion_id', 1)->orderBy('tag_id')->pluck('tag_id')->map(fn ($id) => (int) $id)->all());

        // The response carries the discussion as it is now.
        $included = array_values(array_filter($body['included'], fn ($row) => $row['type'] === 'discussions'))[0];
        $this->assertTrue($included['attributes']['isLocked']);
    }

    #[Test]
    public function replacing_tags_replaces_them(): void
    {
        $this->reply(self::MOD, ['tags' => [4], 'replaceTags' => true]);

        $this->assertSame([4], $this->database()->table('discussion_tag')->where('discussion_id', 1)->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
    }

    #[Test]
    public function actions_the_poster_may_not_take_fail_and_the_reply_stays(): void
    {
        $response = $this->reply(self::MEMBER, ['lock' => true, 'tags' => [2], 'bestAnswer' => true]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $this->assertSame(['tags', 'lock', 'bestAnswer'], $this->json($response)['data']['attributes']['cuecardFailed']);

        $discussion = Discussion::query()->find(1);
        $this->assertFalse((bool) $discussion->is_locked);
        $this->assertNull($discussion->best_answer_post_id);
        $this->assertSame([1], $this->database()->table('discussion_tag')->where('discussion_id', 1)->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(2, Post::query()->where('discussion_id', 1)->count());
    }

    #[Test]
    public function tags_that_break_the_forums_tag_rules_are_refused(): void
    {
        // A second primary tag, where one is the limit.
        $response = $this->reply(self::MOD, ['tags' => [4]]);
        $this->assertSame(['tags'], $this->json($response)['data']['attributes']['cuecardFailed']);
        $this->database()->table('posts')->update(['created_at' => Carbon::now()->subHour()]);

        // No primary tag at all.
        $response = $this->reply(self::MOD, ['tags' => [2], 'replaceTags' => true]);
        $this->assertSame(['tags'], $this->json($response)['data']['attributes']['cuecardFailed']);

        $this->assertSame([1], $this->database()->table('discussion_tag')->where('discussion_id', 1)->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
    }

    #[Test]
    public function a_tag_the_poster_cannot_use_fails_alone(): void
    {
        $response = $this->reply(self::MOD, ['lock' => true, 'tags' => [3]]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $this->assertSame(['tags'], $this->json($response)['data']['attributes']['cuecardFailed']);
        $this->assertTrue((bool) Discussion::query()->find(1)->is_locked);
    }

    #[Test]
    public function malformed_actions_are_ignored(): void
    {
        $response = $this->reply(self::MOD, ['lock' => 'yes', 'tags' => 'all', 'bestAnswer' => 1, 'drop' => true]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $discussion = Discussion::query()->find(1);
        $this->assertFalse((bool) $discussion->is_locked);
        $this->assertNull($discussion->best_answer_post_id);
    }

    #[Test]
    public function actions_are_never_taken_on_a_new_discussion_or_an_edit(): void
    {
        $response = $this->call('POST', '/api/discussions', self::MOD, ['data' => [
            'type' => 'discussions',
            'attributes' => ['title' => 'New', 'content' => 'First post'],
            'relationships' => ['tags' => ['data' => [['type' => 'tags', 'id' => '1']]]],
        ]]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $response = $this->call('PATCH', '/api/posts/1', self::MOD, ['data' => ['type' => 'posts', 'id' => '1', 'attributes' => ['cuecardActions' => ['lock' => true]]]]);
        $this->assertContains($response->getStatusCode(), [400, 403]);
        $this->assertFalse((bool) Discussion::query()->find(1)->is_locked);
    }
}
