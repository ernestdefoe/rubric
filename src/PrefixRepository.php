<?php

namespace Ernestdefoe\Rubric;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Collection;

class PrefixRepository
{
    public const REQUIRED_TAGS = 'ernestdefoe-rubric.required_tags';

    /** @var Collection<int, Prefix>|null One query per request, however often it is asked. */
    private ?Collection $all = null;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    /** @return Collection<int, Prefix> */
    public function all(): Collection
    {
        return $this->all ??= Prefix::query()->orderBy('position')->orderBy('id')->get();
    }

    public function find(int $id): ?Prefix
    {
        return $this->all()->firstWhere('id', $id);
    }

    public function forget(): void
    {
        $this->all = null;
    }

    public function payload(): array
    {
        return $this->all()->map(fn (Prefix $p) => $p->toPayload())->values()->all();
    }

    /** @return int[] Tags in which a new discussion must have a prefix. */
    public function requiredTagIds(): array
    {
        $raw = json_decode((string) $this->settings->get(self::REQUIRED_TAGS, '[]'), true);

        return is_array($raw) ? array_values(array_unique(array_map('intval', $raw))) : [];
    }
}
