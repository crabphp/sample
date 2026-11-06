@props(?App\User $user = null, string $slot = '')
<form method="post" action="/assistant" class="assistant-form" data-assistant-form>
<Input name="question" label="What are you looking for?" placeholder="Something for my morning coffee…" :required="true" />
<div class="actions"><Button>Ask the assistant <span aria-hidden="true">↗</span></Button>@if($user !== null)<Button variant="secondary" action="/assistant/chat">Continue my conversation</Button>@else<a href="/login" class="small muted">Sign in for a personal conversation</a>@endif</div>
</form>
<div class="assistant-answer" data-assistant-answer role="status" aria-live="polite"></div>
<p class="small muted">Product suggestions from our catalogue. Personal conversations remember your recent choices while you browse.</p>{!! $slot !!}
