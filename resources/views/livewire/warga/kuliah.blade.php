<div class="halaman flex flex-col gap-3 px-4 pb-8 pt-5 lg:mx-auto lg:w-full lg:max-w-3xl lg:px-0 lg:pt-8">
    <div class="flex flex-col gap-1">
        <h1 class="m-0 text-[26px] font-extrabold text-tinta">Jadwal kuliah</h1>
        <p class="m-0 text-[15px] leading-normal text-abu-tua">
            Dipakai untuk mengatur hari piket harianmu. Kalau kuliah mulai sebelum {{ $batas }}, kamu tidak dijadwalkan piket di hari itu.
        </p>
    </div>

    <livewire:jadwal-kuliah-editor />
</div>
