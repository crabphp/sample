@props(string $name, string $label, string $value = '', string $type = 'text', string $id = '', string $placeholder = '', string $autocomplete = '', string $min = '', string $max = '', string $step = '', bool $required = false, string $slot = '')
<?php $inputId = $id === '' ? $name : $id; ?>
<div class="field">
<label for="{{ $inputId }}">{{ $label }}</label>
<input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif @if($autocomplete !== '') autocomplete="{{ $autocomplete }}" @endif @if($min !== '') min="{{ $min }}" @endif @if($max !== '') max="{{ $max }}" @endif @if($step !== '') step="{{ $step }}" @endif @if($required) required @endif>
{!! $slot !!}
</div>
