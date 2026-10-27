<?php

namespace App\Http\Controllers;

use App\Models\FaqArticle;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminKnowledgeBaseController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = FaqCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['slug'] = $this->uniqueSlug(FaqCategory::class, $request->input('name'));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $category = FaqCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category created',
            'data' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = FaqCategory::find($id);

        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug(FaqCategory::class, $data['name'], $category->id);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated',
            'data' => $category->fresh(),
        ]);
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $category = FaqCategory::find($id);

        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted']);
    }

    public function articles(Request $request): JsonResponse
    {
        $query = FaqArticle::query()->with('category')->orderBy('sort_order')->orderBy('title');

        if ($request->filled('faq_category_id')) {
            $query->where('faq_category_id', $request->input('faq_category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->get('limit', 25)),
        ]);
    }

    public function showArticle(int $id): JsonResponse
    {
        $article = FaqArticle::with('category')->find($id);

        if (! $article) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $article]);
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'faq_category_id' => 'required|integer|exists:faq_categories,id',
            'title' => 'required|string|max:180',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['slug'] = $this->uniqueSlug(FaqArticle::class, $request->input('title'));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_published'] = $request->boolean('is_published', true);

        $article = FaqArticle::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Article created',
            'data' => $article->load('category'),
        ], 201);
    }

    public function updateArticle(Request $request, int $id): JsonResponse
    {
        $article = FaqArticle::find($id);

        if (! $article) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'faq_category_id' => 'sometimes|required|integer|exists:faq_categories,id',
            'title' => 'sometimes|required|string|max:180',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'sometimes|required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();

        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug(FaqArticle::class, $data['title'], $article->id);
        }

        if ($request->has('is_published')) {
            $data['is_published'] = $request->boolean('is_published');
        }

        $article->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Article updated',
            'data' => $article->fresh()->load('category'),
        ]);
    }

    public function deleteArticle(int $id): JsonResponse
    {
        $article = FaqArticle::find($id);

        if (! $article) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        }

        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article deleted']);
    }

    private function uniqueSlug(string $model, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($model::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}
