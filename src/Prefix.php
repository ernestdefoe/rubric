<?php

namespace Ernestdefoe\Rubric;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property string|null $icon
 * @property bool $staff_only
 * @property array|null $tag_ids
 * @property int $position
 */
class Prefix extends AbstractModel
{
    protected $table = 'rubric_prefixes';

    public $timestamps = true;

    protected $casts = [
        'id' => 'integer',
        'staff_only' => 'boolean',
        'tag_ids' => 'array',
        'position' => 'integer',
    ];

    /** @return int[] Empty means every tag. */
    public function tagIds(): array
    {
        return array_values(array_map('intval', (array) ($this->tag_ids ?? [])));
    }

    /** Whether the prefix may be used in a discussion with these tags. */
    public function allowedIn(array $tagIds): bool
    {
        $own = $this->tagIds();

        return $own === [] || array_intersect($own, array_map('intval', $tagIds)) !== [];
    }

    public function toPayload(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'color' => (string) $this->color,
            'icon' => $this->icon ? (string) $this->icon : null,
            'staffOnly' => (bool) $this->staff_only,
            'tagIds' => $this->tagIds(),
        ];
    }
}
