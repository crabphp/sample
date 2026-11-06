<SiteLayout title="{{ $problem->status }}" :user="$user" :cart="$cart" :flash="$flash">
<section class="error-page">
<p class="eyebrow mono">{{ $problem->status }} / CRAB</p>
<h1>{{ match ($problem->status) { 404 => 'A little off the path.', 403 => 'This door is closed.', 401 => 'Let’s get you signed in.', 429 => 'A moment, please.', 409 => 'Something needs a second look.', 422 => 'Check your details.', default => 'Something went wrong.' } }}</h1>
<div role="alert">
<p class="muted">{{ $problem->status >= 500 ? 'We couldn’t complete that request. Please try again in a moment.' : $problem->message }}</p>
@if($errors !== [])<ul class="validation-errors">
@foreach($errors as $field => $messages)@foreach($messages as $message)<li>
<strong>{{ $field }}</strong>: {{ $message }}</li>
@endforeach
@endforeach
</ul>
@endif
</div>
<div class="actions">
@if($path === '/checkout' || str_starts_with($path, '/cart'))<Button href="/cart">Review your cart</Button>
<Button href="/checkout" variant="secondary">Back to checkout</Button>
@elseif($path === '/login')<Button href="/login">Back to sign in</Button>
@elseif($path === '/register')<Button href="/register">Back to registration</Button>
@elseif(str_starts_with($path, '/admin'))<Button href="/admin">Back to administration</Button>
@elseif(str_starts_with($path, '/assistant'))<Button href="/assistant">Try the assistant again</Button>
@else
<Button href="/products">Explore the collection</Button>
@endif
<Button href="/" variant="secondary">Go home</Button>
</div>
</section>
</SiteLayout>
