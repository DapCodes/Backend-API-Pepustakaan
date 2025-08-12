<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Validator;

class KategoriController extends Controller
{

    public function index()
    {
        $kategori = kategori::latest()->get();
        return response()->json([
            'data' => $kategori,
            'message' => 'Fetch all kategori',
            'success' => true
        ]);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kategori' => 'required|string|max:255|unique:kategoris',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false
            ], 400);
        }

        $kategori = new Kategori;
        $kategori->nama_kategori = $request->nama_kategori;

        $kategori->save();

        return response()->json([
            'data' => $kategori,
            'message' => 'Kategori created successfully.',
            'success' => true
        ], 201);
    }

   
    public function show($id)
    {
        $kategori = Kategori::find($id);
        if (! $kategori) {
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kategori,
            'message' => 'Show kategori detail',
        ], 200);
    }


    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_kategori' => 'required|string|max:255|unique:kategoris,nama_kategori,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false
            ], 400);
        }

        $kategori = Kategori::find($id);
        $kategori->nama_kategori = $request->nama_kategori;

        $kategori->save();

        return response()->json([
            'data' => $kategori,
            'message' => 'Kategori edited successfully.',
            'success' => true
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $kategori = Kategori::find($id);

        $kategori->delete();

        return response()->json([
            'data' => [],
            'message' => 'kategori deleted successfully',
            'success' => true
        ]);
    }
}
