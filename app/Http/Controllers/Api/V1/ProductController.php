<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private const PER_PAGE = 15;

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $products = Product::query()
            ->active()
            ->whereHas('category', fn ($category) => $category->active())
            ->with('category')
            ->when(
                $filters['category'] ?? null,
                fn ($query, $slug) => $query->whereHas(
                    'category',
                    fn ($category) => $category->where('slug', $slug)
                )
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, $term) => $query->whereLike('name', "%{$term}%")
            )
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product->load('category');

        abort_unless($product->is_active && $product->category->is_active, 404);

        return ProductResource::make($product);
    }
}