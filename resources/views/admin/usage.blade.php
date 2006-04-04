<div class="space-y-4">
    @forelse ($groups as $group)
        <section>
            <h3 class="font-semibold">
                {{ $group->type }} · {{ $group->site }} ({{ $group->count }})
            </h3>
            <ul>
                @foreach ($group->records as $record)
                    <li>
                        @if ($record['url'])
                            <a
                                href="{{ $record['url'] }}"
                                class="underline"
                                >{{ $record['label'] }}</a
                            >
                        @else
                            {{ $record['label'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>{{ __('capell-tags::generic.usage_empty') }}</p>
    @endforelse
</div>
