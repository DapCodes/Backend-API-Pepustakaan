<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buku;
use App\Models\Peminjaman;
use Illuminate\Http\Request;
use Storage;
use Validator;

class BukuController extends Controller
{
    private function generateKodeBuku()
    {
        return 'BK-'.str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $buku = Buku::latest()->get();

        return response()->json([
            'data' => $buku,
            'message' => 'Fetch all Buku',
            'success' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255|unique:bukus',
            'penulis' => 'required|string|max:255',
            'penerbit' => 'required|string|max:255',
            'tahun_terbit' => 'required|integer',
            'stok' => 'required|integer|min:0',
            'kategori_id' => 'required|integer',
            'cover' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        $buku = new Buku;
        $buku->kode_buku = $this->generateKodeBuku();
        $buku->judul = $request->judul;
        $buku->penulis = $request->penulis;
        $buku->penerbit = $request->penerbit;
        $buku->tahun_terbit = $request->tahun_terbit;
        $buku->stok = $request->stok;
        $buku->kategori_id = $request->kategori_id;

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('buku', 'public');
            $buku->cover = $path;
        }

        $buku->save();

        return response()->json([
            'data' => $buku,
            'message' => 'Buku berhasil ditambahkan.',
            'success' => true,
        ], 201);
    }

    public function show($id)
    {
        $buku = Buku::with('kategori')->find($id);
        

        if (! $buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $buku,
            'message' => 'Detail buku.',
            'success' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        $buku = Buku::find($id);

        if (! $buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'judul' => 'sometimes|required|string|max:255|unique:bukus,judul,'.$id,
            'penulis' => 'sometimes|required|string|max:255',
            'penerbit' => 'sometimes|required|string|max:255',
            'tahun_terbit' => 'sometimes|required|integer',
            'stok' => 'sometimes|required|integer|min:0',
            'kategori_id' => 'sometimes|required|integer',
            'cover' => 'sometimes|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        $buku->fill($request->except('cover'));

        if ($request->hasFile('cover')) {
            if ($buku->cover && Storage::disk('public')->exists($buku->cover)) {
                Storage::disk('public')->delete($buku->cover);
            }
            $path = $request->file('cover')->store('buku', 'public');
            $buku->cover = $path;
        }

        $buku->save();

        return response()->json([
            'data' => $buku,
            'message' => 'Buku berhasil diperbarui.',
            'success' => true,
        ]);
    }

    // Hapus buku
    public function destroy($id)
    {
        $buku = Buku::find($id);

        if (! $buku) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak ditemukan.',
                'success' => false,
            ], 404);
        }

        $isBorrowed = false;

        if (method_exists($buku, 'peminjamans')) {
            $isBorrowed = $buku->peminjamans()
                ->where(function ($q) {
                    $q->whereNull('tanggal_pengembalian')
                        ->orWhere('status', 'dipinjam')
                        ->orWhereNull('tenggat');
                })->exists();
        } else {
            $isBorrowed = Peminjaman::where('buku_id', $buku->id)
                ->where(function ($q) {
                    $q->whereNull('tanggal_pengembalian')
                        ->orWhere('status', 'dipinjam')
                        ->orWhereNull('tenggat');
                })->exists();
        }

        if ($isBorrowed) {
            return response()->json([
                'data' => [],
                'message' => 'Buku tidak bisa dihapus karena masih ada yang meminjam.',
                'success' => false,
            ], 409);
        }

        // Hapus file cover jika ada
        if ($buku->cover && Storage::disk('public')->exists($buku->cover)) {
            Storage::disk('public')->delete($buku->cover);
        }

        $buku->delete();

        return response()->json([
            'data' => [],
            'message' => 'Buku berhasil dihapus.',
            'success' => true,
        ]);
    }
}
