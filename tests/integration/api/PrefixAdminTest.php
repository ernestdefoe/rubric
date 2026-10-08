<?php

namespace Ernestdefoe\Rubric\Tests\integration\api;

use Ernestdefoe\Rubric\Tests\integration\RubricTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The admin page's endpoints: refused to everyone but an admin, validated, and
 * always answered with the whole list in order.
 */
class PrefixAdminTest extends RubricTestCase
{
    public static function routes(): array
    {
        return [
            'list'   => ['GET', '/api/rubric/prefixes', null],
            'create' => ['POST', '/api/rubric/prefixes', ['name' => 'Solved', 'color' => '#16a34a']],
            'order'  => ['POST', '/api/rubric/prefixes/order', ['order' => [3, 2, 1]]],
            'update' => ['PATCH', '/api/rubric/prefixes/1', ['name' => 'Gossip', 'color' => '#2563eb']],
            'delete' => ['DELETE', '/api/rubric/prefixes/1', null],
        ];
    }

    #[Test]
    #[DataProvider('routes')]
    public function everyone_but_an_admin_is_refused(string $method, string $path, ?array $json)
    {
        $this->assertSame(403, $this->call($method, $path, null, $json)[0], 'Guest');
        $this->assertSame(403, $this->call($method, $path, 2, $json)[0], 'Member');
        $this->assertSame(403, $this->call($method, $path, 3, $json)[0], 'Moderator');
    }

    #[Test]
    #[DataProvider('routes')]
    public function an_admin_gets_the_whole_list(string $method, string $path, ?array $json)
    {
        [$status, $body] = $this->call($method, $path, 1, $json);

        $this->assertSame(200, $status, json_encode($body));
        $this->assertIsArray($body['data']);
    }

    #[Test]
    public function a_new_prefix_is_stored_and_goes_last()
    {
        [, $body] = $this->call('POST', '/api/rubric/prefixes', 1, ['name' => 'Solved', 'color' => '#ABC', 'icon' => 'fas fa-check', 'staffOnly' => true, 'tagIds' => [2, '2', 0]]);

        $this->assertSame(['rumor', 'official', 'confirmed', 'solved'], array_column($body['data'], 'slug'));
        $this->assertSame(['id' => $body['data'][3]['id'], 'name' => 'Solved', 'slug' => 'solved', 'color' => '#aabbcc', 'icon' => 'fas fa-check', 'staffOnly' => true, 'tagIds' => [2]], $body['data'][3]);
    }

    #[Test]
    public function a_prefix_is_validated()
    {
        $errors = function (array $attributes) {
            [$status, $body] = $this->call('POST', '/api/rubric/prefixes', 1, $attributes + ['name' => 'Fine', 'color' => '#2563eb']);
            $this->assertSame(422, $status);

            return array_map(fn ($e) => substr($e['source']['pointer'], strrpos($e['source']['pointer'], '/') + 1), $body['errors']);
        };

        $this->assertContains('name', $errors(['name' => '']));
        $this->assertSame(['name'], $errors(['name' => str_repeat('x', 61)]));
        $this->assertSame(['color'], $errors(['color' => 'blue']));
        $this->assertSame(['icon'], $errors(['icon' => 'fas fa-x" onmouseover="alert(1)']));
        $this->assertSame(['slug'], $errors(['slug' => 'rumor']));
        $this->assertSame(0, $this->database()->table('rubric_prefixes')->where('name', 'Fine')->count());
    }

    #[Test]
    public function a_prefix_keeps_its_own_slug_when_edited()
    {
        [$status] = $this->call('PATCH', '/api/rubric/prefixes/1', 1, ['name' => 'Rumor', 'slug' => 'rumor', 'color' => '#000000']);

        $this->assertSame(200, $status);
    }

    #[Test]
    public function a_missing_prefix_is_not_found()
    {
        $this->assertSame(404, $this->call('PATCH', '/api/rubric/prefixes/99', 1, ['name' => 'X', 'color' => '#000000'])[0]);
        $this->assertSame(404, $this->call('DELETE', '/api/rubric/prefixes/99', 1)[0]);
    }

    #[Test]
    public function the_order_from_a_drag_is_kept()
    {
        [, $body] = $this->call('POST', '/api/rubric/prefixes/order', 1, ['order' => [3, 1, 2]]);

        $this->assertSame(['confirmed', 'rumor', 'official'], array_column($body['data'], 'slug'));
    }

    #[Test]
    public function deleting_a_prefix_leaves_its_discussions_without_one()
    {
        $this->call('DELETE', '/api/rubric/prefixes/1', 1);

        $this->assertNull($this->prefixOf(1));
        $this->assertSame(1, $this->database()->table('discussions')->where('id', 1)->count());
    }
}
