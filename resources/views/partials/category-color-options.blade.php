@foreach(\App\Support\CategoryColorPalette::PALETTE as $hex => $label)
    <option value="{{ $hex }}">{{ $label }}</option>
@endforeach
