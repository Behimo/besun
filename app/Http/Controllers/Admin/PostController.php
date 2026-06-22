<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Services\SiteDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private SiteDataService $siteData) {}

    public function index(): View
    {
        $posts = CmsPost::query()->with('category')->latest()->paginate(15);

        return view('admin.posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.posts.form', [
            'post' => new CmsPost,
            'categories' => CmsCategory::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = CmsPost::query()->create($this->validatePost($request));

        return redirect()->route('admin.posts.edit', $post)->with('success', 'مقاله ایجاد شد.');
    }

    public function edit(CmsPost $post): View
    {
        return view('admin.posts.form', [
            'post' => $post,
            'categories' => CmsCategory::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, CmsPost $post): RedirectResponse
    {
        $post->update($this->validatePost($request, $post));

        return redirect()->route('admin.posts.index')->with('success', 'مقاله به‌روزرسانی شد.');
    }

    public function destroy(CmsPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'مقاله حذف شد.');
    }

    private function validatePost(Request $request, ?CmsPost $post = null): array
    {
        $slugRule = ['required', 'string', 'max:120', 'alpha_dash'];
        $slugRule[] = $post
            ? 'unique:cms_posts,slug,'.$post->id
            : 'unique:cms_posts,slug';

        $validated = $request->validate([
            'slug' => $slugRule,
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'exists:cms_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:100'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:300'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ]);

        $validated['is_published'] = $request->boolean('is_published');
        $validated['category_id'] = $validated['category_id'] ?: null;

        return $validated;
    }
}
