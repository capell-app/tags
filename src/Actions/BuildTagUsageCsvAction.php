<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Builds the CSV behind the tag usage export. It reads the same actor-scoped
 * groups as the usage modal, so the file never lists a record the actor cannot see.
 */
final class BuildTagUsageCsvAction
{
    public function __construct(private readonly BuildTagUsageAction $usage) {}

    public function handle(Tag $tag, Authenticatable $actor): string
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, RuntimeException::class, 'Unable to open a temporary stream for the usage export.');

        fputcsv($stream, [
            (string) __('capell-tags::generic.usage_export_type'),
            (string) __('capell-tags::generic.usage_export_site'),
            (string) __('capell-tags::generic.usage_export_record'),
            (string) __('capell-tags::generic.usage_export_url'),
        ], escape: '');

        foreach ($this->usage->handle([$tag], $actor) as $group) {
            // Records the actor may not view are counted but never listed, so say how many are hidden.
            $hidden = $group->count - count($group->records);

            foreach ($group->records as $record) {
                fputcsv($stream, [
                    self::safe($group->type),
                    self::safe($group->site),
                    self::safe($record['label']),
                    $record['url'] ?? '',
                ], escape: '');
            }

            if ($hidden > 0) {
                fputcsv($stream, [
                    self::safe($group->type),
                    self::safe($group->site),
                    (string) __('capell-tags::generic.usage_export_hidden', ['count' => $hidden]),
                    '',
                ], escape: '');
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * Stops a spreadsheet running a record title such as "=HYPERLINK(...)" as a formula.
     */
    private static function safe(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }
}
