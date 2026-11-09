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
<div class="chat-thread" data-chat-thread>
@if($messages === [])<div class="chat-empty">
<p class="eyebrow">Start a conversation</p>
<p class="muted">Ask for a product, a colour, or something for a particular room.</p>
</div>
@else
@foreach($messages as $message)<article class="chat-turn">
<div class="chat-message chat-message-user">
<p class="eyebrow">You</p>
<p>{{ $message['question'] }}</p>
</div>
<div class="chat-message chat-message-assistant">
<p class="eyebrow">Crab assistant · Turn {{ $message['turn'] }}</p>
<p>{{ $message['answer'] }}</p>
</div>
</article>
@endforeach
@endif
</div>
<AssistantForm :user="$user" />
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
