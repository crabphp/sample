@props(array $categories, App\Http\ProductSearch $search, string $slot = '')
<?php $options = ['' => 'All categories']; foreach ($categories as $category) { $options[$category['category']] = ucfirst($category['category']) . ' (' . $category['products'] . ')'; } ?>
<form class="filters" method="get" action="/products">
<div class="section-heading">
<h2>Filters</h2>
<a class="small muted" href="/products">Reset</a>
</div>
<Input name="q" label="Search" :value="$search->q" placeholder="Mug, lamp, notebook…" type="search" />
<Select name="category" label="Category" :value="$search->category ?? ''" :options="$options" />
<Select name="colour" label="Colour" :value="$search->colour ?? ''" :options="['' => 'All colours', 'black' => 'Black', 'blue' => 'Blue', 'green' => 'Green', 'natural' => 'Natural', 'red' => 'Red', 'white' => 'White']" />
<div class="field-pair">
<Input name="minPrice" label="Min price (¢)" type="number" min="0" :value="(string) ($search->minPrice ?? '')" />
<Input name="maxPrice" label="Max price (¢)" type="number" min="0" :value="(string) ($search->maxPrice ?? '')" />
</div>
<Select name="sort" label="Sort by" :value="$search->sort" :options="['name' => 'Name, A–Z', 'price-asc' => 'Price, low to high', 'price-desc' => 'Price, high to low', 'newest' => 'Newest first']" />
<Button>Apply filters</Button>{!! $slot !!}</form>
