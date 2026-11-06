<SiteLayout title="Shopping assistant" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">A little guidance</p>
<h1>Find your kind of everyday.</h1>
<p class="muted">Ask about an object, a room, or a small ritual.</p>
</div>
<Badge>Shopping assistant</Badge>
</div>
<div class="assistant-page">
<Card title="Let’s find something good.">
<AssistantForm :user="$user" />
@if($answer !== null)<div class="conversation">
<p class="eyebrow">You asked</p>
<p>{{ $answer['question'] }}</p>
<hr>
<p class="eyebrow">
@if($answer['turn'] > 0)Your personal assistant · Turn {{ $answer['turn'] }}@else Crab assistant@endif
</p>
<p>{{ $answer['answer'] }}</p>
</div>
@endif
</Card>
</div>
@if($products !== [])<section class="section">
<div class="section-heading">
<h2>Worth a look.</h2>
</div>
<ProductGrid :products="$products" />
</section>
@endif
</SiteLayout>
