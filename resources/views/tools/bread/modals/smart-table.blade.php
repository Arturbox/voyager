<!-- !!! Add form action below -->
<form role="form" action="{{ route('voyager.bread.filter') }}" method="POST">
    <div class="modal fade modal-danger modal-relationships" id="smart_table">
        <div class="modal-dialog relationship-panel">
            <div class="model-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                            aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><i class="voyager-heart"></i> {{ str_singular(ucfirst($table)) }}
                                                {{ __('voyager::generic.smart_table') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            {{--@include('voyager::multilingual.language-selector')--}}
                        </div>

                        <div class="col-md-12 relationship_details">
                            <p class="relationship_table_select">Table Name</p>
                            <input type="text" class="form-control" value="" name="display_name">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="relationship-btn-container">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                        @if(isset($dataType->id))
                            <button class="btn btn-danger btn-relationship"><i class="voyager-plus"></i>
                                <span>{{ __('voyager::generic.add') }}</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" value="@if(isset($dataType->id)){{ $dataType->id }}@endif" name="data_type_id">
    {{ csrf_field() }}
</form>