<div class="table-responsive">
    <table class="table table-slim" id="stock_history_table">
        <thead>
        <tr>
            <th>@lang('lang_v1.type')</th>
            <th>@lang('lang_v1.quantity_change')</th>
            <th>@lang('lang_v1.new_quantity')</th>
            <th>@lang('lang_v1.date')</th>
            <th>@lang('purchase.ref_no')</th>
        </tr>
        </thead>
        <tbody>
        @forelse($stock_history as $history)
            <tr>
                <td>{{$history['type_label']}}</td>
                <td>@if($history['quantity_change'] > 0 ) +<span class="display_currency" data-is_quantity="true">{{$history['quantity_change']}}</span> @else <span class="display_currency" data-is_quantity="true">{{$history['quantity_change']}}</span> @endif</td>
                <td><span class="display_currency" data-is_quantity="true">{{$history['stock']}}</span></td>
                <td>{{@format_datetime($history['date'])}}</td>
                <td>{{$history['ref_no']}}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">
                @lang('lang_v1.no_stock_history_found')
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
