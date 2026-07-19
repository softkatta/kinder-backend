@php
    $t = $card['theme'];
    $m = $card['meta'] ?? [];
@endphp
<div class="pvc-card card-front" style="background: linear-gradient(135deg, {{ $t['gradient_start'] }} 0%, {{ $t['gradient_end'] }} 100%);">
    <div class="card-front-inner" style="padding: 3mm 3.5mm 7mm;">
        <div class="card-header">
            <div class="school-logo">★</div>
            <div class="school-name">
                <h1>{{ strtoupper($card['school']['short_name'] ?? 'LITTLE STARS') }}</h1>
                <p>{{ $card['school']['name'] }}</p>
            </div>
        </div>

        <div class="card-body">
            <div class="card-info">
                <span class="role-badge" style="background: {{ $t['badge_bg'] }}; color: {{ $t['badge_text'] }};">{{ $card['role_badge'] }}</span>
                <div class="holder-name">{{ $card['full_name'] }}</div>
                <div class="card-id">{{ $card['card_number'] }}</div>

                @foreach($card['subtitle_lines'] as $line)
                    <div class="meta-line">{{ $line }}</div>
                @endforeach

                @if($card['card_type'] === 'student' && !empty($m['admission_number']))
                    <div class="meta-line">Adm: {{ $m['admission_number'] }}</div>
                @endif
                @if($card['card_type'] === 'teacher' || $card['card_type'] === 'staff')
                    @if(!empty($m['employee_id']))<div class="meta-line">ID: {{ $m['employee_id'] }}</div>@endif
                @endif
                @if($card['card_type'] === 'parent' && !empty($m['parent_id']))
                    <div class="meta-line">{{ $m['parent_id'] }}</div>
                @endif
                @if($card['card_type'] === 'guest' && !empty($m['visitor_id']))
                    <div class="meta-line">{{ $m['visitor_id'] }}</div>
                @endif

                @if($card['blood_group'])
                    <div class="meta-line">Blood: {{ $card['blood_group'] }}</div>
                @endif

                <div class="validity">{{ $card['validity_label'] }}</div>
            </div>

            <div class="card-photo-wrap">
                <div class="photo-box">
                    @if($card['photo_url'])
                        <img src="{{ $card['photo_url'] }}" alt="">
                    @else
                        <div class="photo-initials">{{ $card['initials'] }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-footer">
            {{ $card['school']['phone'] }} &nbsp;|&nbsp; {{ $card['school']['email'] }}
        </div>
    </div>
</div>
