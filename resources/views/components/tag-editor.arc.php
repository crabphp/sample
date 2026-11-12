@props(string $action, array $tags, array $selected)
<form method="post" action="{{ $action }}">
<fieldset>
<legend class="sr-only">Tags</legend>
@foreach($tags as $tag)<label>
<input type="checkbox" name="tagIds[]" value="{{ $tag->id }}" @if(in_array($tag->id, $selected, true))checked@endif>
{{ $tag->name }}
</label>
@endforeach
</fieldset>
<Button variant="secondary">Save tags</Button>
</form>
