<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Carbon;
use App\Http\Resources\PostResource;
use App\Http\Requests\PostCreateRequest;
use App\Http\Requests\PostUpdateRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activePosts = Post::with('user')
            ->where('is_draft', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(20);

        return PostResource::collection($activePosts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 'posts.create';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostCreateRequest $request)
    {
        $validated = $request->validated();

        if ($validated['is_draft'] === true) {
            $validated['published_at'] = null;
        }

        $post = Post::create(array_merge($validated, [
            'user_id' => $request->user()->id,
        ]));

        return response()->json([
            'code'      => 201,
            'message'   => 'Post created successfully.',
            'data'      => new PostResource($post->load('user')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        if ($post->is_draft || is_null($post->published_at) || $post->published_at > now()) {
            abort(404);
        }

        return new PostResource($post);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PostUpdateRequest $request, Post $post)
    {
        $validated = $request->validated();

        if (isset($validated['published_at'])) {
            $validated['published_at'] = Carbon::parse($validated['published_at']);
        }

        if ($post->published_at && $post->published_at <= now()) {
            if (isset($validated['published_at']) && $validated['published_at'] < $post->published_at) {
                unset($validated['published_at']);
            }
        }

        if ($post->is_draft === false) {
            unset($validated['is_draft']);
        }

        $post->update($validated);
        $post->refresh()->load('user');

        return response()->json([
            'code'      => 200,
            'message'   => 'Post updated successfully.',
            'data'      => new PostResource($post),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json([
            'code'      => 200,
            'message'   => 'Post deleted successfully.'
        ]);
    }
}
