@props(?App\User $user = null, string $slot = '')
<form method="post" action="{{ $user !== null ? '/assistant/chat' : '/assistant' }}" class="assistant-form" data-assistant-form data-conversation="{{ $user !== null ? 'true' : 'false' }}">
<Input name="question" label="What are you looking for?" placeholder="A mug, a lamp, a notebook…" autocomplete="off" required />
<div class="actions">
<Button>@if($user !== null)Send message@else Ask the assistant <span aria-hidden="true">↗</span>@endif
</Button>
@if($user === null)<a href="/login" class="small muted">Sign in for a personal conversation</a>
@endif
</div>
</form>
<div class="assistant-answer" data-assistant-answer role="status" aria-live="polite">
</div>
<p class="small muted">Product suggestions from our catalogue. Personal conversations remember your recent choices while you browse.</p>{!! $slot !!}
