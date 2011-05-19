<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Feature;

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Blog\Tests\BlogTestCase;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Actions\InstallPackageAction;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Capell\Tests\Support\ScreenshotManifest;

final class TagsHostScreenshotStateTest extends BlogTestCase
{
    use CreatesAdminUser;

    public function test_capture_opens_the_actual_article_form_with_tags_input(): void
    {
        $this->actingAsAdmin();
        InstallPackageAction::run();
        SiteDomain::factory()->default()->create();
        putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
        putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

        try {
            SeedBlogScreenshotFixtureAction::run();
            $url = ScreenshotManifest::captureUrl(__DIR__ . '/../../docs/screenshots.json', 'article-or-page-form-using-tagsinput');
            $this->get($url)->assertOk()->assertSee('data.tags', false);
        } finally {
            putenv('CAPELL_SCREENSHOT_FIXTURE');
            putenv('CAPELL_SCREENSHOT_APP_PATH');
        }
    }
}
