@props(['status' => null, 'jenis' => null, 'label' => null])
@php
    // status: App\Enums\StatusPenugasan|StatusPengajuan; jenis: 'kosong'|'netral' untuk badge non-status.
    $kelas = $status ? $status->badgeClass() : 'badge badge-'.($jenis ?? 'netral');
    $teks = $label ?? $status?->label() ?? '';
@endphp
<span {{ $attributes->class($kelas) }}>{{ $teks }}</span>
