<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buku;
use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class PeminjamanController extends Controller
{
    public function index()
    {
        $peminjaman = Peminjaman::with(['user', 'buku'])->latest()->get();

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Fetch all peminjaman',
            'success' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'buku_id' => 'required|integer|exists:bukus,id',
            'stok_dipinjam' => 'required|integer',
            'tanggal_pinjam' => 'required|date',
            'tenggat' => 'required|date|after:tanggal_pinjam',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        $user = User::find($request->user_id);
        $buku = Buku::find($request->buku_id);

        if (! $user) {
            return response()->json([
                'data' => [],
                'message' => 'User tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        if (! $buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        if ($buku->stok <= 0) {
            return response()->json([
                'data' => [],
                'message' => 'Stok buku tidak tersedia.',
                'success' => false,
            ], 400);
        }

        $existingPeminjaman = Peminjaman::where('user_id', $request->user_id)
            ->where('buku_id', $request->buku_id)
            ->where('status', 'dipinjam')
            ->first();

        if ($existingPeminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'User masih memiliki peminjaman aktif untuk buku ini.',
                'success' => false,
            ], 400);
        }

        $peminjaman = new Peminjaman;
        $peminjaman->user_id = $request->user_id;
        $peminjaman->buku_id = $request->buku_id;
        $peminjaman->stok_dipinjam = $request->stok_dipinjam;
        $peminjaman->tanggal_pinjam = $request->tanggal_pinjam;
        $peminjaman->tenggat = $request->tenggat;
        $peminjaman->status = 'dipinjam';
        $peminjaman->save();

        $buku->stok -= $request->stok_dipinjam;
        $buku->save();

        $peminjaman->load(['user', 'buku']);

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Peminjaman berhasil ditambahkan.',
            'success' => true,
        ], 201);
    }

    public function show($id)
    {
        $peminjaman = Peminjaman::with(['user', 'buku'])->find($id);

        if (! $peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $peminjaman,
            'message' => 'Detail peminjaman.',
            'success' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        $peminjaman = Peminjaman::find($id);
        if (! $peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        if ($peminjaman->status === 'dikembalikan') {
            return response()->json([
                'data' => $peminjaman,
                'message' => 'Data peminjaman sudah dikembalikan dan tidak bisa diubah.',
                'success' => false,
            ], 400);
        }

        $buku = Buku::find($peminjaman->buku_id);
        if (! $buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku terkait peminjaman tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_pinjam' => 'sometimes|required|date',
            'tenggat' => 'sometimes|required|date|after:tanggal_pinjam',
            'stok_dipinjam' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:dipinjam,dikembalikan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        if ($request->has('stok_dipinjam')) {
            $stokDipinjamLama = $peminjaman->stok_dipinjam;
            $stokDipinjamBaru = $request->stok_dipinjam;
            $selisihStok = $stokDipinjamBaru - $stokDipinjamLama;

            if ($selisihStok > 0) {
                if ($buku->stok < $selisihStok) {
                    return response()->json([
                        'data' => [],
                        'message' => 'Stok buku tidak mencukupi untuk penambahan peminjaman.',
                        'success' => false,
                    ], 400);
                }
                $buku->stok -= $selisihStok;
            } elseif ($selisihStok < 0) {
                $buku->stok += abs($selisihStok);
            }

            $buku->save();
            $peminjaman->stok_dipinjam = $stokDipinjamBaru;

            if ($stokDipinjamBaru == 0) {
                $peminjaman->status = 'dikembalikan';
                $peminjaman->tanggal_pengembalian = Carbon::now()->format('Y-m-d');

                $pengembalian = new Pengembalian;
                $pengembalian->peminjaman_id = $peminjaman->id;
                $pengembalian->tanggal_pengembalian = $peminjaman->tanggal_pengembalian;
                $pengembalian->save();
            }
        }

        if ($peminjaman->status == 'dipinjam') {
            if ($request->has('tanggal_pinjam')) {
                $peminjaman->tanggal_pinjam = $request->tanggal_pinjam;
            }
            if ($request->has('tenggat')) {
                $peminjaman->tenggat = $request->tenggat;
            }
        }

        if ($request->has('status')) {
            $statusLama = $peminjaman->status;
            $statusBaru = $request->status;

            if ($statusLama == 'dipinjam' && $statusBaru == 'dikembalikan') {
                $peminjaman->status = 'dikembalikan';
                $peminjaman->tanggal_pengembalian = Carbon::now()->format('Y-m-d');

                $buku->stok += $peminjaman->stok_dipinjam;
                $buku->save();

                $peminjaman->stok_dipinjam = 0;

                $existingPengembalian = Pengembalian::where('peminjaman_id', $peminjaman->id)->first();
                if (! $existingPengembalian) {
                    $pengembalian = new Pengembalian;
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
            'success' => true,
        ]);
    }

    // Hapus peminjaman
    public function destroy($id)
    {
        $peminjaman = Peminjaman::find($id);

        if (! $peminjaman) {
            return response()->json([
                'data' => [],
                'message' => 'Peminjaman tidak ditemukan.',
                'success' => false,
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
            'success' => true,
        ]);
    }
}
