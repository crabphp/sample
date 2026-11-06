<SiteLayout title="Please try again" :user="$user">
    <div class="checkout-panel">
        <p class="eyebrow">{{ $problem->status }}</p>
        <h1>@if($problem->status === 409)Order could not be placed@else Check your details@endif</h1>
        <div class="notice" role="alert">
            <p>{{ $problem->message }}</p>
            @if($errors !== [])
                <ul>
                    @foreach($errors as $field => $messages)
                        @foreach($messages as $message)
                            <li><strong>{{ $field }}</strong>: {{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </div>
        @if($path === '/shop/checkout')
            <p>Your cart has been kept. Review its contents before trying again.</p>
            <p><a href="/shop/cart">Edit cart</a> · <a href="/shop/checkout">Back to checkout</a></p>
        @elseif($path === '/shop/login')
            <p><a href="/shop/login">Try logging in again</a></p>
        @else
            <p><a href="/shop">Back to catalogue</a></p>
        @endif
    </div>
</SiteLayout>
