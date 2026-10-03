<?php

use Ernestdefoe\Rubric\Api\Controller;
use Ernestdefoe\Rubric\Listener\ValidatePrefix;
use Ernestdefoe\Rubric\PrefixRepository;
use Ernestdefoe\Rubric\Search\PrefixFilter;
use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/forum.less')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ServiceProvider())
        ->register(Ernestdefoe\Rubric\RubricServiceProvider::class),

    /*
     * The whole prefix list rides along with the forum payload. It is a
     * handful of rows, and having it up front means a label on a discussion
     * is drawn from memory — never a request per discussion in the list.
     */
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Arr::make('rubricPrefixes')
                ->get(fn () => resolve(PrefixRepository::class)->payload()),
            Schema\Arr::make('rubricRequiredTags')
                ->get(fn () => resolve(PrefixRepository::class)->requiredTagIds()),
            Schema\Boolean::make('canUseStaffPrefixes')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('rubric.useStaffPrefixes')),
        ]),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\Integer::make('rubricPrefixId')
                ->nullable()
                ->writable(fn (Discussion $discussion, Context $context) => $context->creating()
                    || $context->getActor()->can('rename', $discussion))
                ->get(fn (Discussion $discussion) => $discussion->rubric_prefix_id ? (int) $discussion->rubric_prefix_id : null)
                // Only the value is applied here. Whether this member may use
                // it, in these tags, is decided in ValidatePrefix once the
                // discussion's tags from the same request are known.
                ->set(function (Discussion $discussion, $value) {
                    $discussion->rubric_prefix_id = $value ? (int) $value : null;
                }),
        ]),

    (new Extend\Event())
        ->listen(Saving::class, ValidatePrefix::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addFilter(DiscussionSearcher::class, PrefixFilter::class),

    (new Extend\Routes('api'))
        ->get('/rubric/prefixes', 'ernestdefoe-rubric.prefixes.index', Controller\ListPrefixesController::class)
        ->post('/rubric/prefixes', 'ernestdefoe-rubric.prefixes.create', Controller\CreatePrefixController::class)
        ->post('/rubric/prefixes/order', 'ernestdefoe-rubric.prefixes.order', Controller\OrderPrefixesController::class)
        ->patch('/rubric/prefixes/{id}', 'ernestdefoe-rubric.prefixes.update', Controller\UpdatePrefixController::class)
        ->delete('/rubric/prefixes/{id}', 'ernestdefoe-rubric.prefixes.delete', Controller\DeletePrefixController::class),

    (new Extend\Settings())
        ->default(PrefixRepository::REQUIRED_TAGS, '[]'),
];
