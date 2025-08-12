<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $posts = Post::latest()->get();

        return response()->json([
            'data' => $posts,
            'message' => 'Fetch all posts',
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:posts',
            'content' => 'required|string|max:255',
            'status' => 'required',
            'image' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        // $post = Post::create([
        //     'title' => $request->get('title'),
        //     'content' => $request->get('content'),
        //     'status' => $request->get('status'),
        //     'slug' => Str::slug($request->get('title'))
        // ]);

        $post = new Post;
        $post->title = $request->title;
        $post->slug = Str::slug($request->title, '-');
        $post->content = $request->content;
        $post->status = $request->status;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('posts', 'public');
            $post->image = $path;
        }

        $post->save();

        return response()->json([
            'data' => $post,
            'message' => 'Post created successfully.',
            'success' => true,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $post = Post::find($id);
        if (! $post) {
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $post,
            'message' => 'Show post detail',
        ], 200);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:posts,title,'.$id,
            'content' => 'required|string|max:255',
            'status' => 'required',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'message' => $validator->errors(),
                'success' => false,
            ], 400);
        }

        // $post = Post::create([
        //     'title' => $request->get('title'),
        //     'content' => $request->get('content'),
        //     'status' => $request->get('status'),
        //     'slug' => Str::slug($request->get('title'))
        // ]);

        $post = Post::find($id);
        $post->title = $request->title;
        $post->slug = Str::slug($request->title, '-');
        $post->content = $request->content;
        $post->status = $request->status;

        if ($request->hasFile('image')) {
            if ($post->image && Storage::disk('public')->exists($post->image)) {
                Storage::disk('public')->delete($post->image);

                $path = $request->file('image')->store('posts', 'public');
                $post->image = $path;
            }
        }

        $post->save();

        return response()->json([
            'data' => $post,
            'message' => 'Update data success',
            'success' => true,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json(['message' => 'Data not found'], 400);
        }

        if ($post->image && Storage::disk('public')->exists($post->image)) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json([
            'data' => [],
            'message' => 'Post deleted successfully',
            'success' => true,
        ]);
    }
}
