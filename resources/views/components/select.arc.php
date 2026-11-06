@props(string $name, string $label, string $value, array $options, string $slot = '')
<div class="field"><label for="{{ $name }}">{{ $label }}</label><select name="{{ $name }}" id="{{ $name }}">
@foreach($options as $key => $text)
<option value="{{ $key }}" @if((string) $key === $value) selected @endif>{{ $text }}</option>
@endforeach
</select>{!! $slot !!}</div>
