@if ($preview)
    <div class="space-y-4">
        <p>{{ __('capell-tags::generic.merge_tags_target') }}: {{ $preview->target }}</p>
        <p>{{ __('capell-tags::generic.merge_sources') }}: {{ implode(', ', $preview->sources) }}</p>
        <p>{{ __('capell-tags::generic.merge_affected', ['count' => $preview->affected]) }}</p>
        <p>{{ __('capell-tags::generic.merge_duplicates', ['count' => $preview->duplicates]) }}</p>
        @include('capell-tags::admin.usage', ['groups' => $preview->usage])
        <h3 class="font-semibold">
            {{ __('capell-tags::generic.merge_aliases') }}
        </h3>
        @foreach ($preview->aliases as $locale => $aliases)
            <p>{{ $locale }}: {{ implode(', ', $aliases) }}</p>
        @endforeach
    </div>
@else
    <p>{{ __('capell-tags::generic.review_stale') }}</p>
@endif
