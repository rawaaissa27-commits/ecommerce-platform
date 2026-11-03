<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_categories(): void
    {
        Category::factory()->create(['name' => 'Books', 'slug' => 'books']);
        Category::factory()->create(['name' => 'Toys', 'slug' => 'toys']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'books')
            ->assertJsonPath('data.1.slug', 'toys')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description']]]);
    }

    public function test_excludes_inactive_categories(): void
    {
        Category::factory()->create(['slug' => 'visible']);
        Category::factory()->inactive()->create(['slug' => 'hidden']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'visible');
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        $product->category->delete();
    }
}