@php
    $manual = (bool) ($sourceFlags['manual'] ?? false);
    $doctrine = (bool) ($sourceFlags['doctrine'] ?? false);
    $fitting = (bool) ($sourceFlags['fitting'] ?? false);
@endphp

@if($manual || $doctrine || $fitting)
    <span class="market-seeding-source-icons" aria-label="Target sources">
        @if($manual)
            <span class="market-seeding-source-icon market-seeding-source-manual" title="Manual target">
                <i class="fas fa-user-edit"></i>
            </span>
        @endif
        @if($doctrine)
            <span class="market-seeding-source-icon market-seeding-source-doctrine" title="Doctrine target">
                <i class="fas fa-sitemap"></i>
            </span>
        @endif
        @if($fitting)
            <span class="market-seeding-source-icon market-seeding-source-fitting" title="Saved fitting target">
                <i class="fas fa-paper-plane"></i>
            </span>
        @endif
    </span>
@endif
