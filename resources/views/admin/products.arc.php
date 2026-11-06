<SiteLayout title="Manage products" :user="$user" :cart="$cart" :flash="$flash">
<AdminNav />
<div class="section-heading">
<h2>Products <span class="muted">{{ $page->total }}</span>
</h2>
<form method="get" action="/admin/products" class="inline-search">
<label class="sr-only" for="admin-q">Search products</label>
<input id="admin-q" name="q" placeholder="Search products…" value="{{ $search->q }}">
<Button variant="secondary">Search</Button>
</form>
</div>
@if($page->items === [])<EmptyState title="No products found" message="Try another search or add your first product below." href="/admin/products" label="Reset search" />
@else
<Table caption="Product inventory">
<Slot name="head">
<tr>
<th scope="col">Product</th>
<th scope="col">SKU</th>
<th scope="col">Category</th>
<th scope="col">Price</th>
<th scope="col">Available stock</th>
</tr>
</Slot>
@foreach($page->items as $product)<tr>
<td>
<a href="/products/{{ $product['id'] }}">{{ $product['name'] }}</a>
</td>
<td class="mono small">{{ $product['sku'] }}</td>
<td>{{ $product['category'] }}</td>
<td class="mono">${{ number_format($product['price'] / 100, 2) }}</td>
<td>
<form method="post" action="/admin/products/{{ $product['id'] }}/stock" class="stock-form">
<label class="sr-only" for="stock-{{ $product['id'] }}">Stock for {{ $product['name'] }}</label>
<input id="stock-{{ $product['id'] }}" name="stock" type="number" min="0" max="100000" value="{{ $product['stock'] }}" required>
<Button variant="secondary">Save</Button>
</form>
</td>
</tr>
@endforeach
</Table>
<Pagination :page="$page" path="/admin/products" :links="$pagination" />
@endif
<section class="section">
<Card title="Add a product" eyebrow="Catalogue">
<form method="post" action="/admin/products" class="admin-product-form">
<Input name="name" label="Product name" :required="true" />
<Input name="sku" label="SKU" placeholder="DESK-001" :required="true" />
<Input name="category" label="Category" :required="true" />
<Input name="price" label="Price (cents)" type="number" min="1" max="100000000" :required="true" />
<Input name="stock" label="Initial stock" type="number" min="0" max="100000" value="0" :required="true" />
<Button>Create product</Button>
</form>
</Card>
</section>
</SiteLayout>
