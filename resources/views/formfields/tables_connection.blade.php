@section('css')
    <style>
    .log_relations {
        padding: 5px;
        margin: 10px 0;
    }
    .tbl_relations_column > .row > div {
        margin-bottom: 0;
        word-break: break-all;
    }
    .section_tbl_name {
        margin-top: 0;
    }
    .tbl_relations_main_wr {
        margin-bottom: 0;
    }
    .tbl_relations_main {
        display: table-row;
    }
    .tbl_relations_wrapper {
        display: table-cell;
        vertical-align: middle;
        border-radius: 5px;
        padding: 0 5px;
    }
    .tbl_relations_name {
        text-align: center;
    }
    .tbl_relations_name p {
        margin-bottom: 0px;
    }
    .tbl_relations {
        display: inline-block;
        margin-bottom: 5px !important;
    }
    </style>
@stop
@php
    $content = json_decode($data->{$row->field});
    $dataType = Voyager::model('DataType')->where('slug', '=', $data->log_name)->first();
@endphp
<div class="container">
    <div class="row bg-danger log_relations">
        <div class="col-md-6 tbl_relations_column">
            <div class="row">
                <div class="col-md-12 text-center">
                    <h3 class="section_tbl_name">{{$dataType->display_name_plural}}</h3>
                </div>
            </div>

            @foreach($dataType->browseRows->where('type','text') as $column)
                @if($column->type == 'text' && isset($content->{$column->field}))
                    @if(is_object($content->{$column->field}) || is_array($content->{$column->field}))@continue;@endif
                     <div class="row">
                        <div class="col-xs-5 text-center ">
                            <p class="bg-success">{{$column->field}}</p>
                        </div>
                        <div class="col-xs-2 text-center">
                            <p class="bg-primary">=></p>
                        </div>
                        <div class="col-xs-5 text-center">
                            <div class="row">
                                <div class="col-md-12 tbl_relations_main_wr">{{$content->{$column->field} }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
            @endforeach

        </div>
        <div class="col-md-6 tbl_relations_main_wr">
            @foreach($dataType->browseRows->where('type','relationship') as $column)
                @php
                    $DataTypeRelation = Voyager::model('DataType')->where('name', '=',$column->details->table)->first();
                @endphp
                <div class="tbl_relations_main">
                    <div class="bg-primary tbl_relations_wrapper tbl_relations_name">
                        <p>{{$DataTypeRelation->display_name_plural}}</p>
                    </div>
                    <div class="tbl_relations_wrapper">
                        @if($column->details->type == 'belongsToMany')
                            @foreach($content->{$column->field} as $id)
                                <span class="well well-sm tbl_relations">{{$DataTypeRelation->model_name::where('id',$id)->first()[$column->details->label]}}</span>
                            @endforeach
                        @elseif($column->details->type == 'belongsTo')
                            {{$column->details->model::where('id',$content->{$column->details->column})->first()[$column->details->label]}}
                        @endif

                    </div>
                </div>
                <hr>
            @endforeach
        </div>
    </div>
</div>





