<?php

namespace Ernestdefoe\Rubric\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Rubric\Tests\integration\RubricTestCase;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use PHPUnit\Framework\Attributes\Test;

/**
 * A discussion's prefix: shown, chosen when starting one, changed only by
 * someone who may rename it, and refused where it isn't allowed.
 */
class DiscussionPrefixTest extends RubricTestCase
{
    #[Test]
    public function the_forum_payload_carries_the_prefixes_and_who_may_use_staff_ones()
    {
        $attributes = fn (?int $actor) => $this->call('GET', '/api', $actor)[1]['data']['attributes'];

        $this->assertSame(['rumor', 'official', 'confirmed'], array_column($attributes(null)['rubricPrefixes'], 'slug'));
        $this->assertSame([], $attributes(null)['rubricRequiredTags']);
        $this->assertFalse($attributes(2)['canUseStaffPrefixes']);
        $this->assertTrue($attributes(3)['canUseStaffPrefixes']);
        $this->assertTrue($attributes(1)['canUseStaffPrefixes']);
    }

    #[Test]
    public function the_required_tags_are_shared()
    {
        $this->setting('ernestdefoe-rubric.required_tags', '[2,"2",3]');

        $this->assertSame([2, 3], $this->call('GET', '/api')[1]['data']['attributes']['rubricRequiredTags']);
    }

    #[Test]
    public function a_discussion_shows_its_prefix()
    {
        [$status, $body] = $this->call('GET', '/api/discussions/1');

        $this->assertSame(200, $status);
        $this->assertSame(1, $body['data']['attributes']['rubricPrefixId']);
    }

    #[Test]
    public function the_list_shows_prefixes_without_a_query_per_discussion()
    {
        $discussions = $posts = $tags = [];
        foreach (range(2, 12) as $id) {
            $discussions[] = ['id' => $id, 'title' => "D$id", 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => $id, 'comment_count' => 1, 'rubric_prefix_id' => $id % 3 + 1];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>'];
            $tags[] = ['discussion_id' => $id, 'tag_id' => 1];
        }
        $this->prepareDatabase([Discussion::class => $discussions, Post::class => $posts, 'discussion_tag' => $tags]);

        // flarum/testing fails the request when the same query repeats.
        [$status, $body] = $this->call('GET', '/api/discussions');

        $this->assertSame(200, $status);
        $this->assertCount(12, $body['data']);
        $this->assertSame(3, collect($body['data'])->firstWhere('id', '11')['attributes']['rubricPrefixId']);
    }

    #[Test]
    public function a_member_starts_a_discussion_with_a_prefix()
    {
        [$status, $body] = $this->startDiscussion(2, 1, [1]);

        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame(1, $body['data']['attributes']['rubricPrefixId']);
        $this->assertSame(1, $this->prefixOf((int) $body['data']['id']));
    }

    #[Test]
    public function a_staff_prefix_is_for_those_allowed_it()
    {
        $this->assertSame(403, $this->startDiscussion(2, 2, [1])[0]);
        $this->assertSame(201, $this->startDiscussion(3, 2, [1])[0]);
    }

    #[Test]
    public function a_prefix_limited_to_some_tags_is_refused_elsewhere()
    {
        $this->assertSame(422, $this->startDiscussion(2, 3, [1])[0]);
        $this->assertSame(201, $this->startDiscussion(2, 3, [2])[0]);
    }

    #[Test]
    public function a_prefix_that_does_not_exist_is_refused()
    {
        $this->assertSame(422, $this->startDiscussion(2, 99, [1])[0]);
    }

    #[Test]
    public function a_prefix_is_required_in_a_required_tag()
    {
        $this->setting('ernestdefoe-rubric.required_tags', '[2]');

        $this->assertSame(422, $this->startDiscussion(2, null, [2])[0]);
        $this->assertSame(201, $this->startDiscussion(2, 1, [2])[0]);
        $this->assertSame(201, $this->startDiscussion(2, null, [1])[0], 'Other tags need none');
    }

    #[Test]
    public function a_member_with_no_prefix_to_choose_is_not_locked_out_of_a_required_tag()
    {
        // Limit Rumor to News, so the only prefix left for Rumours is the
        // staff-only Official.
        $this->setting('ernestdefoe-rubric.required_tags', '[3]');
        $this->app();
        $this->database()->table('rubric_prefixes')->where('id', 1)->update(['tag_ids' => '[2]']);

        $this->assertSame(201, $this->startDiscussion(2, null, [3])[0], 'A member has nothing to choose');
        $this->assertSame(422, $this->startDiscussion(3, null, [3])[0], 'A moderator does');
    }

    #[Test]
    public function only_someone_who_may_rename_a_discussion_changes_its_prefix()
    {
        $patch = fn (int $actor, ?int $prefix) => $this->call('PATCH', '/api/discussions/1', $actor, ['data' => [
            'type' => 'discussions', 'id' => '1', 'attributes' => ['rubricPrefixId' => $prefix],
        ]])[0];

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 2, 'title' => 'Someone else\'s', 'created_at' => Carbon::now(), 'user_id' => 3, 'first_post_id' => 2, 'comment_count' => 1, 'rubric_prefix_id' => null],
            ],
            Post::class => [
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Mine</p></t>'],
            ],
            'discussion_tag' => [['discussion_id' => 2, 'tag_id' => 1]],
        ]);

        $other = $this->call('PATCH', '/api/discussions/2', 2, ['data' => ['type' => 'discussions', 'id' => '2', 'attributes' => ['rubricPrefixId' => 1]]])[0];
        $this->assertContains($other, [403, 400], 'A member cannot relabel someone else\'s discussion');
        $this->assertNull($this->prefixOf(2));

        $this->assertSame(200, $patch(2, null), 'The author may remove their own');
        $this->assertNull($this->prefixOf(1));

        $this->assertSame(200, $patch(3, 2), 'A moderator may apply a staff prefix');
        $this->assertSame(2, $this->prefixOf(1));
    }

    #[Test]
    public function a_prefix_limited_to_some_tags_is_checked_against_the_discussions_own_tags()
    {
        // Discussion 1 is in General; Confirmed is for News only.
        [$status] = $this->call('PATCH', '/api/discussions/1', 1, ['data' => ['type' => 'discussions', 'id' => '1', 'attributes' => ['rubricPrefixId' => 3]]]);

        $this->assertSame(422, $status);
        $this->assertSame(1, $this->prefixOf(1));

        $this->database()->table('discussion_tag')->where('discussion_id', 1)->update(['tag_id' => 2]);

        [$status] = $this->call('PATCH', '/api/discussions/1', 1, ['data' => ['type' => 'discussions', 'id' => '1', 'attributes' => ['rubricPrefixId' => 3]]]);

        $this->assertSame(200, $status, 'Once the discussion is in News it may');
        $this->assertSame(3, $this->prefixOf(1));
    }

    #[Test]
    public function a_read_marker_update_is_never_blocked_by_a_prefix_rule()
    {
        // Rumor is now limited to News, so discussion 1 (General) breaks the
        // rule, but a reader marking it read is not changing its prefix.
        $this->app();
        $this->database()->table('rubric_prefixes')->where('id', 1)->update(['tag_ids' => '[2]']);

        [$status] = $this->call('PATCH', '/api/discussions/1', 3, ['data' => ['type' => 'discussions', 'id' => '1', 'attributes' => ['lastReadPostNumber' => 1]]]);

        $this->assertSame(200, $status);
    }

    #[Test]
    public function the_list_filters_by_prefix_and_by_its_absence()
    {
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 2, 'title' => 'Plain', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1, 'rubric_prefix_id' => null],
                ['id' => 3, 'title' => 'Official', 'created_at' => Carbon::now(), 'user_id' => 3, 'first_post_id' => 3, 'comment_count' => 1, 'rubric_prefix_id' => 2],
            ],
            Post::class => [
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>'],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>x</p></t>'],
            ],
            'discussion_tag' => [['discussion_id' => 2, 'tag_id' => 1], ['discussion_id' => 3, 'tag_id' => 1]],
        ]);

        $ids = function (array $filter) {
            $response = $this->send($this->request('GET', '/api/discussions')->withQueryParams(['filter' => $filter]));

            return array_map('intval', array_column(json_decode((string) $response->getBody(), true)['data'], 'id'));
        };

        $this->assertSame([1], $ids(['prefix' => 'rumor']));
        $this->assertEqualsCanonicalizing([1, 3], $ids(['prefix' => 'rumor,official']));
        $this->assertEqualsCanonicalizing([2, 3], $ids(['-prefix' => 'rumor']), 'Not Rumor includes discussions with no prefix');
    }
}
