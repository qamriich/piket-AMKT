import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Alpine sudah dibawa Livewire 3. Jangan memanggil Alpine.start() sendiri supaya tidak jalan dua kali.

const hematGerak = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const easeOut = (p) => 1 - Math.pow(1 - p, 3);

/**
 * x-hitung: angka hitung naik. Nilai akhir ada di data-nilai (dan sudah tertulis di HTML,
 * jadi tetap benar tanpa JavaScript). Kalau Livewire mengganti data-nilai, angkanya
 * beranimasi dari nilai lama ke nilai baru.
 */
Alpine.directive('hitung', (el, _, { cleanup }) => {
    const baca = () => parseInt(el.dataset.nilai ?? el.textContent, 10) || 0;
    let sekarang = baca();

    const jalan = (dari, ke, durasi = 1000) => {
        cancelAnimationFrame(el._rafHitung);
        if (hematGerak() || dari === ke) {
            el.textContent = ke;
            return;
        }
        const mulai = performance.now();
        const langkah = (t) => {
            const p = Math.min(1, (t - mulai) / durasi);
            el.textContent = Math.round(dari + (ke - dari) * easeOut(p));
            if (p < 1) el._rafHitung = requestAnimationFrame(langkah);
        };
        el._rafHitung = requestAnimationFrame(langkah);
    };

    jalan(0, sekarang);

    const pengamat = new MutationObserver(() => {
        const baru = baca();
        if (baru !== sekarang) {
            const lama = sekarang;
            sekarang = baru;
            jalan(lama, baru, 700);
        } else if (el.textContent !== String(baru)) {
            el.textContent = baru;
        }
    });
    pengamat.observe(el, { attributes: true, attributeFilter: ['data-nilai'] });
    cleanup(() => {
        pengamat.disconnect();
        cancelAnimationFrame(el._rafHitung);
    });
});

/** x-muncul: muncul dari bawah saat elemen masuk layar. x-muncul="2" = jeda 2 × 80 ms. */
Alpine.directive('muncul', (el, { expression }, { cleanup }) => {
    if (hematGerak() || !('IntersectionObserver' in window)) return;

    const jeda = parseInt(expression || '0', 10) || 0;
    el.classList.add('muncul');
    if (jeda) el.style.animationDelay = `${jeda * 80}ms`;

    const pengamat = new IntersectionObserver((entri) => {
        entri.forEach((e) => {
            if (e.isIntersecting) {
                el.classList.add('tampil');
                pengamat.disconnect();
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    pengamat.observe(el);
    cleanup(() => pengamat.disconnect());
});

/** Hitung mundur ke tenggat + bar progres dari waktu mulai ke tenggat. */
Alpine.data('hitungMundur', (tenggat, mulai) => ({
    angka: 0,
    satuan: '',
    persen: 0,
    lewat: false,
    init() {
        this.perbarui(true);
        this._jam = setInterval(() => this.perbarui(false), 30000);
    },
    destroy() {
        clearInterval(this._jam);
    },
    perbarui(awal) {
        const akhir = new Date(tenggat).getTime();
        const awalWaktu = new Date(mulai).getTime();
        const kini = Date.now();
        const sisa = akhir - kini;
        this.lewat = sisa <= 0;

        let angka;
        if (sisa <= 0) {
            angka = 0;
            this.satuan = 'lewat batas';
        } else if (sisa >= 86400000) {
            angka = Math.floor(sisa / 86400000);
            this.satuan = 'hari lagi';
        } else if (sisa >= 3600000) {
            angka = Math.floor(sisa / 3600000);
            this.satuan = 'jam lagi';
        } else {
            angka = Math.max(1, Math.floor(sisa / 60000));
            this.satuan = 'menit lagi';
        }

        const total = Math.max(1, akhir - awalWaktu);
        const persen = Math.min(100, Math.max(4, ((kini - awalWaktu) / total) * 100));

        if (awal && !hematGerak()) {
            // Bar mulai dari 0 lalu terisi; angka hitung naik.
            requestAnimationFrame(() => requestAnimationFrame(() => { this.persen = persen; }));
            const mulaiAnim = performance.now();
            const langkah = (t) => {
                const p = Math.min(1, (t - mulaiAnim) / 1000);
                this.angka = Math.round(angka * easeOut(p));
                if (p < 1) requestAnimationFrame(langkah);
            };
            requestAnimationFrame(langkah);
        } else {
            this.persen = persen;
            this.angka = angka;
        }
    },
}));

/**
 * Garis penanda tab warga. Bergeser dari posisi tab sebelumnya ke tab sekarang,
 * karena wire:navigate mempertahankan halaman JavaScript.
 */
Alpine.data('penandaTab', (indeks) => ({
    posisi: window.__tabWargaSebelum ?? indeks,
    animasi: false,
    init() {
        const tujuan = indeks;
        window.__tabWargaSebelum = tujuan;
        if (this.posisi === tujuan || hematGerak()) {
            this.posisi = tujuan;
            return;
        }
        requestAnimationFrame(() => {
            this.animasi = true;
            requestAnimationFrame(() => { this.posisi = tujuan; });
        });
    },
}));

/** Toast "Berhasil disimpan". Dari Livewire: $this->dispatch('toast', pesan: '...', jenis: 'sukses'|'gagal'). */
Alpine.store('toast', {
    daftar: [],
    tampil(pesan, jenis = 'sukses') {
        if (!pesan) return;
        const id = Date.now() + Math.random();
        this.daftar.push({ id, pesan, jenis });
        setTimeout(() => this.tutup(id), jenis === 'gagal' ? 6000 : 3500);
    },
    tutup(id) {
        this.daftar = this.daftar.filter((t) => t.id !== id);
    },
});

window.addEventListener('toast', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    Alpine.store('toast').tampil(d?.pesan, d?.jenis);
});

/** Modal konfirmasi pengganti confirm() bawaan browser. */
Alpine.store('konfirmasi', {
    buka: false,
    judul: '',
    pesan: '',
    tombol: 'Ya, lanjutkan',
    bahaya: false,
    aksi: null,
    tanya({ judul, pesan = '', tombol = 'Ya, lanjutkan', bahaya = false, aksi }) {
        Object.assign(this, { judul, pesan, tombol, bahaya, aksi, buka: true });
    },
    ya() {
        const aksi = this.aksi;
        this.buka = false;
        this.aksi = null;
        if (aksi) aksi();
    },
    batal() {
        this.buka = false;
        this.aksi = null;
    },
});

Livewire.start();
