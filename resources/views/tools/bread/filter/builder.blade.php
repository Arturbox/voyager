<ol class="dd-list">
    @foreach ($dataFilters as $filter)
        <li class="dd-item" data-id="{{ $filter->id }}">
            <div class="pull-right item_actions">
                <a href="javascript:;" title="Delete" class="btn btn-sm btn-danger pull-right delete" data-id="{{ $filter->id }}" id="delete-{{ $filter->id }}">
                    <i class="voyager-trash"></i> <span class="hidden-xs hidden-sm">{{ __('voyager::generic.delete') }}</span>
                </a>
            </div>
            <div class="dd-handle">
                    {{--@include('voyager::multilingual.input-hidden', [--}}
                        {{--'isModelTranslatable' => true,--}}
                        {{--'_field_name'         => 'display_name'.$filter->id,--}}
                        {{--'_field_trans'        => json_encode($filter->getTranslationsOf('display_name'))--}}
                    {{--])--}}
                <span>{{$filter->display_name}}</span> <small class="url">{{$filter->details->table}}</small>
            </div>
            @if(!$filter->children->isEmpty())
                @include('voyager::tools.bread.filter.builder', ['dataFilters' => $filter->children])
            @endif
        </li>
    @endforeach
</ol>