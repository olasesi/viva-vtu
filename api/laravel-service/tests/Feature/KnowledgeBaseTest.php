<?php

use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function faqCategory(array $overrides = []): FaqCategory
{
    return FaqCategory::create(array_merge([
        'name' => 'Getting Started',
        'slug' => 'getting-started',
        'description' => 'Basics',
        'sort_order' => 1,
        'is_active' => true,
    ], $overrides));
}

function faqArticle(FaqCategory $category, array $overrides = []): FaqArticle
{
    return FaqArticle::create(array_merge([
        'faq_category_id' => $category->id,
        'title' => 'How do I start?',
        'slug' => 'how-do-i-start',
        'excerpt' => 'Start here',
        'content' => 'A detailed answer.',
        'is_published' => true,
    ], $overrides));
}

it('lists only active categories with published article counts', function () {
    $active = faqCategory();
    $inactive = faqCategory([
        'name' => 'Hidden',
        'slug' => 'hidden',
        'is_active' => false,
    ]);

    faqArticle($active);
    faqArticle($active, [
        'title' => 'Draft',
        'slug' => 'draft',
        'is_published' => false,
    ]);
    faqArticle($inactive, ['title' => 'Hidden article', 'slug' => 'hidden-article']);

    $this->getJson('/api/kb/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'getting-started')
        ->assertJsonPath('data.0.articles_count', 1);
});

it('lists published articles and excludes drafts and inactive categories', function () {
    $active = faqCategory();
    $inactive = faqCategory(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

    faqArticle($active);
    faqArticle($active, ['title' => 'Draft', 'slug' => 'draft', 'is_published' => false]);
    faqArticle($inactive, ['title' => 'Hidden article', 'slug' => 'hidden-article']);

    $this->getJson('/api/kb/articles')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.slug', 'how-do-i-start');
});

it('filters articles by category slug', function () {
    $first = faqCategory();
    $second = faqCategory(['name' => 'Security', 'slug' => 'security', 'sort_order' => 2]);

    faqArticle($first);
    faqArticle($second, ['title' => 'Stay safe', 'slug' => 'stay-safe']);

    $this->getJson('/api/kb/articles?category=security')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.slug', 'stay-safe');
});

it('searches articles by title and content', function () {
    $category = faqCategory();

    faqArticle($category, ['title' => 'Refund policy', 'slug' => 'refund-policy', 'content' => 'Refunds take time']);
    faqArticle($category, ['title' => 'Wallet funding', 'slug' => 'wallet-funding', 'content' => 'Paystack and Flutterwave']);

    $this->getJson('/api/kb/articles?search=refund')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.slug', 'refund-policy');
});

it('supports a flat limited article list', function () {
    $category = faqCategory();
    faqArticle($category);

    $this->getJson('/api/kb/articles?limit=5')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'how-do-i-start');
});

it('shows a published article and increments its view count', function () {
    $category = faqCategory();
    $article = faqArticle($category);

    $this->getJson('/api/kb/articles/how-do-i-start')
        ->assertOk()
        ->assertJsonPath('data.slug', 'how-do-i-start')
        ->assertJsonPath('data.category.slug', 'getting-started');

    expect($article->fresh()->views)->toBe(1);
});

it('hides unpublished articles', function () {
    $category = faqCategory();
    faqArticle($category, [
        'title' => 'Draft',
        'slug' => 'draft',
        'is_published' => false,
    ]);

    $this->getJson('/api/kb/articles/draft')->assertStatus(404);
});

it('requires admin access for the knowledge base admin endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/knowledge-base/categories')
        ->assertStatus(403);
});

it('lets an admin create and update a category with a generated slug', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin, 'api')
        ->postJson('/api/admin/knowledge-base/categories', [
            'name' => 'Billing Help',
            'description' => 'Billing questions',
            'is_active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.slug', 'billing-help');

    $id = $response->json('data.id');

    $this->actingAs($admin, 'api')
        ->patchJson("/api/admin/knowledge-base/categories/{$id}", [
            'name' => 'Billing & Refunds',
        ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'billing-refunds');
});

it('generates unique slugs for duplicate titles', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = faqCategory();

    $payload = [
        'faq_category_id' => $category->id,
        'title' => 'Same Title',
        'content' => 'First article',
    ];

    $first = $this->actingAs($admin, 'api')
        ->postJson('/api/admin/knowledge-base/articles', $payload)
        ->assertStatus(201);

    $second = $this->actingAs($admin, 'api')
        ->postJson('/api/admin/knowledge-base/articles', $payload)
        ->assertStatus(201);

    expect($first->json('data.slug'))->toBe('same-title')
        ->and($second->json('data.slug'))->toBe('same-title-2');
});

it('lets an admin publish and unpublish an article', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = faqCategory();
    $article = faqArticle($category, ['is_published' => false]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/admin/knowledge-base/articles/{$article->id}", ['is_published' => true])
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    $this->getJson('/api/kb/articles/how-do-i-start')->assertOk();
});

it('validates article and category payloads', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'api')
        ->postJson('/api/admin/knowledge-base/categories', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($admin, 'api')
        ->postJson('/api/admin/knowledge-base/articles', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faq_category_id', 'title', 'content']);
});

it('deletes a category together with its articles', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = faqCategory();
    faqArticle($category);

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/admin/knowledge-base/categories/{$category->id}")
        ->assertOk();

    $this->assertDatabaseMissing('faq_categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('faq_articles', ['faq_category_id' => $category->id]);
});

it('returns 404 for missing admin resources', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'api')
        ->patchJson('/api/admin/knowledge-base/categories/999999', ['name' => 'Ghost'])
        ->assertStatus(404);

    $this->actingAs($admin, 'api')
        ->patchJson('/api/admin/knowledge-base/articles/999999', ['title' => 'Ghost'])
        ->assertStatus(404);
});

it('seeds the default knowledge base idempotently', function () {
    $this->seed(FaqSeeder::class);
    $this->seed(FaqSeeder::class);

    expect(FaqCategory::count())->toBe(3)
        ->and(FaqArticle::count())->toBe(9)
        ->and(FaqArticle::where('is_published', true)->count())->toBe(9);
});
