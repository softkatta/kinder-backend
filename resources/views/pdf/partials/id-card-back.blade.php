@php $t = $card['theme']; @endphp
<div class="pvc-card card-back" style="background: linear-gradient(160deg, {{ $t['gradient_start'] }} 0%, {{ $t['gradient_end'] }} 100%);">
    <div class="card-back-inner">
        <div class="back-left">
            <div class="digital-pass-label">✦ DIGITAL PASS</div>
            <div class="qr-wrap"><img src="{{ $card['qr_data_uri'] }}" alt="QR" style="width:24mm;height:24mm;display:block;" /></div>
            <div class="qr-hint">Show at reception — staff scanner only</div>
            <div class="qr-card-num">{{ $card['card_number'] }}</div>
        </div>
        <div class="back-right">
            @if($card['emergency_contact'])
                <div class="back-detail"><strong>Emergency</strong><br>{{ $card['emergency_contact'] }}</div>
            @endif
            <div class="back-detail"><strong>Address</strong><br>{{ $card['school']['address'] }}</div>
            <div class="back-detail"><strong>Phone</strong><br>{{ $card['school']['phone'] }}</div>
            <div class="back-detail"><strong>Email</strong><br>{{ $card['school']['email'] }}</div>
            <div class="back-detail"><strong>Website</strong><br>{{ $card['school']['website'] }}</div>
            <div class="dates-row">Issued: {{ $card['issue_date'] }} · Expires: {{ $card['expiry_date'] }}</div>
            <div class="back-note">{{ $card['back_note'] }}</div>
        </div>
    </div>
</div>
