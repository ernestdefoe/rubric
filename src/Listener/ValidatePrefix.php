<?php

namespace Ernestdefoe\Rubric\Listener;

use Ernestdefoe\Rubric\Prefix;
use Ernestdefoe\Rubric\PrefixRepository;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;

/**
 * Every rule about a discussion's prefix, checked once the request's fields
 * have been applied and before anything is written.
 *
 * 🚨 Runs on EVERY discussion save, including the "read up to post N" update
 * a reader's browser sends. It only acts when a discussion is being started or
 * its prefix is the thing being changed.
 */
class ValidatePrefix
{
    public function __construct(
        protected PrefixRepository $prefixes,
        protected TranslatorInterface $translator,
        protected ExtensionManager $extensions
    ) {
    }

    public function handle(Saving $event): void
    {
        $discussion = $event->discussion;
        $creating = ! $discussion->exists;

        if (! $creating && ! $discussion->isDirty('rubric_prefix_id')) {
            return;
        }

        $tagIds = $this->tagIds($discussion, $event->data, $creating);
        $prefixId = $discussion->rubric_prefix_id ? (int) $discussion->rubric_prefix_id : null;

        if ($prefixId === null) {
            if ($creating) {
                $this->assertNotRequired($event->actor, $tagIds);
            }

            return;
        }

        $prefix = $this->prefixes->find($prefixId);

        if (! $prefix) {
            $this->fail('not_found');
        }

        if ($prefix->staff_only && ! $event->actor->hasPermission('rubric.useStaffPrefixes')) {
            throw new PermissionDeniedException();
        }

        if ($this->tagsEnabled() && ! $prefix->allowedIn($tagIds)) {
            $this->fail('not_allowed_here', ['prefix' => $prefix->name]);
        }
    }

    /**
     * The tags the discussion will have once this request is done: the ones in
     * the request if it sends any, otherwise the ones it already has.
     */
    protected function tagIds(Discussion $discussion, array $data, bool $creating): array
    {
        if (! $this->tagsEnabled()) {
            return [];
        }

        $sent = Arr::get($data, 'relationships.tags.data');

        if (is_array($sent)) {
            return array_values(array_filter(array_map(fn ($t) => (int) ($t['id'] ?? 0), $sent)));
        }

        if ($creating) {
            return [];
        }

        return $discussion->tags()->pluck('tags.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * A prefix is required in some tags — but only if this member has a prefix
     * they could choose there. A forum whose only prefix for a tag is
     * staff-only must not lock members out of starting discussions in it.
     */
    protected function assertNotRequired(User $actor, array $tagIds): void
    {
        $required = array_intersect($this->prefixes->requiredTagIds(), $tagIds);

        if ($required === []) {
            return;
        }

        $mayStaff = $actor->hasPermission('rubric.useStaffPrefixes');
        $choosable = $this->prefixes->all()->contains(
            fn (Prefix $p) => (! $p->staff_only || $mayStaff) && $p->allowedIn($tagIds)
        );

        if ($choosable) {
            $this->fail('required');
        }
    }

    protected function fail(string $key, array $params = []): never
    {
        throw new ValidationException([
            'rubricPrefixId' => $this->translator->trans('ernestdefoe-rubric.api.'.$key, $params),
        ]);
    }

    protected function tagsEnabled(): bool
    {
        return $this->extensions->isEnabled('flarum-tags');
    }
}
