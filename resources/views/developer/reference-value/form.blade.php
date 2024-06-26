<div class="shadow sm:rounded-md sm:overflow-hidden">
    <div class="px-4 py-5 bg-white space-y-6 sm:p-6">
        <div class="grid grid-cols-1 gap-6">
            <div class="">
                <x-label for="value" value="{{ __('Value') }}" />
                <x-input id="value" name="value" type="text" value="{{old('value', $referenceValue->value ?? null)}}" />
                <x-input-error for="value" class="mt-2" />
            </div>
            {{--<div class="">
                <x-label for="code" value="{{ __('Code') }}" />
                <x-input id="code" name="code" type="text" value="{{old('code', $area->code ?? null)}}" />
                <x-input-error for="code" class="mt-2" />
            </div>--}}

        </div>
    </div>
    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
        <x-secondary-button onclick="window.history.back();" class="mr-2">{{ __('Cancel') }}</x-secondary-button>
        <x-button>{{ __('Submit') }}</x-button>
    </div>
</div>
