<?php

return [
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'max' => [
        'string' => ':attribute maksimal :max karakter.',
    ],
    'min' => [
        'string' => ':attribute minimal :min karakter.',
    ],
    'password' => [
        'letters' => ':attribute harus mengandung setidaknya satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus mengandung setidaknya satu angka.',
        'symbols' => ':attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => ':attribute terdeteksi pada kebocoran data. Gunakan password lain.',
    ],
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'attributes' => [
        'email' => 'alamat email',
        'password' => 'password',
        'remember' => 'ingat saya',
        'name' => 'nama',
    ],
];
