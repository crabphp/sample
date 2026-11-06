<SiteLayout title="Collection" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">The Crab collection</p>
<h1>Everyday, elevated.</h1>
<p class="muted">Useful objects. Thoughtful details. {{ $page->total }} things to make your own.</p>
</div>
<Badge>{{ $page->total }} products</Badge>
</div>
<div class="catalogue-layout">
<aside>
<FilterSidebar :categories="$categories" :search="$search" />
</aside>
<section aria-label="Catalogue results">
@if($page->items === [])<EmptyState title="Nothing here just yet" message="Try another search or reset your filters to see the whole collection." label="Reset filters" />
@else
<ProductGrid :products="$page->items" />
<Pagination :page="$page" :links="$pagination" />
@endif
</section>
</div>
</SiteLayout>
