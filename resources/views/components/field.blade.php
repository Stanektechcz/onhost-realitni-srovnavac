@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])
<div>
    <label for="f-{{ $name }}" class="label">{{ $label }}</label>
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->merge(['class' => 'input']) }} @error($name) aria-invalid="true" @enderror>
    @if ($hint)<p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>@endif
    @error($name)<p class="err">{{ $message }}</p>@enderror
</div>
