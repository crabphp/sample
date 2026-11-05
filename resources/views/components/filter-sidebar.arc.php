@props(array $categories, App\Http\ProductSearch $search, string $slot = '')
<form class="filters" method="get" action="/shop">
    <h2>Filter</h2>
    <label for="shop-q">Search</label>
    <input id="shop-q" name="q" value="{{ $search->q }}" placeholder="Mug, lamp...">
    <label for="shop-category">Category</label>
    <select id="shop-category" name="category">
        <option value="">All categories</option>
        @foreach($categories as $category)
            <option value="{{ $category['category'] }}" @if($search->category === $category['category']) selected @endif>{{ $category['category'] }} ({{ $category['products'] }})</option>
        @endforeach
    </select>
    <label for="shop-colour">Colour</label>
    <select id="shop-colour" name="colour">
        <option value="">Any colour</option>
        <option value="blue" @if($search->colour === 'blue') selected @endif>Blue</option>
        <option value="black" @if($search->colour === 'black') selected @endif>Black</option>
    </select>
    <label for="shop-min">Minimum price (cents)</label>
    <input id="shop-min" name="minPrice" type="number" min="0" value="{{ $search->minPrice ?? '' }}">
    <label for="shop-max">Maximum price (cents)</label>
    <input id="shop-max" name="maxPrice" type="number" min="0" value="{{ $search->maxPrice ?? '' }}">
    <button type="submit">Apply filters</button>
</form>
{!! $slot !!}
