<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->get('/screenshot-fixtures/tags/tagged-pages', static function (): RedirectResponse {
    abort_unless(app()->environment(['local', 'testing']) && getenv('CAPELL_SCREENSHOT_FIXTURE') === 'record-state', 403);

    $site = Site::query()->firstOrFail();
    $tag = Tag::query()->where('site_id', $site->getKey())->whereJsonContainsLocale('name', 'en', 'Screenshot pages')->first();
    $tag ??= Tag::factory()
        ->site($site)
        ->has(Page::factory()->site($site)->withTranslations()->count(3), 'pages')
        ->create(['name' => ['en' => 'Screenshot pages'], 'slug' => ['en' => 'screenshot-pages']]);

    return redirect(TagResource::getUrl('edit', ['record' => $tag]));
});
