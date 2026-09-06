@extends('layouts.app')
@section('title', __('lang_v1.stock_log'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('lang_v1.stock_log') <small>{{ $stock_details['variation'] }}</small></h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['title' => $stock_details['variation']])
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, $location_id, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.stock_comparison')])
                <div class="col-md-3">
                    <div class="info-box bg-green">
                        <span class="info-box-icon"><i class="fa fa-database"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">@lang('lang_v1.system_stock')</span>
                            <span class="info-box-number">{{ $stock_details['current_stock'] }} {{ $stock_details['unit'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-blue">
                        <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">@lang('lang_v1.real_stock')</span>
                            <span class="info-box-number">{{ $real_stock }} {{ $stock_details['unit'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box @if($stock_details['current_stock'] - $real_stock == 0) bg-green @else bg-red @endif">
                        <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">@lang('lang_v1.stock_difference')</span>
                            <span class="info-box-number">
                                @if($stock_details['current_stock'] - $real_stock > 0)+@endif{{ $stock_details['current_stock'] - $real_stock }} {{ $stock_details['unit'] }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-yellow">
                        <span class="info-box-icon"><i class="fa fa-link"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">@lang('lang_v1.orphan_mapping')</span>
                            <span class="info-box-number">{{ $orphan_mappings->count() }}</span>
                        </div>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    @if($orphan_mappings->count() > 0)
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.orphan_mapping')])
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i> @lang('lang_v1.orphan_mapping_help')
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('lang_v1.sell_line_id')</th>
                                <th>@lang('lang_v1.purchase_line_id')</th>
                                <th>@lang('lang_v1.quantity')</th>
                                <th>@lang('lang_v1.created_at')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orphan_mappings as $orphan)
                            <tr>
                                <td>{{ $orphan->id }}</td>
                                <td>{{ $orphan->sell_line_id }}</td>
                                <td>{{ $orphan->purchase_line_id }}</td>
                                <td>{{ $orphan->quantity }}</td>
                                <td>{{ @format_datetime($orphan->created_at) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.purchase_line_breakdown')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="purchase_lines_table">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.purchase_line_id')</th>
                                <th>@lang('lang_v1.type')</th>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.quantity')</th>
                                <th>@lang('lang_v1.quantity_sold')</th>
                                <th>@lang('lang_v1.quantity_adjusted')</th>
                                <th>@lang('lang_v1.quantity_returned')</th>
                                <th>@lang('lang_v1.quantity_remaining')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchase_lines as $pl)
                            <tr>
                                <td>{{ $pl->id }}</td>
                                <td>
                                    @php
                                        $type_labels = [
                                            'purchase' => __('lang_v1.purchase'),
                                            'purchase_transfer' => __('lang_v1.stock_transfers') . ' (' . __('lang_v1.in') . ')',
                                            'opening_stock' => __('report.opening_stock'),
                                            'production_purchase' => __('manufacturing::lang.manufactured'),
                                        ];
                                    @endphp
                                    {{ $type_labels[$pl->transaction_type] ?? $pl->transaction_type }}
                                </td>
                                <td>{{ $pl->ref_no ?: $pl->invoice_no }}</td>
                                <td>{{ @format_datetime($pl->transaction_date) }}</td>
                                <td><span class="display_currency" data-is_quantity="true">{{ $pl->quantity }}</span></td>
                                <td><span class="display_currency" data-is_quantity="true">{{ $pl->quantity_sold }}</span></td>
                                <td><span class="display_currency" data-is_quantity="true">{{ $pl->quantity_adjusted }}</span></td>
                                <td><span class="display_currency" data-is_quantity="true">{{ $pl->quantity_returned }}</span></td>
                                <td>
                                    @if($pl->quantity_available < 0)
                                        <span class="label bg-red">{{ $pl->quantity_available }}</span>
                                    @else
                                        {{ $pl->quantity_available }}
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center">@lang('lang_v1.no_purchase_lines_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.product_stock_history')])
                @include('stock_log.partials.stock_history_table')
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    $('#location_id').on('change', function() {
        var url = '/stock-log/history/{{ $variation->id }}?location_id=' + $(this).val();
        window.location.href = url;
    });

    $('#purchase_lines_table').DataTable({
        searching: false,
        paging: false,
        ordering: false
    });

    $('#stock_history_table').DataTable({
        searching: false,
        ordering: false,
        order: [[3, 'desc']]
    });

    __currency_convert_recursively($('body'));
});
</script>
@endsection
