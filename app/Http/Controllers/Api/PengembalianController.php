<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengembalian;
use App\Models\Peminjaman;
use App\Models\Buku;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PengembalianController extends Controller
{
    public function index()
    {
        $pengembalian = Pengembalian::with(['peminjaman.user', 'peminjaman.buku'])->latest()->get();
        
        $pengembalian = $pengembalian->map(function ($item) {
            $peminjaman = $item->peminjaman;
            $tenggat = Carbon::parse($peminjaman->tenggat);
            $tanggalKembali = Carbon::parse($item->tanggal_pengembalian);
            $isLate = $tanggalKembali->gt($tenggat);
            $daysLate = $isLate ? $tanggalKembali->diffInDays($tenggat) : 0;
            
            $item->keterlambatan = [
                'is_late' => $isLate,
                'days_late' => $daysLate,
                'tenggat' => $peminjaman->tenggat
            ];
            
            return $item;
        });
        
        return response()->json([
            'data' => $pengembalian,
            'message' => 'Fetch all pengembalian',
            'success' => true
        ]);
    }

    public function show($id)
    {
        $pengembalian = Pengembalian::with(['peminjaman.user', 'peminjaman.buku'])->find($id);

        if (!$pengembalian) {
            return response()->json([
                'data' => [],
                'message' => 'Pengembalian tidak ditemukan.',
                'success' => false
            ], 404);
        }

        $peminjaman = $pengembalian->peminjaman;
        $tenggat = Carbon::parse($peminjaman->tenggat);
        $tanggalKembali = Carbon::parse($pengembalian->tanggal_pengembalian);
        $isLate = $tanggalKembali->gt($tenggat);
        $daysLate = $isLate ? $tanggalKembali->diffInDays($tenggat) : 0;
        
        $responseData = $pengembalian->toArray();
        $responseData['keterlambatan'] = [
            'is_late' => $isLate,
            'days_late' => $daysLate,
            'tenggat' => $peminjaman->tenggat
        ];

        return response()->json([
            'data' => $responseData,
            'message' => 'Detail pengembalian.',
            'success' => true
        ]);
    }

    public function getPeminjamanBelumKembali()
    {
        $peminjamanBelumKembali = Peminjaman::with(['user', 'buku'])
            ->where('status', 'dipinjam')
            ->where('stok_dipinjam', '>', 0)
            ->latest()
            ->get();

        return response()->json([
            'data' => $peminjamanBelumKembali,
            'message' => 'Fetch peminjaman yang belum dikembalikan',
            'success' => true
        ]);
    }

    public function getPeminjamanTerlambat()
    {
        $today = Carbon::now()->format('Y-m-d');
        
        $peminjamanTerlambat = Peminjaman::with(['user', 'buku'])
            ->where('status', 'dipinjam')
            ->where('stok_dipinjam', '>', 0)
            ->where('tenggat', '<', $today)
            ->latest()
            ->get();

        $peminjamanTerlambat = $peminjamanTerlambat->map(function ($item) use ($today) {
            $tenggat = Carbon::parse($item->tenggat);
            $todayCarbon = Carbon::parse($today);
            $daysLate = $todayCarbon->diffInDays($tenggat);
            
            $item->days_late = $daysLate;
            
            return $item;
        });

        return response()->json([
            'data' => $peminjamanTerlambat,
            'message' => 'Fetch peminjaman yang terlambat',
            'success' => true
        ]);
    }
}