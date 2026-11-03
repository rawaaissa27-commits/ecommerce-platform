<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_products_with_category(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.category.slug', $product->category->slug)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'description', 'price', 'category']],
                'links',
                'meta',
            ]);
    }

    public function test_excludes_inactive_products(): void
    {
        $active = Product::factory()->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $active->slug);
    }

    public function test_filters_products_by_category_slug(): void
    {
        $books = Category::factory()->create(['slug' => 'books']);
        $toys = Category::factory()->create(['slug' => 'toys']);
        $book = Product::factory()->for($books)->create();
        Product::factory()->for($toys)->create();

        $this->getJson('/api/v1/products?category=books')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $book->slug);
    }

    public function test_unknown_category_returns_empty_list(): void
    {
        Product::factory()->create();

        $this->getJson('/api/v1/products?category=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_searches_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Red Widget', 'slug' => 'red-widget']);
        Product::factory()->create(['name' => 'Blue Gadget', 'slug' => 'blue-gadget']);

        $this->getJson('/api/v1/products?search=widget')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'red-widget');
    }

    public function test_paginates_products_with_fifteen_per_page(): void
    {
        Product::factory()->count(16)->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/products?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rejects_invalid_query_parameters(): void
    {
        $this->getJson('/api/v1/products?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');

        $this->getJson('/api/v1/products?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_listing_query_count_does_not_grow_with_results(): void
    {
        Product::factory()->count(2)->create();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $small = count(DB::getQueryLog());

        Product::factory()->count(13)->create();
        DB::flushQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $large = count(DB::getQueryLog());

        $this->assertSame($small, $large);
    }

    public function test_shows_product_details_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Desk Lamp',
            'slug' => 'desk-lamp',
            'price' => 12.5,
        ]);

        $this->getJson('/api/v1/products/desk-lamp')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', 'desk-lamp')
            ->assertJsonPath('data.price', '12.50')
            ->assertJsonPath('data.category.slug', $product->category->slug);
    }

    public function test_returns_404_for_inactive_product(): void
    {
        Product::factory()->inactive()->create(['slug' => 'hidden-product']);

        $this->getJson('/api/v1/products/hidden-product')->assertNotFound();
    }

    public function test_returns_404_for_nonexistent_product(): void
    {
        $this->getJson('/api/v1/products/nope')->assertNotFound();
    }

    public function test_excludes_active_products_of_inactive_categories_from_listing(): void
    {
        $visible = Product::factory()->create();

        Product::factory()
            ->for(Category::factory()->inactive())
            ->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $visible->slug);
    }

    public function test_category_filter_never_returns_products_of_inactive_categories(): void
    {
        $inactive = Category::factory()->inactive()->create(['slug' => 'archived']);

        Product::factory()->for($inactive)->create();

        $this->getJson('/api/v1/products?category=archived')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_404_for_active_product_of_inactive_category(): void
    {
        Product::factory()
            ->for(Category::factory()->inactive())
            ->create(['slug' => 'orphaned-by-category']);

        $this->getJson('/api/v1/products/orphaned-by-category')->assertNotFound();
    }
}