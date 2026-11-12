@props(string $name, string $label, string $value = '', string $id = '', array $attributes = [], string $slot = '')
<?php $inputId = $id === '' ? $name : $id; ?>
<div class="field">
<label for="{{ $inputId }}">{{ $label }}</label>
<input @attributes($attributes, ['id' => $inputId, 'name' => $name, 'type' => 'text', 'value' => $value])>
{!! $slot !!}
</div>
