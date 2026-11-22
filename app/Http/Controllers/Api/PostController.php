<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with(['user:id,name,email', 'category:id,name,slug'])->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like',"%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'status' => 'nullable|in:draft,published',
        ]);

        $slug = Str::slug($validated['title']);
        if (Post::where('slug', $slug)->exists()) {
            $slug .= '-' . now()->timestamp;
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'status' => $validated['status'] ?? 'published',
        ]);

        $post->load(['user:id,name,email','category:id,name,slug']);

        return response()->json([
            'status' => 'success',
            'message' => 'Artikel berhasil dibuat',
            'data' => $post,
        ], 201);
    }

    public function show(string $id)
    {
        $post = Post::with(['user:id,name,email', 'category:id,name,slug'])->find($id);

        if (! $post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Artikel tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Artikel tidak ditemukan',
            ], 404);
        }

        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kamu tidak berhak mengubah artikel ini',
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'title' => 'sometimes|required|max:200',
            'content' => 'sometimes|required|string',
            'status' => 'sometimes|in:draft,published',
        ]);

        if (isset($validated['title'])) {
            $slug = Str::slug($validated['title']);
            if (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug .= '-' . now()->timestamp;
            }
            $validated['slug'] = $slug;
        }

        $post->update($validated);
        $post->load(['user:id,name,email', 'category:id,name,slug']);

        return response()->json([
            'status' => 'success',
            'message' => 'Artikel berhasil diperbarui', 
            'data' => $post,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Artikel tidak ditemukan',
            ], 404);
        }

        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kamu tidak berhak menghapus artikel ini',
            ], 403);
        }

        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Artikel berhasil dihapus'
        ]);
    }
}
