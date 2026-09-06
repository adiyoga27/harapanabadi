@extends('layouts.app')
@section('title', __('lang_v1.stock_log'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('lang_v1.stock_log')
        <small>@lang('lang_v1.stock_log_help')</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
              {!! Form::open(['url' => action('StockLogController@index'), 'method' => 'get', 'id' => 'stock_log_filter_form' ]) !!}
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, null, ['placeholder' => __('messages.all'), 'class' => 'form-control select2', 'style' => 'width:100%']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <br>
                        <div class="checkbox">
                            <label>
                              {!! Form::checkbox('only_mismatch', 1, false,
                              [ 'class' => 'input-icheck', 'id' => 'only_mismatch']) !!} @lang('lang_v1.only_mismatch')
                            </label>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-solid'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="stock_log_table">
                        <thead>
                            <tr>
                                <th>@lang('product.sku')</th>
                                <th>@lang('product.products')</th>
                                <th>@lang('purchase.business_location')</th>
                                <th>@lang('lang_v1.system_stock')</th>
                                <th>@lang('lang_v1.real_stock')</th>
                                <th>@lang('lang_v1.stock_difference')</th>
                                <th>@lang('sale.status')</th>
                                <th>@lang('lang_v1.orphan_mapping')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    stock_log_table = $('#stock_log_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/stock-log',
            data: function(d) {
                d.location_id = $('#location_id').val();
                d.only_mismatch = $('#only_mismatch').is(':checked') ? 1 : 0;
            }
        },
        columns: [
            { data: 'sku', name: 'variations.sub_sku' },
            { data: 'product_name', name: 'p.name' },
            { data: 'location_name', name: 'l.name' },
            { data: 'system_stock', name: 'vld.qty_available' },
            { data: 'real_stock', name: 'real_stock', orderable: false },
            { data: 'difference', name: null, orderable: false },
            { data: 'status', name: null, orderable: false },
            { data: 'orphan_mappings', name: null, orderable: false },
            { data: 'action', name: null, orderable: false, searchable: false },
        ],
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#stock_log_table'));
        },
        order: [[3, 'desc']]
    });

    $(document).on('change', '#location_id, #only_mismatch', function() {
        stock_log_table.ajax.reload();
    });

    $('#stock_log_filter_form').on('submit', function(e) {
        e.preventDefault();
        stock_log_table.ajax.reload();
    });
});
</script>
@endsection
