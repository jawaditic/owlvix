{{-- "What do you need?" dropdown, the same in every quote form. $selected: preselected option; $id: element id. --}}
@php($current = old('service', $selected ?? null))
<select name="service" @if (! empty($id)) id="{{ $id }}" @endif>
    @foreach (\App\Support\Enquiries::SERVICE_GROUPS as $group => $options)
        <optgroup label="{{ $group }}">
            @foreach ($options as $opt)
                <option value="{{ $opt }}" @selected($current === $opt)>{{ $opt }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
