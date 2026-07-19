@include('pdf.partials.id-card-styles')

@foreach($cards as $card)
<div class="card-sheet bulk-row">
    @include('pdf.partials.id-card-front', ['card' => $card])
    @include('pdf.partials.id-card-back', ['card' => $card])
</div>
@if(!$loop->last)<div class="page-break"></div>@endif
@endforeach
