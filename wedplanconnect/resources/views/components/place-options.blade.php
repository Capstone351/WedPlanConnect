@props(['id', 'type' => 'venues'])

{{-- Suggestions for a place input (link it with list="{{ $id }}"). People can still type a place that isn't listed. --}}
<datalist id="{{ $id }}">
    @foreach (config("cebu_places.{$type}", []) as $kind => $places)
        @foreach ($places as $place)
            <option value="{{ $place }}" label="{{ $kind }}"></option>
        @endforeach
    @endforeach
</datalist>
