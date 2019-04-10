<ol class="dd-list">
    @foreach ($dataFilters as $filter)
        <li class="dd-item" data-id="{{ $filter->id }}">
            <div class="pull-right item_actions">
                <div class="btn btn-new-filter-{{$filter->id}}"><i class="voyager-heart"></i><span>
                                                            {{ __('voyager::bread.create_filter') }}</span></div>
                <div class="btn btn-sm btn-danger pull-right delete" data-id="{{ $filter->id }}">
                    <i class="voyager-trash"></i> {{ __('voyager::generic.delete') }}
                </div>
            </div>
            <div class="dd-handle">
                    {{--@include('voyager::multilingual.input-hidden', [--}}
                        {{--'isModelTranslatable' => true,--}}
                        {{--'_field_name'         => 'display_name'.$filter->id,--}}
                        {{--'_field_trans'        => json_encode($filter->getTranslationsOf('display_name'))--}}
                    {{--])--}}
                <span>{{$filter->display_name}}</span> <small class="url">{{$filter->details->table}}</small>
            </div>
            <div class="current_model_options">
                @include('voyager::tools.bread.filter-add',['id'=>$filter->id,'dataType'=>Voyager::model('DataType')->whereName($filter->details->table)->first()])
            </div>
            @if(!$filter->children->isEmpty())
                @include('voyager::tools.bread.filter.builder', ['dataFilters' => $filter->children])
            @endif
        </li>
    @endforeach
</ol>