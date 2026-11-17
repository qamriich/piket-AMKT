@props(['ukuran' => 42])
{{-- Logo oval berlatar putih, jadi selalu ditaruh di wadah putih agar jelas di latar gelap maupun terang. --}}
<span {{ $attributes->class('flex flex-none items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-sm ring-1 ring-black/5') }}
      style="width: {{ $ukuran * 1.35 }}px; height: {{ $ukuran }}px">
    <img src="{{ asset('images/logo.png') }}" alt="Logo AMKT Putri Mulawarman Makassar" class="h-full w-full object-contain" width="347" height="220">
</span>
