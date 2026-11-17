<x-mail::message>
# Halo, {{ $user->nama }}

@if ($kirimUlang)
Pengurus sudah membuat link pribadi baru untukmu. **Link lama sudah tidak berlaku.**
@else
Akunmu di sistem jadwal piket Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar sudah dibuat.
@endif

Lewat link ini kamu bisa melihat jadwal piket, menandai piket selesai, dan melihat riwayatmu. Tidak perlu password.

<x-mail::button :url="$url">
Buka jadwal piket saya
</x-mail::button>

Simpan email ini baik-baik dan **jangan bagikan link ini ke siapa pun**, karena siapa saja yang membuka link ini akan masuk sebagai kamu. Kalau link hilang atau tersebar, minta pengurus mengirim ulang link.

Salam,<br>
Pengurus AMKT Putri Mulawarman
</x-mail::message>
