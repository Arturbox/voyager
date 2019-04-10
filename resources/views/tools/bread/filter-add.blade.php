<!-- !!! Add form action below -->
<form role="form" action="{{ route('voyager.bread.filter') }}" method="POST">
    <div class="modal fade modal-danger modal-relationships" id="new_filter{{(isset($id)?'_'.$id:'')}}_modal">
        <div class="modal-dialog relationship-panel">
            <div class="model-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                            aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><i class="voyager-heart"></i> {{ str_singular(ucfirst($table)) }}
                        {{ __('voyager::database.relationship.relationships') }} </h4>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            {{--@include('voyager::multilingual.language-selector')--}}
                        </div>
                        <div class="col-md-12 relationship_details">
                            <p class="relationship_table_select">Filter Table</p>
                            <select class="form-control select2" name="details">
                                @if(!empty($dataType) && $dataType->browseRows->where('type','relationship')->count()>0)
                                    @foreach($dataType->browseRows->where('type','relationship') as  $relationship)
                                        <option value="{{json_encode($relationship->details)}}">{{$relationship->display_name}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-12 relationship_details">
                            <p class="relationship_table_select">Filter Name</p>
                            <input type="text" class="form-control" value="" name="display_name">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="relationship-btn-container">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('voyager::database.relationship.cancel') }}</button>
                        @if(isset($dataType->id))
                            <button class="btn btn-danger btn-relationship"><i class="voyager-plus"></i> <span>{{ __('voyager::database.relationship.add_new') }}</span></button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" value="@if(isset($dataType->id)){{ $dataType->id }}@endif" name="data_type_id">
    <input type="hidden" value="@if(isset($id)){{ $id }}@endif" name="parent_id">
    {{ csrf_field() }}
</form>