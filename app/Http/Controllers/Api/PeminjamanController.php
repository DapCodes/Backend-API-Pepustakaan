<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Peminjaman;
use App\Models\Buku;
use App\Models\User;
use App\Models\Pengembalian;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;

class PeminjamanController extends Controller
{
    // Get semua data peminjaman
    public function index()
    {
        $peminjaman = Peminjaman::with(['user', 'buku'])->latest()->get();
        
        return response()->json([
            'data' => $peminjaman,
            'message' => 'Fetch all peminjaman',
            'success' => true
        ]);
    }

    // Tambah peminjaman baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'buku_id' => 'required|integer|exists:bukus,id',
            'stok_dipinjam' => 'required|integer',
            'tanggal_pinjam' => 'required|date',
            'tenggat' => 'required|date|after:tanggal_pinjam'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false
            ], 400);
        }

        // Cek apakah user dan buku exists
        $user = User::find($request->user_id);
        $buku = Buku::find($request->buku_id);

        if (!$user) {
            return response()->json([
                'data' => [],
                'message' => 'User tidak ditemukan.',
                'success' => false
            ], 404);
        }

        if (!$buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak ditemukan.',
                'success' => false
            ], 404);
        }

        // Cek stok buku
        if ($buku->stok <= 0) {
            return response()->json([
                'data' => [],
                'message' => 'Stok buku tidak tersedia.',
                'success' => false
            ], 400);
        }

        // Cek apakah user masih memiliki peminjaman aktif untuk buku yang sama
        $existingPeminjaman = Peminjaman::where('user_id', $request->user_id)
            ->where('buku_id', $request->buku_id)
            ->where('status', 'dipinjam')
            ->first();

        if ($existingPeminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'User masih memiliki peminjaman aktif untuk buku ini.',
                'success' => false
            ], 400);
        }

        // Buat peminjaman baru
        $peminjaman = new Peminjaman();
        $peminjaman->user_id = $request->user_id;
        $peminjaman->buku_id = $request->buku_id;
        $peminjaman->stok_dipinjam = $request->stok_dipinjam;
        $peminjaman->tanggal_pinjam = $request->tanggal_pinjam;
        $peminjaman->tenggat = $request->tenggat;
        $peminjaman->status = 'dipinjam';
        $peminjaman->save();

        // Kurangi stok buku
        $buku->stok -= $request->stok_dipinjam;
        $buku->save();

        // Load relasi untuk response
        $peminjaman->load(['user', 'buku']);

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Peminjaman berhasil ditambahkan.',
            'success' => true
        ], 201);
    }

    // Detail peminjaman
    public function show($id)
    {
        $peminjaman = Peminjaman::with(['user', 'buku'])->find($id);

        if (!$peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false
            ], 404);
        }

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Detail peminjaman.',
            'success' => true
        ]);
    }

    // Update peminjaman
    public function update(Request $request, $id)
    {
        $peminjaman = Peminjaman::find($id);
        if (!$peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false
            ], 404);
        }

        // Cek jika status sudah dikembalikan, blokir perubahan
        if ($peminjaman->status === 'dikembalikan') {
            return response()->json([
                'data' => $peminjaman,
                'message' => 'Data peminjaman sudah dikembalikan dan tidak bisa diubah.',
                'success' => false
            ], 400);
        }

        // Ambil buku terkait
        $buku = Buku::find($peminjaman->buku_id);
        if (!$buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku terkait peminjaman tidak ditemukan.',
                'success' => false
            ], 404);
        }

        // Validasi
        $validator = Validator::make($request->all(), [
            'tanggal_pinjam' => 'sometimes|required|date',
            'tenggat' => 'sometimes|required|date|after:tanggal_pinjam',
            'stok_dipinjam' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:dipinjam,dikembalikan'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false
            ], 400);
        }

        // Update stok buku jika stok_dipinjam diubah
        if ($request->has('stok_dipinjam')) {
            $stokDipinjamLama = $peminjaman->stok_dipinjam;
            $stokDipinjamBaru = $request->stok_dipinjam;
            $selisihStok = $stokDipinjamBaru - $stokDipinjamLama;

            // Jika stok dipinjam baru > stok dipinjam lama
            if ($selisihStok > 0) {
                // Cek apakah stok buku mencukupi untuk penambahan
                if ($buku->stok < $selisihStok) {
                    return response()->json([
                        'data' => [],
                        'message' => 'Stok buku tidak mencukupi untuk penambahan peminjaman.',
                        'success' => false
                    ], 400);
                }
                // Kurangi stok buku sesuai selisih
                $buku->stok -= $selisihStok;
            } else if ($selisihStok < 0) {
                // Jika stok dipinjam baru < stok dipinjam lama, tambah kembali stok
                $buku->stok += abs($selisihStok);
            }

            $buku->save();
            $peminjaman->stok_dipinjam = $stokDipinjamBaru;

            // Jika stok_dipinjam menjadi 0, otomatis ubah status ke dikembalikan
            if ($stokDipinjamBaru == 0) {
                $peminjaman->status = 'dikembalikan';
                $peminjaman->tanggal_pengembalian = Carbon::now()->format('Y-m-d');
                
                // Buat record pengembalian otomatis
                $pengembalian = new Pengembalian();
                $pengembalian->peminjaman_id = $peminjaman->id;
                $pengembalian->tanggal_pengembalian = $peminjaman->tanggal_pengembalian;
                $pengembalian->save();
            }
        }

        // Update tanggal pinjam & tenggat (hanya jika masih dipinjam)
        if ($peminjaman->status == 'dipinjam') {
            if ($request->has('tanggal_pinjam')) {
                $peminjaman->tanggal_pinjam = $request->tanggal_pinjam;
            }
            if ($request->has('tenggat')) {
                $peminjaman->tenggat = $request->tenggat;
            }
        }

        // Update status manual (jika ada)
        if ($request->has('status')) {
            $statusLama = $peminjaman->status;
            $statusBaru = $request->status;
            
            // Jika status berubah dari dipinjam ke dikembalikan
            if ($statusLama == 'dipinjam' && $statusBaru == 'dikembalikan') {
                $peminjaman->status = 'dikembalikan';
                $peminjaman->tanggal_pengembalian = Carbon::now()->format('Y-m-d');
                
                // Kembalikan semua stok yang dipinjam
                $buku->stok += $peminjaman->stok_dipinjam;
                $buku->save();
                
                // Reset stok dipinjam menjadi 0
                $peminjaman->stok_dipinjam = 0;
                
                // Buat record pengembalian otomatis jika belum ada
                $existingPengembalian = Pengembalian::where('peminjaman_id', $peminjaman->id)->first();
                if (!$existingPengembalian) {
                    $pengembalian = new Pengembalian();
                    $pengembalian->peminjaman_id = $peminjaman->id;
                    $pengembalian->tanggal_pengembalian = $peminjaman->tanggal_pengembalian;
                    $pengembalian->save();
                }
            }
        }

        $peminjaman->save();
        $peminjaman->load(['user', 'buku']);

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Peminjaman berhasil diperbarui.',
            'success' => true
        ]);
    }

    // Hapus peminjaman
    public function destroy($id)
    {
        $peminjaman = Peminjaman::find($id);

        if (!$peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false
            ], 404);
        }

        if ($peminjaman->status == 'dipinjam') {
            $buku = Buku::find($peminjaman->buku_id);
            if ($buku) {
                $buku->stok += $peminjaman->stok_dipinjam;
                $buku->save();
            }
        }

        $peminjaman->delete();

        return response()->json([
            'data' => [],
            'message' => 'Peminjaman berhasil dihapus.',
            'success' => true
        ]);
    }
}