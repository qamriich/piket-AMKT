<?php

// Pesan validasi berbahasa Indonesia (hanya aturan yang dipakai aplikasi ini).
return [
    'boolean' => ':Attribute harus berupa ya atau tidak.',
    'between' => [
        'numeric' => ':Attribute harus antara :min dan :max.',
        'string' => ':Attribute harus antara :min dan :max karakter.',
    ],
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'date_format' => ':Attribute tidak sesuai format :format.',
    'digits' => ':Attribute harus :digits digit.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',

    'attributes' => [
        'nama' => 'nama',
        'email' => 'email',
        'pin' => 'PIN',
        'urutan' => 'urutan',
        'jenis' => 'jenis',
    ],
];
