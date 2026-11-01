<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Fighter;
use Illuminate\Support\Facades\DB;

/**
 * Mengikat identitas Instagram yang TERBUKTI (IGSID dari webhook Meta + username
 * dari User Profile API) ke satu petarung, membuatnya bila belum ada.
 *
 * Aturan:
 *  - IGSID adalah identitas sebenarnya; username bisa berganti.
 *  - Bila username berubah, petarung ikut berganti nama dan riwayatnya dicatat.
 *  - Bila username baru sudah dipakai petarung lain yang belum terverifikasi
 *    (dari login manual/dev), petarung terverifikasi yang menang dan petarung
 *    lain itu dipindah ke nama sementara.
 */
class FighterActivator
{
    public function activateVerified(string $igsid, string $username): Fighter
    {
        $username = Fighter::normalizeUsername($username)
            ?? throw new \InvalidArgumentException('Username Instagram tidak valid.');

        return DB::transaction(function () use ($igsid, $username) {
            $byId = Fighter::where('ig_scoped_id', $igsid)->lockForUpdate()->first();
            $byName = Fighter::where('instagram_username', $username)->lockForUpdate()->first();

            if ($byId && $byName && $byId->isNot($byName)) {
                // Nama baru dipegang petarung lain: pindahkan dia ke nama sementara.
                $this->moveAside($byName, $byName->ig_scoped_id ? 'username dipakai akun IG lain' : 'kalah dari akun terverifikasi');
                $byName = null;
            }

            if ($byId) {
                $fighter = $byId;
                if ($fighter->instagram_username !== $username) {
                    $this->rename($fighter, $username);
                }
            } elseif ($byName) {
                if ($byName->ig_scoped_id && $byName->ig_scoped_id !== $igsid) {
                    // Username ini dulu milik akun IG lain yang sudah ganti nama. Data lama disisihkan.
                    $this->moveAside($byName, 'username berpindah pemilik di Instagram');
                    $fighter = new Fighter(['instagram_username' => $username]);
                } else {
                    // Petarung yang dibuat lewat login manual/dev kini terbukti pemiliknya.
                    $fighter = $byName;
                }
            } else {
                $fighter = new Fighter(['instagram_username' => $username]);
            }

            $isNew = ! $fighter->exists;
            $fighter->forceFill([
                'ig_scoped_id' => $igsid,
                'status' => $fighter->status === 'suspended' ? 'suspended' : 'active',
                'verified_at' => $fighter->verified_at ?? now(),
                'activated_at' => $fighter->activated_at ?? now(),
                'last_active_at' => now(),
            ])->save();

            if ($isNew) {
                AuditLog::record('fighter_activated', $fighter, ['via' => 'instagram']);
            }

            return $fighter;
        });
    }

    /** Untuk login manual (admin) dan dev: belum ada bukti IGSID. */
    public function activateUnverified(string $username, string $via): Fighter
    {
        $username = Fighter::normalizeUsername($username)
            ?? throw new \InvalidArgumentException('Username Instagram tidak valid.');

        $fighter = Fighter::firstOrNew(['instagram_username' => $username]);
        $isNew = ! $fighter->exists;
        $fighter->forceFill([
            'status' => $fighter->status ?: 'active',
            'activated_at' => $fighter->activated_at ?? now(),
            'last_active_at' => now(),
        ])->save();

        if ($isNew) {
            AuditLog::record('fighter_activated', $fighter, ['via' => $via]);
        }

        return $fighter;
    }

    private function rename(Fighter $fighter, string $to): void
    {
        DB::table('fighter_username_history')->insert([
            'fighter_id' => $fighter->id,
            'old_username' => $fighter->instagram_username,
            'changed_at' => now(),
        ]);
        AuditLog::record('fighter_renamed', $fighter, ['from' => $fighter->instagram_username, 'to' => $to]);
        $fighter->instagram_username = $to;
        $fighter->save();
    }

    private function moveAside(Fighter $fighter, string $reason): void
    {
        // Petarung dengan IGSID tetap aktif: saat pemiliknya kembali (DM lagi),
        // ia ditemukan lewat IGSID dan namanya dipulihkan otomatis.
        // Petarung tanpa bukti IGSID disuspend agar tidak membingungkan ranking.
        $this->rename($fighter, '_lama_'.$fighter->id);
        if (! $fighter->ig_scoped_id) {
            $fighter->forceFill(['status' => 'suspended'])->save();
        }
        AuditLog::record('fighter_moved_aside', $fighter, ['reason' => $reason]);
    }
}
