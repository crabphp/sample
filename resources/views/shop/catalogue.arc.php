<SiteLayout title="Catalogue" :user="$user">
    <section class="catalogue-head">
        <div>
            <p class="eyebrow">The Crab collection</p>
            <h1>Objects for a considered day</h1>
            <p>{{ $page->total }} pieces, filtered and paged by the same API as the feed.</p>
        </div>
        <a class="button secondary" href="/shop/cart">View cart</a>
    </section>
    <div class="shop-grid">
        <FilterSidebar :categories="$categories" :search="$search" />
        <section aria-label="Catalogue results">
            @if($page->items === [])
                <div class="card"><h2>No products found</h2><p>Try a broader search or another category.</p></div>
            @else
                <ProductGrid :products="$page->items" />
                <Pagination :page="$page" :links="$pagination" />
            @endif
        </section>
    </div>
</SiteLayout>
