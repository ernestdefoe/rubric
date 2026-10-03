<?php

namespace Ernestdefoe\Rubric\Api\Controller;

use Ernestdefoe\Rubric\Prefix;
use Ernestdefoe\Rubric\PrefixRepository;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The admin page's CRUD. Every endpoint answers with the whole list, in order,
 * so the page never has to reconcile a partial result with what it shows.
 */
abstract class AbstractPrefixController implements RequestHandlerInterface
{
    public function __construct(
        protected PrefixRepository $prefixes,
        protected TranslatorInterface $translator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $this->act($request);
        $this->prefixes->forget();

        return new JsonResponse(['data' => $this->prefixes->payload()]);
    }

    abstract protected function act(ServerRequestInterface $request): void;

    protected function routeId(ServerRequestInterface $request): int
    {
        return (int) Arr::get($request->getQueryParams(), 'id');
    }

    /** Validate and apply the editable fields from the request body. */
    protected function fill(Prefix $prefix, array $body): void
    {
        $name = trim((string) ($body['name'] ?? ''));
        $color = strtolower(trim((string) ($body['color'] ?? '')));
        $icon = trim((string) ($body['icon'] ?? ''));
        $slug = Str::slug(trim((string) ($body['slug'] ?? '')) ?: $name);

        $errors = [];

        if ($name === '' || mb_strlen($name) > 60) {
            $errors['name'] = $this->translator->trans('ernestdefoe-rubric.api.invalid_name');
        }

        if (preg_match('/^#[0-9a-f]{3}$/', $color)) {
            $color = '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
        }

        if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $errors['color'] = $this->translator->trans('ernestdefoe-rubric.api.invalid_color');
        }

        // Font Awesome class names only: "fas fa-bullhorn". Nothing that could
        // close the attribute it is written into.
        if ($icon !== '' && ! preg_match('/^[a-z0-9 -]{1,100}$/i', $icon)) {
            $errors['icon'] = $this->translator->trans('ernestdefoe-rubric.api.invalid_icon');
        }

        if ($slug === '' || mb_strlen($slug) > 80) {
            $errors['slug'] = $this->translator->trans('ernestdefoe-rubric.api.invalid_slug');
        } elseif (Prefix::query()->where('slug', $slug)->where('id', '!=', (int) $prefix->id)->exists()) {
            $errors['slug'] = $this->translator->trans('ernestdefoe-rubric.api.slug_taken');
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $tagIds = array_values(array_unique(array_filter(array_map('intval', (array) ($body['tagIds'] ?? [])))));

        $prefix->name = $name;
        $prefix->slug = $slug;
        $prefix->color = $color;
        $prefix->icon = $icon !== '' ? $icon : null;
        $prefix->staff_only = (bool) ($body['staffOnly'] ?? false);
        $prefix->tag_ids = $tagIds;
    }
}
