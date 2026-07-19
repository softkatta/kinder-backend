@include('pdf.partials.id-card-styles')

<div class="card-sheet">
    @include('pdf.partials.id-card-front', ['card' => $card])
    <span class="card-gap"></span>
    @include('pdf.partials.id-card-back', ['card' => $card])
</div>
