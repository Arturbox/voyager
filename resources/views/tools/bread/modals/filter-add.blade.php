<!-- !!! Add form action below -->
<form role="form" action="{{ route('voyager.bread.filter') }}" method="POST">
    <div class="modal fade modal-danger modal-relationships" id="new_filter_modal">
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
                            <select class="form-control select2 filter_group" name="details">
                                @foreach($dataType->browseRows->where('type','relationship') as  $relationship)
                                    @php
                                        $dataTypeTables[] = [
                                                   'dataTypeTable' => Voyager::model('DataType')->whereName($relationship->details->table)->first(),
                                                    'field' => $relationship->field];
                                    @endphp
                                    <option value="{{json_encode($relationship->details)}}" class="{{$relationship->field}}">{{$relationship->display_name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 relationship_details">
                            <p class="relationship_table_select">Show filed</p>
                            <select class="form-control select2 filter_fields"  name="display_field" value="">
                                @foreach($dataTypeTables as $dataTypeTable)
                                    @foreach($dataTypeTable['dataTypeTable']->browseRows->where('type','text') as  $field)
                                        <option value="{{$field->field}}" class="{{$dataTypeTable['field']}}"> {{$field->display_name}} </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12 relationship_details">
                            <p class="relationship_table_select">Parent</p>
                            <select class="form-control select2 filter_parent" name="parent_id">
                                <option value="">{{__('voyager::generic.none')}}</option>
                                @foreach($dataFilters as  $filter)
                                    <option value="{{$filter->id}}"  >{{$filter->display_name}}</option>
                                @endforeach
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
    {{ csrf_field() }}
</form>