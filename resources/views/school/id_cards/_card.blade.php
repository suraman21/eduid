@php
    $accent  = $school->accent_color ?? '#6366f1';
    // Preview mode (school-facing) overlays a watermark and is not print-optimised.
    // The Golden Rule (PRD §03): schools only ever see a low-res preview — the
    // clean, print-ready artwork is generated server-side for the Super Admin only.
    $preview = $preview ?? true;
@endphp

{{--
    Physical ID card: 85.6mm × 54mm (CR80 / credit card standard).
    On screen the card renders at 2× scale (342px × 216px). This partial is the
    PREVIEW used on the school side — deliberately watermarked and not laid out
    for printing. The Super Admin's print pipeline (TCPDF) produces the real
    print-ready file separately.
--}}
<div class="id-card" style="
    position: relative;
    width: 342px; height: 216px;
    border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,.18);
    overflow: hidden;
    font-family: 'Outfit', sans-serif;
    display: flex;
    flex-direction: column;
    background: white;
    flex-shrink: 0;
">

    {{-- PREVIEW watermark — repeating diagonal text the school cannot remove. --}}
    @if($preview)
        <div aria-hidden="true" style="
            position:absolute; inset:0; z-index:5; pointer-events:none;
            background-image: repeating-linear-gradient(-45deg,
                rgba(99,102,241,.10) 0, rgba(99,102,241,.10) 2px,
                transparent 2px, transparent 60px);
            display:flex; align-items:center; justify-content:center;
        ">
            <span style="
                transform: rotate(-22deg);
                font-size: 26px; font-weight: 800; letter-spacing: 4px;
                color: rgba(99,102,241,.28); text-transform: uppercase;
                white-space: nowrap;
            ">Preview</span>
        </div>
    @endif

    {{-- Header stripe --}}
    <div style="
        background: {{ $accent }};
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    ">
        {{-- School logo / initials --}}
        @if($school->logo_url ?? false)
            <img src="{{ $school->logo_url }}" style="width:28px;height:28px;border-radius:4px;object-fit:cover;" alt="">
        @else
            <div style="width:28px;height:28px;border-radius:4px;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;">
                {{ strtoupper(substr($school->name, 0, 2)) }}
            </div>
        @endif
        <div style="flex:1;min-width:0;">
            <p style="color:white;font-size:9px;font-weight:700;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $school->name }}</p>
            <p style="color:rgba(255,255,255,.75);font-size:7px;line-height:1.2;">{{ $school->academic_year }}</p>
        </div>
        <p style="color:rgba(255,255,255,.85);font-size:7px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;">Student ID</p>
    </div>

    {{-- Body --}}
    <div style="flex:1;display:flex;padding:10px 12px;gap:10px;overflow:hidden;">

        {{-- Photo --}}
        <div style="flex-shrink:0;">
            @if($student->photo_url)
                <img src="{{ $student->photo_url }}" alt="{{ $student->full_name }}"
                     style="width:64px;height:80px;object-fit:cover;border-radius:4px;border:1.5px solid #e5e7eb;">
            @else
                <div style="width:64px;height:80px;border-radius:4px;border:1.5px dashed #d1d5db;display:flex;align-items:center;justify-content:center;background:#f9fafb;">
                    <svg style="width:24px;height:24px;color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:3px;">
            <p style="font-size:12px;font-weight:700;color:#111827;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $student->full_name }}
            </p>
            @if($student->full_name_am)
                <p style="font-size:9px;color:#6b7280;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $student->full_name_am }}
                </p>
            @endif
            <p style="font-size:9px;color:#374151;margin-top:2px;">
                <span style="color:#9ca3af;">Class: </span>{{ $student->schoolClass?->display_name ?? '—' }}
            </p>
            @if($student->date_of_birth)
                <p style="font-size:9px;color:#374151;">
                    <span style="color:#9ca3af;">DOB: </span>{{ $student->date_of_birth->format('M d, Y') }}
                </p>
            @endif
            @if($student->blood_type)
                <p style="font-size:9px;color:#374151;">
                    <span style="color:#9ca3af;">Blood: </span><strong>{{ $student->blood_type }}</strong>
                </p>
            @endif
        </div>

        {{-- QR Code --}}
        <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;">
            <div style="width:52px;height:52px;">
                {!! $qrSvg !!}
            </div>
            <p style="font-size:6px;color:#9ca3af;font-family:monospace;letter-spacing:.3px;text-align:center;">{{ $student->uid }}</p>
        </div>

    </div>

    {{-- Footer stripe --}}
    <div style="
        background: #f9fafb;
        border-top: 1px solid #f3f4f6;
        padding: 4px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    ">
        <p style="font-size:7px;color:#9ca3af;">{{ $school->code }}</p>
        <p style="font-size:7px;color:#9ca3af;">{{ $school->phone ?? '' }}</p>
    </div>

</div>
