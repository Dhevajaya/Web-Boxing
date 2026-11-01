<?php

namespace App\Services\Instagram;

use RuntimeException;

/** Profil pengirim tidak bisa dibaca (belum ada persetujuan, diblokir, atau API gagal). */
class ProfileUnavailable extends RuntimeException {}
