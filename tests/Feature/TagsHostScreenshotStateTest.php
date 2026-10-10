<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Feature;

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Blog\Tests\BlogTestCase;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Actions\InstallPackageAction;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Capell\Tests\Support\ScreenshotManifest;
use RuntimeException;

final class TagsHostScreenshotStateTest extends BlogTestCase
{
    use CreatesAdminUser;

    public function test_capture_opens_the_actual_article_form_with_tags_input(): void
    {
        $this->actingAsAdmin();
        InstallPackageAction::run();
        Site::factory()->withTranslations()->create();
        SiteDomain::query()->delete();
        expect(SiteDomain::query()->count())->toBe(0);
        putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
        putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

        try {
            $siteId = Site::query()->orderBy('id')->value('id');
            expect(SeedBlogScreenshotFixtureAction::run())->toBe(4)
                ->and(SiteDomain::query()->where('site_id', $siteId)->exists())->toBeTrue();
            $url = ScreenshotManifest::captureUrl(__DIR__ . '/../../docs/screenshots.json', 'article-or-page-form-using-tagsinput');
            $this->get($url)->assertOk()->assertSee('data.tags', false);
        } finally {
            putenv('CAPELL_SCREENSHOT_FIXTURE');
            putenv('CAPELL_SCREENSHOT_APP_PATH');
        }
    }

    public function test_capture_opens_a_tag_edit_page_with_tagged_pages(): void
    {
        $this->actingAsAdmin();
        SiteDomain::factory()->default()->create();
        putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
        putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

        try {
            require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';
            $response = $this->get('/screenshot-fixtures/tags/tagged-pages');
            $tag = Tag::query()->whereJsonContainsLocale('name', 'en', 'Screenshot pages')->sole();

            $response->assertRedirect(TagResource::getUrl('edit', ['record' => $tag]));
            expect($tag->pages()->count())->toBe(3);

            $entry = ScreenshotManifest::entry(__DIR__ . '/../../docs/screenshots.json', 'tag-relation-manager-showing-tagged-pages');
            expect($entry['beforeWait'] ?? [])->toContain([
                'type' => 'scrollIntoView',
                'selector' => '.fi-ta .fi-ta-row',
                'block' => 'center',
            ])->and($entry['waitFor'] ?? null)->toBe('.fi-ta .fi-ta-row')
                ->and($entry['fullPage'] ?? null)->toBeFalse()
                ->and($entry['required'] ?? null)->toBeFalse();

            $root = dirname(__DIR__, 4);
            $receipt = json_decode(file_get_contents($root . '/docs/screenshot-receipts/recapture-2026-10-06/tags--tag-relation-manager-showing-tagged-pages.json') ?: throw new RuntimeException('Missing Tags capture receipt.'), true, flags: JSON_THROW_ON_ERROR);
            $captures = data_get($receipt, 'provenance.receipts');
            throw_unless(is_array($captures), RuntimeException::class, 'Missing Tags capture provenance.');
            expect($captures)->toHaveCount(2);
            foreach ($captures as $capture) {
                $path = data_get($capture, 'output.path');
                $sha256 = data_get($capture, 'output.sha256');
                throw_unless(is_string($path) && is_string($sha256), RuntimeException::class, 'Invalid Tags capture output.');
                expect(data_get($capture, 'acceptance'))->toBe('accepted')
                    ->and(data_get($capture, 'capture.fullPage'))->toBeFalse()
                    ->and(hash_file('sha256', $root . '/' . $path))->toBe($sha256);
            }
        } finally {
            putenv('CAPELL_SCREENSHOT_FIXTURE');
            putenv('CAPELL_SCREENSHOT_APP_PATH');
        }
    }
}
