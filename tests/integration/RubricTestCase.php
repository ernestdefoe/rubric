<?php

namespace Ernestdefoe\Rubric\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 member, 3 moderator.
 * Tags: 1 General, 2 News, 3 Rumours.
 * Prefixes: 1 Rumor (any tag), 2 Official (staff only), 3 Confirmed (News only).
 * Discussions: 1 by the member, prefixed Rumor, tagged General.
 */
abstract class RubricTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-rubric');

        // Several discussions are started per test, faster than flood control allows.
        $this->extend((new Extend\ThrottleApi())->remove('postTimeout'));

        $prefix = fn (int $id, string $name, bool $staff = false, ?array $tags = null) => [
            'id' => $id, 'name' => $name, 'slug' => strtolower($name), 'color' => '#2563eb', 'icon' => null,
            'staff_only' => $staff, 'tag_ids' => $tags === null ? null : json_encode($tags), 'position' => $id,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'color' => '#888', 'position' => 0, 'is_restricted' => 0],
                ['id' => 2, 'name' => 'News', 'slug' => 'news', 'color' => '#888', 'position' => 1, 'is_restricted' => 0],
                ['id' => 3, 'name' => 'Rumours', 'slug' => 'rumours', 'color' => '#888', 'position' => 2, 'is_restricted' => 0],
            ],
            'rubric_prefixes' => [
                $prefix(1, 'Rumor'),
                $prefix(2, 'Official', true),
                $prefix(3, 'Confirmed', false, [2]),
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Prefixed', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'rubric_prefix_id' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Hello</p></t>'],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
            ],
        ]);
    }

    /** @return array{0: int, 1: mixed} */
    protected function call(string $method, string $path, ?int $actor = null, ?array $json = null): array
    {
        $options = [];
        if ($actor) {
            $options['authenticatedAs'] = $actor;
        }
        if ($json !== null) {
            $options['json'] = $json;
        }

        $request = $this->request($method, $path, $options);

        // A guest's write needs a session and its CSRF token, or core refuses
        // it before the extension is reached and nothing of ours is tested.
        if (! $actor && $method !== 'GET') {
            $session = $this->send($this->request('GET', '/'));
            $request = $this->request($method, $path, $options + ['cookiesFrom' => $session])
                ->withHeader('X-CSRF-Token', $session->getHeaderLine('X-CSRF-Token'));
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** Start a discussion in the given tags, with the given prefix. */
    protected function startDiscussion(int $actor, ?int $prefix, array $tags): array
    {
        return $this->call('POST', '/api/discussions', $actor, ['data' => [
            'type' => 'discussions',
            'attributes' => ['title' => 'A new discussion', 'content' => 'Its first post', 'rubricPrefixId' => $prefix],
            'relationships' => ['tags' => ['data' => array_map(fn ($id) => ['type' => 'tags', 'id' => (string) $id], $tags)]],
        ]]);
    }

    protected function prefixOf(int $discussion): ?int
    {
        $id = $this->database()->table('discussions')->where('id', $discussion)->value('rubric_prefix_id');

        return $id === null ? null : (int) $id;
    }
}
