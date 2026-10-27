<?php

namespace App\Http\Controllers;

use App\Models\FaqArticle;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = FaqCategory::query()
            ->where('is_active', true)
            ->withCount(['articles' => fn ($query) => $query->where('is_published', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function articles(Request $request): JsonResponse
    {
        $query = FaqArticle::query()
            ->published()
            ->whereHas('category', fn ($builder) => $builder->where('is_active', true))
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $request->input('category')));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('limit')) {
            $articles = $query->limit((int) $request->input('limit'))->get();

            return response()->json([
                'success' => true,
                'data' => $articles,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->get('per_page', 20)),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $article = FaqArticle::query()
            ->published()
            ->where('slug', $slug)
            ->with('category')
            ->first();

        if (! $article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        $article->increment('views');

        return response()->json([
            'success' => true,
            'data' => $article->fresh()->load('category'),
        ]);
    }
}
